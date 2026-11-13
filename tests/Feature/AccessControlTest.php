<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Filament\Resources\InvoiceResource;
use App\Models\Bed;
use App\Models\Complaint;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Two residents, each with one invoice.
     *
     * @return array{0: Guest, 1: Guest, 2: Invoice, 3: Invoice}
     */
    private function twoResidents(): array
    {
        Property::factory()->create(['billing_cycle_start_day' => 1]);
        $room = Room::factory()->create(['room_number' => '101', 'sharing_type' => 'double', 'base_rent_per_bed' => 9000]);
        $room->syncBedsToCapacity();

        [$bedA, $bedB] = Bed::query()->orderBy('id')->get()->all();
        $alice = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);
        $bob = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);

        $allocation = app(BedAllocationService::class);
        $bookingA = $allocation->allocate($alice, $bedA->id, ['check_in_date' => now()->startOfMonth()->toDateString(), 'monthly_rent' => 9000]);
        $bookingB = $allocation->allocate($bob, $bedB->id, ['check_in_date' => now()->startOfMonth()->toDateString(), 'monthly_rent' => 9000]);

        $service = app(InvoiceService::class);
        $invoiceA = $service->generateForBooking($bookingA, now()->startOfMonth(), now()->endOfMonth());
        $invoiceB = $service->generateForBooking($bookingB, now()->startOfMonth(), now()->endOfMonth());

        return [$alice, $bob, $invoiceA, $invoiceB];
    }

    public function test_tenants_and_disabled_staff_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->tenant()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->inactive()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk();
    }

    public function test_tenants_cannot_open_printed_invoices_from_the_web(): void
    {
        [, , $invoice] = $this->twoResidents();

        $this->actingAs(User::factory()->tenant()->create())
            ->get(route('invoices.print', $invoice))
            ->assertForbidden();
    }

    public function test_finance_screens_are_hidden_from_wardens(): void
    {
        Property::factory()->create();

        $this->actingAs(User::factory()->staff()->create());
        $this->get('/admin/expenses')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get(route('reports.download', 'rent-roll'))->assertForbidden();

        $this->actingAs(User::factory()->manager()->create());
        $this->get('/admin/expenses')->assertOk();
        $this->get('/admin/reports')->assertOk();
        $this->get('/admin/users')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/users')->assertOk();
    }

    public function test_tenant_tokens_are_refused_on_staff_endpoints(): void
    {
        [$alice, , $invoiceA] = $this->twoResidents();
        Sanctum::actingAs(User::factory()->tenant($alice)->create());

        $this->getJson('/api/guests')->assertForbidden();
        $this->getJson('/api/bookings')->assertForbidden();
        $this->getJson('/api/rooms')->assertForbidden();
        $this->getJson('/api/dashboard')->assertForbidden();
        $this->getJson('/api/inquiries')->assertForbidden();
        $this->postJson("/api/invoices/{$invoiceA->id}/collect", ['amount' => 9000, 'payment_method' => 'cash'])->assertForbidden();

        $this->assertSame(0.0, (float) $invoiceA->refresh()->amount_paid);
    }

    public function test_tenant_only_sees_and_raises_their_own_records(): void
    {
        [$alice, $bob, $invoiceA] = $this->twoResidents();
        Complaint::query()->create(['guest_id' => $bob->id, 'title' => 'Bob AC', 'status' => 'open', 'priority' => 'low']);

        Sanctum::actingAs(User::factory()->tenant($alice)->create());

        $invoices = $this->getJson('/api/invoices?status=all')->assertOk()->json('data');
        $this->assertSame([$invoiceA->invoice_number], array_column($invoices, 'invoice_number'));

        $this->getJson('/api/complaints')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/complaints', ['title' => 'Fan not working', 'guest_id' => $bob->id])->assertCreated();
        $this->assertSame($alice->id, Complaint::query()->where('title', 'Fan not working')->value('guest_id'));
    }

    public function test_unlinked_tenant_login_cannot_list_everyones_invoices(): void
    {
        $this->twoResidents();
        Sanctum::actingAs(User::factory()->create(['role' => 'tenant', 'guest_id' => null]));

        $this->getJson('/api/invoices')->assertForbidden();
    }

    public function test_wardens_get_the_dashboard_without_the_owner_pnl(): void
    {
        $this->twoResidents();

        Sanctum::actingAs(User::factory()->staff()->create());
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('pnl', null);

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->getJson('/api/dashboard')->assertOk()->assertJsonStructure(['pnl' => ['collected', 'expenses', 'profit']]);
    }

    public function test_disabled_login_is_refused_and_login_is_throttled(): void
    {
        $user = User::factory()->staff()->inactive()->create(['email' => 'off@example.com']);

        $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['login' => 'nobody@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['login' => 'nobody@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_only_the_owner_can_delete_ledger_rows(): void
    {
        $this->assertFalse($this->canDeleteInvoicesAs(User::factory()->manager()->create()));
        $this->assertTrue($this->canDeleteInvoicesAs(User::factory()->admin()->create()));
    }

    private function canDeleteInvoicesAs(User $user): bool
    {
        $this->actingAs($user);

        return InvoiceResource::canDeleteAny();
    }

    public function test_tenant_can_log_in_with_their_mobile_number(): void
    {
        $guest = Guest::factory()->create(['phone' => '9876543210']);
        User::factory()->tenant($guest)->create();

        $this->postJson('/api/login', ['login' => '9876543210', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('role', 'tenant');
    }
}

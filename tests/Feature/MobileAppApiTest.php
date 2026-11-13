<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\Guest;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Models\Visitor;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAppApiTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        Property::factory()->create(['billing_cycle_start_day' => 1, 'upi_id' => 'pg@okhdfc']);
        $room = Room::factory()->create(['room_number' => '101', 'sharing_type' => 'single', 'base_rent_per_bed' => 9000]);
        $room->syncBedsToCapacity();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value, 'phone' => '9876543210']);

        $booking = app(BedAllocationService::class)->allocate($guest, $room->beds()->firstOrFail()->id, [
            'check_in_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 9000,
        ]);

        return app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());
    }

    public function test_staff_logs_visitors_in_and_out_from_the_app(): void
    {
        $guest = Guest::factory()->create();
        $warden = User::factory()->staff()->create();
        Sanctum::actingAs($warden);

        $id = $this->postJson('/api/visitors', ['visitor_name' => 'Sunita', 'guest_id' => $guest->id, 'relation' => 'Parent'])
            ->assertCreated()
            ->json('id');

        $this->getJson('/api/visitors?inside=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.meeting', $guest->full_name);

        $this->postJson("/api/visitors/{$id}/checkout")->assertOk();

        $this->assertFalse(Visitor::query()->findOrFail($id)->isInside());
        $this->assertSame($warden->id, Visitor::query()->findOrFail($id)->logged_by);
        $this->getJson('/api/visitors?inside=1')->assertJsonCount(0, 'data');
    }

    public function test_staff_adds_walk_in_leads_from_the_app(): void
    {
        Sanctum::actingAs(User::factory()->staff()->create());

        $this->postJson('/api/inquiries', ['name' => 'Rahul', 'phone' => '9811100000', 'source' => 'justdial', 'budget' => 9000])
            ->assertCreated();
        $this->postJson('/api/inquiries', ['name' => 'X', 'phone' => '1', 'source' => 'not-a-source'])
            ->assertUnprocessable();

        $lead = Inquiry::query()->sole();
        $this->assertSame('justdial', $lead->source);
        $this->assertNotNull($lead->follow_up_date);
    }

    public function test_whatsapp_reminder_link_is_staff_only_and_logged(): void
    {
        $invoice = $this->invoice();

        Sanctum::actingAs(User::factory()->tenant($invoice->booking->guest)->create());
        $this->postJson("/api/invoices/{$invoice->id}/remind")->assertForbidden();

        Sanctum::actingAs(User::factory()->staff()->create());
        $url = $this->postJson("/api/invoices/{$invoice->id}/remind")->assertOk()->json('url');

        $this->assertStringStartsWith('https://wa.me/919876543210?text=', $url);
        $this->assertNotNull($invoice->refresh()->last_reminded_at);
    }

    public function test_invoice_list_carries_upi_uri_and_hides_phone_from_tenants(): void
    {
        $invoice = $this->invoice();

        Sanctum::actingAs(User::factory()->tenant($invoice->booking->guest)->create());
        $row = $this->getJson('/api/invoices')->assertOk()->json('data.0');
        $this->assertNull($row['phone']);
        $this->assertStringStartsWith('upi://pay?pa=pg%40okhdfc', $row['upi_uri']);

        Sanctum::actingAs(User::factory()->staff()->create());
        $this->assertSame('9876543210', $this->getJson('/api/invoices')->json('data.0.phone'));
    }

    public function test_me_reports_role_label_and_finance_access(): void
    {
        Sanctum::actingAs(User::factory()->staff()->create());

        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('role', 'staff')
            ->assertJsonPath('role_label', 'Staff / Warden')
            ->assertJsonPath('can_manage_finance', false);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\KycStatus;
use App\Models\Bed;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'secret',
            'services.razorpay.webhook_secret' => 'whsec',
        ]);
    }

    private function invoice(): Invoice
    {
        Property::factory()->create(['billing_cycle_start_day' => 1]);
        $room = Room::factory()->create(['room_number' => '101', 'sharing_type' => 'single', 'base_rent_per_bed' => 9000]);
        $room->syncBedsToCapacity();

        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value, 'phone' => '9876543210']);
        $booking = app(BedAllocationService::class)->allocate($guest, Bed::query()->firstOrFail()->id, [
            'check_in_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 9000,
        ]);

        return app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * @return array<string, mixed>
     */
    private function paidEvent(Invoice $invoice, string $paymentId, int $paise): array
    {
        return [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['id' => 'plink_123', 'notes' => ['invoice_id' => (string) $invoice->id]]],
                'payment' => ['entity' => ['id' => $paymentId, 'amount' => $paise, 'method' => 'upi']],
            ],
        ];
    }

    private function postSigned(array $event, ?string $signature = null): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'whsec'),
        ], $body);
    }

    public function test_tenant_gets_a_payment_link_for_their_balance(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['id' => 'plink_123', 'short_url' => 'https://rzp.io/i/abc'])]);
        $invoice = $this->invoice();

        Sanctum::actingAs(User::factory()->tenant($invoice->booking->guest)->create());

        $this->postJson("/api/invoices/{$invoice->id}/pay-link")
            ->assertOk()
            ->assertJsonPath('url', 'https://rzp.io/i/abc');

        Http::assertSent(fn (Request $request): bool => $request['amount'] === (int) round($invoice->balanceDue() * 100)
            && $request['currency'] === 'INR'
            && $request['customer']['contact'] === '+919876543210');

        $this->assertSame('plink_123', $invoice->refresh()->payment_link_id);
    }

    public function test_tenant_cannot_request_a_link_for_someone_elses_invoice(): void
    {
        Http::fake();
        $invoice = $this->invoice();

        Sanctum::actingAs(User::factory()->tenant()->create());

        $this->postJson("/api/invoices/{$invoice->id}/pay-link")->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_webhook_rejects_unsigned_requests(): void
    {
        $invoice = $this->invoice();

        $this->postSigned($this->paidEvent($invoice, 'pay_1', 900000), 'forged')->assertUnauthorized();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_paid_webhook_settles_the_invoice_exactly_once(): void
    {
        $invoice = $this->invoice();
        $paise = (int) round($invoice->balanceDue() * 100);
        $event = $this->paidEvent($invoice, 'pay_ABC', $paise);

        $this->postSigned($event)->assertOk()->assertJsonPath('posted', true);
        $this->postSigned($event)->assertOk()->assertJsonPath('posted', false);

        $invoice->refresh();
        $this->assertSame(1, Payment::query()->where('transaction_id', 'pay_ABC')->count());
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(0.0, $invoice->balanceDue());
    }

    public function test_pay_link_is_refused_when_gateway_is_not_configured(): void
    {
        config(['services.razorpay.key_id' => null]);
        $invoice = $this->invoice();

        Sanctum::actingAs(User::factory()->tenant($invoice->booking->guest)->create());

        $this->postJson("/api/invoices/{$invoice->id}/pay-link")->assertUnprocessable();
    }
}

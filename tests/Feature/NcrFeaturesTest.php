<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Enums\PoliceVerificationStatus;
use App\Filament\Widgets\AttentionNeededWidget;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Models\Visitor;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use App\Services\RentReceiptService;
use App\Services\ReportService;
use App\Support\IndianCurrency;
use App\Support\Ncr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class NcrFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function resident(array $guest = [], array $booking = [], array $room = []): Booking
    {
        if (Property::query()->doesntExist()) {
            Property::factory()->create(['billing_cycle_start_day' => 1, 'upi_id' => 'pg@okhdfc', 'city' => 'Noida']);
        }

        $roomModel = Room::factory()->create(array_merge([
            'room_number' => (string) fake()->unique()->numberBetween(100, 999),
            'sharing_type' => 'single',
            'base_rent_per_bed' => 9000,
        ], $room));
        $roomModel->syncBedsToCapacity();

        $guestModel = Guest::factory()->create(array_merge(['kyc_status' => KycStatus::Verified->value], $guest));

        return app(BedAllocationService::class)->allocate($guestModel, $roomModel->beds()->firstOrFail()->id, array_merge([
            'check_in_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 9000,
        ], $booking));
    }

    public function test_hotel_gst_follows_the_gst_2_slabs(): void
    {
        $this->assertSame(0.0, Ncr::hotelGstPercent(0));
        $this->assertSame(5.0, Ncr::hotelGstPercent(1499));
        $this->assertSame(5.0, Ncr::hotelGstPercent(7500));
        $this->assertSame(18.0, Ncr::hotelGstPercent(7501));
    }

    public function test_hotel_stay_invoice_charges_five_percent_on_room_tariff(): void
    {
        $booking = $this->resident(booking: [
            'stay_type' => 'hotel',
            'nightly_rate' => 1499,
            'check_in_date' => '2026-10-01',
            'expected_check_out_date' => '2026-10-04',
        ]);

        $invoice = app(InvoiceService::class)->generateForBooking($booking, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'));

        $this->assertSame(5.0, (float) $invoice->gst_percent);
        $this->assertEqualsWithDelta((float) $invoice->rent_amount * 0.05, (float) $invoice->gst_amount, 0.01);
    }

    public function test_ncr_cities_resolve_aliases_state_and_police_portal(): void
    {
        $this->assertSame('Gurugram', Ncr::normaliseCity('Gurgaon'));
        $this->assertSame('Delhi', Ncr::normaliseCity('New Delhi'));
        $this->assertSame('Uttar Pradesh', Ncr::stateFor('Noida'));
        $this->assertStringContainsString('Harsamay', Ncr::policePortalFor('Faridabad')['name']);
        $this->assertArrayHasKey('Laxmi Nagar', Ncr::localityOptions('Delhi'));
        $this->assertArrayNotHasKey('Laxmi Nagar', Ncr::localityOptions('Noida'));
    }

    public function test_indian_currency_formatting_and_words(): void
    {
        $this->assertSame('₹1,25,000.00', IndianCurrency::format(125000));
        $this->assertSame('₹12,34,56,789', IndianCurrency::format(123456789, 0));
        $this->assertSame('₹999.50', IndianCurrency::format(999.5));
        $this->assertSame('One Lakh Twenty Five Thousand Rupees Only', IndianCurrency::inWords(125000));
        $this->assertSame('Nine Thousand Five Hundred Rupees and Fifty Paise Only', IndianCurrency::inWords(9500.50));
    }

    public function test_hra_receipts_cover_paid_rent_in_the_financial_year_only(): void
    {
        $booking = $this->resident(booking: ['check_in_date' => '2026-03-01']);
        $service = app(InvoiceService::class);

        $march = $service->generateForBooking($booking, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));
        $april = $service->generateForBooking($booking, Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));
        $may = $service->generateForBooking($booking, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'));

        foreach ([$march, $april] as $invoice) {
            $service->recordPayment($invoice, ['amount' => $invoice->balanceDue(), 'payment_method' => 'upi', 'transaction_id' => 'UTR'.$invoice->id]);
        }
        $service->recordPayment($may, ['amount' => 4000, 'payment_method' => 'cash']);

        $data = app(RentReceiptService::class)->forGuest($booking->guest, 2026);

        $this->assertSame('2026-27', $data['fy']);
        $this->assertSame(['April 2026', 'May 2026'], array_column($data['receipts'], 'month'));
        $this->assertSame(9000.0 + 4000.0, $data['total']);
        $this->assertFalse($data['needs_landlord_pan']);
    }

    public function test_rent_receipt_page_renders_for_staff(): void
    {
        $booking = $this->resident();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('guests.rent-receipts', $booking->guest))
            ->assertOk()
            ->assertSee('Rent Receipts FY');
    }

    public function test_dues_aging_buckets_by_days_late(): void
    {
        $booking = $this->resident();
        $invoice = app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());
        $invoice->forceFill(['due_date' => now()->subDays(45)->toDateString()])->save();

        $totals = app(ReportService::class)->duesAgingTotals();

        $this->assertSame($invoice->balanceDue(), $totals['d60']);
        $this->assertSame($invoice->balanceDue(), $totals['total']);
        $this->assertSame(0.0, $totals['d30']);
    }

    public function test_reports_download_as_csv_and_neutralise_formulas(): void
    {
        $this->resident(guest: ['full_name' => '=HYPERLINK("http://evil")']);

        $response = $this->actingAs(User::factory()->manager()->create())
            ->get(route('reports.download', 'rent-roll'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Monthly rent', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        foreach (array_keys(ReportService::catalogue()) as $report) {
            $this->get(route('reports.download', ['report' => $report, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))->assertOk();
        }

        $this->get(route('reports.download', 'unknown'))->assertNotFound();
    }

    public function test_public_page_shows_live_availability_and_captures_enquiries(): void
    {
        $this->resident();
        Room::factory()->create(['room_number' => '900', 'sharing_type' => 'double', 'base_rent_per_bed' => 11000])->syncBedsToCapacity();

        $this->get(route('stay.show'))
            ->assertOk()
            ->assertSee('2 bed(s) available right now')
            ->assertSee('₹11,000');

        $payload = ['name' => 'Ananya', 'phone' => '+91 98111 22233', 'gender' => 'female', 'message' => 'Near Sector 62 office'];

        $this->post(route('stay.enquire'), $payload)->assertRedirect(route('stay.show'));
        $this->post(route('stay.enquire'), $payload + ['budget' => 12000])->assertRedirect(route('stay.show'));

        $this->assertSame(1, Inquiry::query()->count());
        $inquiry = Inquiry::query()->firstOrFail();
        $this->assertSame('9811122233', $inquiry->phone);
        $this->assertSame('website', $inquiry->source);
        $this->assertSame(12000.0, (float) $inquiry->budget);
    }

    public function test_public_enquiry_rejects_bots_and_bad_numbers(): void
    {
        Property::factory()->create();

        $this->post(route('stay.enquire'), ['name' => 'Bot', 'phone' => '9811122233', 'gender' => 'male', 'website' => 'spam.example'])
            ->assertRedirect(route('stay.show'));
        $this->post(route('stay.enquire'), ['name' => 'Typo', 'phone' => '12345', 'gender' => 'male'])
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_public_page_can_be_switched_off(): void
    {
        Property::factory()->create(['public_listing' => false]);

        $this->get(route('stay.show'))->assertNotFound();
    }

    public function test_whatsapp_reminder_logs_touchpoint_and_opens_chat(): void
    {
        $booking = $this->resident(guest: ['phone' => '9876543210']);
        $invoice = app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());

        $response = $this->actingAs(User::factory()->staff()->create())->get(route('invoices.whatsapp', $invoice));

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/919876543210?text=', $target);
        $this->assertStringContainsString('pg%40okhdfc', $target);
        $this->assertNotNull($invoice->refresh()->last_reminded_at);
    }

    public function test_printed_invoice_title_matches_tax_status_and_offers_upi_qr(): void
    {
        $booking = $this->resident();
        $invoice = app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('RENT INVOICE')
            ->assertDontSee('TAX INVOICE')
            ->assertSee('upi://pay?pa=pg%40okhdfc', false)
            ->assertSee('Rupees');
    }

    public function test_police_verification_scope_counts_only_residents_staying(): void
    {
        $this->resident(guest: ['police_verification_status' => PoliceVerificationStatus::NotSubmitted->value]);
        $this->resident(guest: ['police_verification_status' => PoliceVerificationStatus::Verified->value]);
        Guest::factory()->create(['police_verification_status' => PoliceVerificationStatus::NotSubmitted->value]);

        $this->assertSame(1, Guest::query()->staying()->policeVerificationPending()->count());
    }

    public function test_new_admin_screens_render(): void
    {
        $booking = $this->resident();
        Visitor::factory()->create(['guest_id' => $booking->guest_id]);
        $bed = Bed::query()->firstOrFail();

        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
        Livewire::test(AttentionNeededWidget::class)
            ->assertSee('Police verification pending')
            ->assertSee('Visitors inside');
        $this->get('/admin/bed-board')->assertOk()->assertSee($bed->bed_code);
        $this->get('/admin/visitors')->assertOk();
        $this->get('/admin/reports')->assertOk()->assertSee('Dues aging');
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/guests')->assertOk();
        $this->get('/admin/invoices')->assertOk();
        $this->get('/admin/properties/'.Property::query()->value('id').'/edit')->assertOk();
    }

    public function test_api_exposes_upi_uri_and_visitor_register(): void
    {
        $booking = $this->resident();
        app(InvoiceService::class)->generateForBooking($booking, now()->startOfMonth(), now()->endOfMonth());
        Visitor::factory()->create(['guest_id' => $booking->guest_id]);

        Sanctum::actingAs(User::factory()->tenant($booking->guest)->create());
        $this->getJson('/api/me/stay')->assertOk()->assertJsonPath('online_payments', false)
            ->assertJson(fn ($json) => $json->where('due.upi_uri', fn (?string $uri): bool => str_starts_with((string) $uri, 'upi://pay?pa=pg%40okhdfc'))->etc());

        Sanctum::actingAs(User::factory()->staff()->create());
        $this->getJson('/api/visitors?inside=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_invoice_number_sequence_is_unaffected(): void
    {
        $this->assertStringStartsWith('BZ-', Invoice::nextNumber());
    }
}

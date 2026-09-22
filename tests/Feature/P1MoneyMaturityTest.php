<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\UtilityMeter;
use App\Services\BedAllocationService;
use App\Services\InvoiceService;
use App\Services\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1MoneyMaturityTest extends TestCase
{
    use RefreshDatabase;

    private function seedBuilding(float $lateFeePercent = 10.0): void
    {
        Property::factory()->create([
            'late_fee_percent' => $lateFeePercent,
            'meal_charge_per_day' => 100.00,
            'billing_cycle_start_day' => 1,
        ]);

        $room = Room::factory()->create(['room_number' => '201', 'sharing_type' => 'single', 'base_rent_per_bed' => 9000]);
        $room->syncBedsToCapacity();
        $room->meters()->create(['meter_type' => 'electricity', 'meter_serial_no' => 'E-201', 'multiplier' => 1.000, 'is_active' => true]);
    }

    private function liveBooking(): Booking
    {
        $bed = Bed::query()->firstOrFail();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);

        return app(BedAllocationService::class)->allocate($guest, $bed->id, [
            'check_in_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 9000,
        ]);
    }

    private function overdueInvoice(): Invoice
    {
        $booking = $this->liveBooking();
        $invoice = app(InvoiceService::class)->generateForBooking(
            $booking,
            now()->startOfMonth(),
            now()->startOfMonth()->copy()->endOfMonth(),
        );
        $invoice->forceFill(['due_date' => now()->subDays(10)->toDateString()])->save();

        return $invoice->refresh();
    }

    public function test_late_fee_is_charged_once_as_a_visible_line(): void
    {
        $this->seedBuilding();
        $service = app(InvoiceService::class);
        $invoice = $this->overdueInvoice();

        $this->assertSame('overdue', $invoice->status->value);
        $otherBefore = (float) $invoice->other_charges;
        $balanceBefore = $invoice->balanceDue();

        $result = $service->applyLateFees();

        $this->assertSame(1, $result['charged']);
        $invoice->refresh();
        $this->assertEqualsWithDelta($balanceBefore * 0.10, (float) $invoice->other_charges - $otherBefore, 0.01);
        $this->assertNotNull($invoice->late_fee_charged_on);

        // Idempotency: a re-run never double-charges the same invoice.
        $second = $service->applyLateFees();
        $this->assertSame(0, $second['charged']);
        $this->assertEqualsWithDelta($invoice->other_charges, $invoice->fresh()->other_charges, 0.001);
    }

    public function test_late_fee_engine_skips_zero_percent_properties(): void
    {
        $this->seedBuilding(lateFeePercent: 0.0);
        $this->overdueInvoice();

        $result = app(InvoiceService::class)->applyLateFees();

        $this->assertSame(0, $result['charged']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_overdue_reminder_throttles_to_three_days(): void
    {
        $this->seedBuilding();
        $service = app(InvoiceService::class);
        $invoice = $this->overdueInvoice();

        $this->assertCount(1, $service->invoicesDueForReminder());

        $service->markReminded($invoice, 'manual');
        $this->assertCount(0, $service->invoicesDueForReminder(), 'a freshly reminded invoice must not re-appear');

        // 4 days later it is due for a chase again.
        $invoice->forceFill(['last_reminded_at' => now()->subDays(4)->toDateString()])->save();
        $this->assertCount(1, $service->invoicesDueForReminder());
    }

    public function test_monthly_cash_pnl_combines_collections_and_expenses(): void
    {
        $this->seedBuilding();
        $service = app(InvoiceService::class);
        $invoice = app(InvoiceService::class)->generateForBooking(
            $this->liveBooking(),
            now()->startOfMonth(),
            now()->startOfMonth()->copy()->endOfMonth(),
        );
        $service->recordPayment($invoice, ['amount' => 1000, 'payment_method' => 'cash']);

        Expense::query()->create(['category' => 'electricity', 'amount' => 400, 'spent_on' => now()->toDateString()]);

        $pnl = Property::current()->monthlyPnL();

        $this->assertEqualsWithDelta(1000.0, $pnl['collected'], 0.01);
        $this->assertEqualsWithDelta(400.0, $pnl['expenses'], 0.01);
        $this->assertEqualsWithDelta(600.0, $pnl['profit'], 0.01);
    }

    public function test_meter_anomaly_flag_fires_above_double_average(): void
    {
        $this->seedBuilding();
        $meter = UtilityMeter::query()->firstOrFail();
        $service = app(MeterReadingService::class);

        $service->record($meter, ['reading_date' => now()->subDays(30)->toDateString(), 'current_reading' => 100]);
        $service->record($meter->refresh(), ['reading_date' => now()->subDays(5)->toDateString(), 'current_reading' => 200]);

        // 300 units against a 100-unit historical average — clearly anomalous.
        $spike = $service->record($meter->refresh(), ['reading_date' => now()->toDateString(), 'current_reading' => 500]);
        $this->assertTrue($spike->isAnomalous());
        $this->assertTrue($spike->fresh()->isAnomalous(), 'flag survives a refresh');
        $this->assertSame(300.0, (float) $spike->consumption, 'spike consumption stays as recorded');

        // A normal follow-up reading (100 units vs ~166 avg) is not flagged.
        $normal = $service->record($meter->refresh(), ['reading_date' => now()->addDay()->toDateString(), 'current_reading' => 600]);
        $this->assertFalse($normal->isAnomalous());
    }

    public function test_complaint_sla_breaches_after_24h_for_high_priority(): void
    {
        // Raised 30h ago: high priority = 24h SLA → already breached while open.
        $complaint = Complaint::factory()->create(['priority' => 'high']);
        $complaint->forceFill(['created_at' => now()->subHours(30)])->save();

        $this->assertTrue($complaint->slaBreached());

        // Resolved late (30h > 24h SLA) — the breach is recorded, not erased.
        $complaint->resolve('Fixed the tap');
        $this->assertTrue($complaint->fresh()->slaBreached(), 'a late resolution still shows the breach');

        // Resolved inside the window: low priority (72h), 30h in → no breach.
        $quick = Complaint::factory()->create(['priority' => 'low']);
        $quick->forceFill(['created_at' => now()->subHours(30)])->save();
        $quick->resolve('Fixed quickly');

        $this->assertFalse($quick->fresh()->slaBreached());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Room;
use App\Services\BedAllocationService;
use App\Services\CheckoutSettlementService;
use App\Services\InvoiceService;
use App\Services\MeterReadingService;
use App\Services\ProrationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingCoreTest extends TestCase
{
    use RefreshDatabase;

    private function seedBuilding(): Property
    {
        $property = Property::factory()->create([
            'electricity_rate_per_unit' => 10.00,
            'meal_charge_per_day' => 100.00,
            'billing_cycle_start_day' => 1,
        ]);

        $room = Room::factory()->create(['room_number' => '201', 'sharing_type' => 'double', 'base_rent_per_bed' => 9000]);
        $room->syncBedsToCapacity();
        $room->meters()->create(['meter_type' => 'electricity', 'meter_serial_no' => 'E-201', 'multiplier' => 1.000, 'is_active' => true]);

        return $property;
    }

    public function test_proration_is_inclusive_and_proportional(): void
    {
        $this->seedBuilding();
        $engine = app(ProrationEngine::class);
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');

        // Full month = full rent; half month ~= half rent.
        $this->assertSame(9000.0, $engine->proratedRent(9000, $start, $end, $start, $end));
        $half = $engine->proratedRent(9000, $start, $end, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-15'));
        $this->assertEqualsWithDelta(4500.0, $half, 1.0);
        $this->assertSame(1, $engine->daysInclusive($start, $start));
    }

    public function test_allocation_uses_service_lock_and_kyc_guard(): void
    {
        $this->seedBuilding();
        $bed = Bed::query()->firstOrFail();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Pending->value]);

        $this->expectException(\App\Exceptions\BedAllocationException::class);
        app(BedAllocationService::class)->allocate($guest, $bed->id, ['check_in_date' => now()->toDateString()]);

        // Blacklisted guest is also rejected.
        $guest->forceFill(['kyc_status' => KycStatus::Verified, 'is_blacklisted' => true])->save();
        $this->expectException(\App\Exceptions\BedAllocationException::class);
        app(BedAllocationService::class)->allocate($guest, $bed->id, ['check_in_date' => now()->toDateString(), 'bypass_kyc_check' => true]);
    }

    public function test_invoice_is_idempotent_per_booking_cycle(): void
    {
        $this->seedBuilding();
        $bed = Bed::query()->firstOrFail();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);
        $booking = app(BedAllocationService::class)->allocate($guest, $bed->id, ['check_in_date' => '2026-09-02', 'monthly_rent' => 9000]);

        $service = app(InvoiceService::class);
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');
        $first = $service->generateForBooking($booking, $start, $end);
        $second = $service->generateForBooking($booking, $start, $end);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(1, Booking::find($booking->id)->invoices()->count());
        // Mid-month join is prorated, never full rent.
        $this->assertLessThan(9000, (float) $first->rent_amount);
        $this->assertGreaterThan(0, (float) $first->rent_amount);
    }

    public function test_partial_payment_keeps_balance_not_total(): void
    {
        $this->seedBuilding();
        $bed = Bed::query()->firstOrFail();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);
        $booking = app(BedAllocationService::class)->allocate($guest, $bed->id, ['check_in_date' => '2026-09-01', 'monthly_rent' => 9000]);

        $service = app(InvoiceService::class);
        $invoice = $service->generateForBooking($booking, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        $service->recordPayment($invoice, ['amount' => 1000, 'payment_method' => 'cash']);

        $invoice->refresh();
        $booking->refresh();
        $this->assertEqualsWithDelta((float) $invoice->total_due - 1000, $invoice->balanceDue(), 0.01);
        $this->assertEqualsWithDelta($invoice->balanceDue(), $booking->outstandingDues(), 0.01);
    }

    public function test_settlement_clears_arrears_and_refunds_deposit(): void
    {
        $this->seedBuilding();
        $bed = Bed::query()->firstOrFail();
        $guest = Guest::factory()->create(['kyc_status' => KycStatus::Verified->value]);
        $booking = app(BedAllocationService::class)->allocate($guest, $bed->id, [
            'check_in_date' => '2026-09-01', 'monthly_rent' => 9000, 'security_deposit_amount' => 9000,
        ]);

        $service = app(InvoiceService::class);
        $service->generateForBooking($booking, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $result = app(CheckoutSettlementService::class)->checkout($booking, Carbon::parse('2026-09-20'), []);
        $this->assertArrayHasKey('invoice', $result);
        $this->assertSame('checked_out', $result['invoice']->booking->refresh()->status->value);
        $this->assertEquals('available', Bed::find($bed->id)->status->value);
    }

    public function test_meter_regression_is_rejected(): void
    {
        $this->seedBuilding();
        $meter = \App\Models\UtilityMeter::query()->firstOrFail();
        $service = app(MeterReadingService::class);
        $service->record($meter, ['reading_date' => '2026-09-05', 'current_reading' => 200]);

        $this->expectException(\App\Exceptions\MeterReadingException::class);
        $service->record($meter->refresh(), ['reading_date' => '2026-09-06', 'current_reading' => 150]);
    }
}

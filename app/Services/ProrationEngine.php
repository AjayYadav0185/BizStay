<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Support\Carbon;

/**
 * Prorated rent engine.
 *
 * Every monetary rule about partial months lives here — nothing else in the
 * codebase divides rent by days. Cycles are anchored to the building's
 * billing_cycle_start_day (1st of the month by default) and all day counts are
 * inclusive of both ends, which is how Indian PG rent is quoted and settled.
 */
final class ProrationEngine
{
    /**
     * Inclusive day count between two dates (15th to 15th = 1 day).
     */
    public function daysInclusive(Carbon $from, Carbon $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    /**
     * The billing window containing the given date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function cycleContaining(Carbon $on, ?int $startDay = null): array
    {
        $startDay ??= Property::current()?->billing_cycle_start_day ?? 1;
        // Clamp to 28 so every month has the anchor day.
        $startDay = max(1, min(28, $startDay));

        $on = $on->copy()->startOfDay();

        $start = $on->day >= $startDay
            ? $on->copy()->setDay($startDay)
            : $on->copy()->subMonthNoOverflow()->setDay($startDay);

        $end = $start->copy()->addMonthNoOverflow()->setDay($startDay)->subDay();

        return [$start->startOfDay(), $end->startOfDay()];
    }

    /**
     * The cycle that applies to a booking right now (or on a given date).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function cycleFor(Booking $booking, ?Carbon $on = null): array
    {
        $on ??= now();

        // A brand-new stay starts its own first cycle on the check-in date.
        if ($booking->check_in_date->isSameMonth($on) && (int) $booking->check_in_date->day > 1) {
            $on = $booking->check_in_date->copy();
        }

        return $this->cycleContaining($on);
    }

    /**
     * First billable cycle of a booking: the natural cycle clamped to the
     * check-in date so the guest is never billed for days before they arrived.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function firstCycleFor(Booking $booking): array
    {
        [$cycleStart, $cycleEnd] = $this->cycleContaining($booking->check_in_date);

        return [
            $booking->check_in_date->greaterThan($cycleStart) ? $booking->check_in_date->copy()->startOfDay() : $cycleStart,
            $cycleEnd,
        ];
    }

    /**
     * Prorated rent for an occupancy window inside a billing cycle.
     *
     * Monthly rent is divided by the number of days in the cycle and multiplied
     * by the days the guest actually occupied the bed (both inclusive).
     */
    public function proratedRent(
        float $monthlyRent,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        Carbon $occupancyStart,
        Carbon $occupancyEnd,
    ): float {
        $cycleDays = $this->daysInclusive($cycleStart, $cycleEnd);

        if ($cycleDays <= 0) {
            return 0.0;
        }

        $occupiedDays = $this->overlapDays($cycleStart, $cycleEnd, $occupancyStart, $occupancyEnd);

        if ($occupiedDays <= 0) {
            return 0.0;
        }

        return round($monthlyRent / $cycleDays * $occupiedDays, 2);
    }

    /**
     * Prorated rent straight off a booking using a supplied cycle.
     */
    public function proratedRentForBooking(
        Booking $booking,
        Carbon $occupancyStart,
        Carbon $occupancyEnd,
        ?Carbon $cycleStart = null,
        ?Carbon $cycleEnd = null,
    ): float {
        [$defaultStart, $defaultEnd] = $this->cycleContaining($occupancyStart);

        return $this->proratedRent(
            (float) $booking->monthly_rent,
            $cycleStart ?? $defaultStart,
            $cycleEnd ?? $defaultEnd,
            $occupancyStart,
            $occupancyEnd,
        );
    }

    /**
     * Daily rent fraction (exposed for quick-entry screens and statements).
     */
    public function dailyRent(float $monthlyRent, Carbon $cycleStart, Carbon $cycleEnd): float
    {
        $cycleDays = $this->daysInclusive($cycleStart, $cycleEnd);

        return $cycleDays <= 0 ? 0.0 : round($monthlyRent / $cycleDays, 2);
    }

    /**
     * Inclusive days shared by two windows; 0 when they do not touch.
     */
    public function overlapDays(Carbon $windowStart, Carbon $windowEnd, Carbon $from, Carbon $to): int
    {
        if ($from->greaterThan($windowEnd) || $to->lessThan($windowStart)) {
            return 0;
        }

        $start = $from->greaterThan($windowStart) ? $from->copy() : $windowStart->copy();
        $end = $to->lessThan($windowEnd) ? $to->copy() : $windowEnd->copy();

        return $this->daysInclusive($start, $end);
    }

    /**
     * Mess/food credit for approved leave days inside a cycle.
     */
    public function mealDeduction(Booking $booking, Carbon $cycleStart, Carbon $cycleEnd): float
    {
        if (! $booking->food_included) {
            return 0.0;
        }

        $perDay = (float) (Property::current()?->meal_charge_per_day ?? 0);

        return round($this->mealOptOutDays($booking, $cycleStart, $cycleEnd) * $perDay, 2);
    }

    /**
     * Days of food the guest opted out of inside a window (for statements).
     */
    public function mealOptOutDays(Booking $booking, Carbon $cycleStart, Carbon $cycleEnd): int
    {
        return $booking->food_included
            ? $booking->foodOptOutDays($cycleStart, $cycleEnd)
            : 0;
    }

    /**
     * Maintenance charge for the bed, prorated like rent for partial cycles.
     */
    public function proratedMaintenance(
        Carbon $cycleStart,
        Carbon $cycleEnd,
        Carbon $occupancyStart,
        Carbon $occupancyEnd,
    ): float {
        $perBed = (float) (Property::current()?->maintenance_charge_per_bed ?? 0);

        if ($perBed <= 0.0) {
            return 0.0;
        }

        $cycleDays = $this->daysInclusive($cycleStart, $cycleEnd);
        $occupiedDays = $this->overlapDays($cycleStart, $cycleEnd, $occupancyStart, $occupancyEnd);

        if ($cycleDays <= 0 || $occupiedDays <= 0) {
            return 0.0;
        }

        return round($perBed / $cycleDays * $occupiedDays, 2);
    }

    /**
     * Late fee on an overdue amount, per the property's configured percentage.
     */
    public function lateFee(float $outstanding, ?float $percent = null): float
    {
        $percent ??= (float) (Property::current()?->late_fee_percent ?? 0);

        return $percent <= 0.0 ? 0.0 : round($outstanding * $percent / 100, 2);
    }

    /**
     * Rent due for the cycle that contains the check-out date, up to that date.
     */
    public function checkoutRent(Booking $booking, Carbon $checkoutDate): float
    {
        [$cycleStart, $cycleEnd] = $this->cycleContaining($checkoutDate);

        return $this->proratedRent(
            (float) $booking->monthly_rent,
            $cycleStart,
            $cycleEnd,
            $booking->check_in_date->lessThan($cycleStart) ? $cycleStart : $booking->check_in_date,
            $checkoutDate,
        );
    }
}

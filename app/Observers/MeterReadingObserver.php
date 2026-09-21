<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MeterReading;
use App\Models\UtilityMeter;

/**
 * Derives consumption and money from the meter itself. Managers only ever type
 * the dial reading; multiplier, previous value and tariff are applied here so a
 * mistyped delta can never inflate a bill.
 */
final class MeterReadingObserver
{
    public function saving(MeterReading $reading): void
    {
        $meter = $reading->meter ?? UtilityMeter::query()->find($reading->meter_id);

        if (! $meter) {
            return;
        }

        $previous = $reading->previous_reading === null
            ? $meter->latestReadingValue()
            : (float) $reading->previous_reading;

        $rate = $reading->rate_per_unit === null
            ? $meter->resolveRate()
            : (float) $reading->rate_per_unit;

        $consumption = MeterReading::consumptionFor(
            $previous,
            (float) $reading->current_reading,
            (float) $meter->multiplier,
        );

        $reading->previous_reading = $previous;
        $reading->rate_per_unit = $rate;
        $reading->consumption = $consumption;
        $reading->amount = MeterReading::amountFor($consumption, $rate);
    }
}

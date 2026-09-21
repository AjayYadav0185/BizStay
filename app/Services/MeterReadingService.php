<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeterType;
use App\Exceptions\MeterReadingException;
use App\Models\MeterReading;
use App\Models\UtilityMeter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Captures physical meter readings.
 *
 * Every write happens inside a transaction with the meter row locked, so two
 * managers entering the same floor simultaneously cannot compute their
 * consumption from the same "previous" value. Consumption and money are always
 * computed by MeterReadingObserver from the locked previous value.
 */
final class MeterReadingService
{
    /**
     * Records one reading with the strict guards (regression + duplicates).
     *
     * @param  array{reading_date: string, current_reading: float|string, notes?: string|null}  $data
     */
    public function record(UtilityMeter $meter, array $data, ?int $recordedBy = null): MeterReading
    {
        return DB::transaction(function () use ($meter, $data, $recordedBy): MeterReading {
            $locked = UtilityMeter::query()->lockForUpdate()->findOrFail($meter->id);

            if (! $locked->is_active) {
                throw MeterReadingException::inactiveMeter($locked->display_label);
            }

            $readingDate = Carbon::parse($data['reading_date']);

            $exists = MeterReading::query()
                ->where('meter_id', $locked->id)
                ->whereDate('reading_date', $readingDate->toDateString())
                ->exists();

            if ($exists) {
                throw MeterReadingException::duplicateEntry($locked->display_label, $readingDate->toDateString());
            }

            $previous = $locked->latestReadingValue();
            $current = (float) $data['current_reading'];

            if ($current < $previous) {
                throw MeterReadingException::regression($locked->display_label, $previous, $current);
            }

            return $locked->readings()->create([
                'reading_date' => $readingDate->toDateString(),
                'previous_reading' => $previous,
                'current_reading' => $current,
                'consumption' => 0,
                'rate_per_unit' => 0,
                'amount' => 0,
                'recorded_by' => $recordedBy ?? auth()->id(),
                'notes' => $data['notes'] ?? null,
            ])->refresh();
        });
    }

    /**
     * Bulk floor entry: the manager walks a floor once and types every dial.
     *
     * Re-running the same date is idempotent — an existing row for that meter
     * and date is corrected (typo fix) rather than duplicated. Blank readings
     * are skipped so a partially filled screen is still useful.
     *
     * @param  array<int, array{meter_id: int|string, current_reading: float|string|null}>  $rows
     * @return Collection<int, MeterReading>
     */
    public function recordBulk(array $rows, string|Carbon $readingDate, ?int $recordedBy = null): Collection
    {
        $date = $readingDate instanceof Carbon ? $readingDate : Carbon::parse($readingDate);

        return DB::transaction(function () use ($rows, $date, $recordedBy): Collection {
            $readings = new Collection;

            foreach ($rows as $row) {
                if (! isset($row['meter_id']) || blank($row['current_reading'] ?? null)) {
                    continue;
                }

                $meterId = (int) $row['meter_id'];
                $current = (float) $row['current_reading'];

                $meter = UtilityMeter::query()->lockForUpdate()->find($meterId);

                if (! $meter || ! $meter->is_active) {
                    continue;
                }

                $existing = MeterReading::query()
                    ->where('meter_id', $meter->id)
                    ->whereDate('reading_date', $date->toDateString())
                    ->first();

                if ($existing) {
                    // Correction path: keep the locked previous value untouched.
                    $existing->current_reading = $current;
                    $existing->recorded_by = $recordedBy ?? auth()->id();
                    $existing->save();

                    $readings->push($existing->refresh());

                    continue;
                }

                $previous = $meter->latestReadingValue();

                if ($current < $previous) {
                    throw MeterReadingException::regression($meter->display_label, $previous, $current);
                }

                $readings->push($meter->readings()->create([
                    'reading_date' => $date->toDateString(),
                    'previous_reading' => $previous,
                    'current_reading' => $current,
                    'consumption' => 0,
                    'rate_per_unit' => 0,
                    'amount' => 0,
                    'recorded_by' => $recordedBy ?? auth()->id(),
                ])->refresh());
            }

            return $readings;
        });
    }

    /**
     * Active meters on a floor for a utility — the rows of the bulk entry grid.
     *
     * @return Collection<int, UtilityMeter>
     */
    public function metersOnFloor(int $floor, ?MeterType $type = null): Collection
    {
        return UtilityMeter::query()
            ->active()
            ->when($type, fn ($query) => $query->ofType($type))
            ->whereHas('room', fn ($query) => $query->where('floor_no', $floor))
            ->with('room')
            ->get()
            ->sortBy(fn (UtilityMeter $meter): string => (string) $meter->room?->room_number)
            ->values();
    }

    /**
     * Floors that actually have meters installed (bulk entry selector).
     *
     * @return array<int, string>
     */
    public function floorsWithMeters(?MeterType $type = null): array
    {
        return UtilityMeter::query()
            ->active()
            ->when($type, fn ($query) => $query->ofType($type))
            ->join('rooms', 'rooms.id', '=', 'utility_meters.room_id')
            ->distinct()
            ->orderBy('rooms.floor_no')
            ->pluck('rooms.floor_no')
            ->mapWithKeys(fn ($floor): array => [(int) $floor => (int) $floor === 0 ? 'Ground Floor' : 'Floor '.$floor])
            ->all();
    }

    /**
     * Reads a meter back for the grid: previous value + last entry, so the
     * manager can see what they typed last month while walking the floor.
     *
     * @return array{meter: UtilityMeter, previous_reading: float, last_reading: MeterReading|null, rate: float}
     */
    public function contextFor(UtilityMeter $meter): array
    {
        $last = $meter->latestReading();

        return [
            'meter' => $meter,
            'previous_reading' => (float) ($last?->current_reading ?? 0),
            'last_reading' => $last,
            'rate' => $meter->resolveRate(),
        ];
    }
}
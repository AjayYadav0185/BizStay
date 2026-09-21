<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MeterType;
use App\Models\UtilityMeter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Backing validation for the bulk meter entry wizard.
 *
 * Beyond shape, it enforces the physical rules on the server:
 *  - every meter belongs to the selected floor and utility;
 *  - no reading may be lower than that meter's previous value.
 */
final class BulkMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'floor_no' => ['required', 'integer', 'min:0', 'max:50'],
            'meter_type' => ['required', Rule::enum(MeterType::class)],
            'reading_date' => ['required', 'date', 'before_or_equal:today'],
            'readings' => ['required', 'array', 'min:1'],
            'readings.*.meter_id' => ['required', 'integer', 'exists:utility_meters,id'],
            'readings.*.current_reading' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $rows = (array) $this->input('readings', []);
                $floor = (int) $this->input('floor_no');
                $type = $this->input('meter_type');

                $meters = UtilityMeter::query()
                    ->with('room')
                    ->whereIn('id', collect($rows)->pluck('meter_id')->filter()->all())
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $index => $row) {
                    if (! isset($row['meter_id'])) {
                        continue;
                    }

                    $meter = $meters->get((int) $row['meter_id']);

                    if (! $meter) {
                        continue;
                    }

                    if ((int) $meter->room?->floor_no !== $floor || $meter->meter_type->value !== $type) {
                        $validator->errors()->add(
                            "readings.{$index}.meter_id",
                            "Meter {$meter->meter_serial_no} does not belong to floor {$floor} / {$type}.",
                        );

                        continue;
                    }

                    if (blank($row['current_reading'] ?? null)) {
                        continue;
                    }

                    $previous = $meter->latestReadingValue();
                    $entered = (float) $row['current_reading'];

                    if ($entered < $previous) {
                        $validator->errors()->add(
                            "readings.{$index}.current_reading",
                            sprintf(
                                'Room %s: reading %s is below the previous reading %s.',
                                $meter->room?->room_number ?? '—',
                                number_format($entered, 2),
                                number_format($previous, 2),
                            ),
                        );
                    }
                }

                if ($this->filled('reading_date') && $rows !== [] && collect($rows)->every(
                    fn ($row): bool => blank($row['current_reading'] ?? null),
                )) {
                    $validator->errors()->add('readings', 'Enter at least one meter reading before submitting.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reading_date.before_or_equal' => 'Readings cannot be dated in the future.',
            'readings.required' => 'There are no meters to read on this floor.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('reading_date')) {
            $this->merge([
                'reading_date' => Carbon::parse($this->input('reading_date'))->toDateString(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MeterReading;
use App\Models\UtilityMeter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    protected $model = MeterReading::class;

    public function definition(): array
    {
        $current = $this->faker->numberBetween(100, 900);

        return [
            'meter_id' => UtilityMeter::factory(),
            'reading_date' => now()->toDateString(),
            'previous_reading' => $current - 50,
            'current_reading' => $current,
            'consumption' => 50,
            'rate_per_unit' => 9.00,
            'amount' => 450.00,
        ];
    }
}

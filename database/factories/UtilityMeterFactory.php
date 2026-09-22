<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeterType;
use App\Models\Room;
use App\Models\UtilityMeter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityMeter>
 */
class UtilityMeterFactory extends Factory
{
    protected $model = UtilityMeter::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'meter_type' => MeterType::Electricity->value,
            'meter_serial_no' => 'E-'.$this->faker->unique()->numerify('####'),
            'multiplier' => 1.000,
            'is_active' => true,
        ];
    }
}

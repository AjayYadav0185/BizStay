<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bed>
 */
class BedFactory extends Factory
{
    protected $model = Bed::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'bed_code' => 'T-'.$this->faker->unique()->bothify('###?'),
            'position' => 1,
            'status' => BedStatus::Available->value,
        ];
    }
}

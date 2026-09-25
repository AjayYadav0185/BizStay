<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Enums\SharingType;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'floor_no' => $this->faker->numberBetween(0, 3),
            'room_number' => (string) $this->faker->unique()->numberBetween(101, 399),
            'sharing_type' => SharingType::Double->value,
            'base_rent_per_bed' => 10000,
            'nightly_rate' => 1799,
            'security_deposit_default' => 1,
            'has_ac' => false,
            'attached_bathroom' => true,
            'has_balcony' => false,
            'status' => RoomStatus::Available->value,
        ];
    }
}

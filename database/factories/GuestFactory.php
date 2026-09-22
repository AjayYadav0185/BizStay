<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->unique()->numerify('98########'),
            'email' => $this->faker->optional()->safeEmail(),
            'kyc_status' => KycStatus::Verified->value,
            'gender' => 'male',
            'is_blacklisted' => false,
        ];
    }
}

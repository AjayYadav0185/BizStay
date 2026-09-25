<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'bed_id' => Bed::factory(),
            'check_in_date' => now()->startOfMonth()->toDateString(),
            'monthly_rent' => 10000,
            'nightly_rate' => null,
            'guests_count' => 1,
            'stay_type' => 'pg',
            'security_deposit_amount' => 10000,
            'rent_due_day' => 5,
            'food_included' => true,
            'status' => BookingStatus::Active->value,
        ];
    }
}

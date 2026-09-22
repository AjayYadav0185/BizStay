<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Booking;
use App\Models\LeaveLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveLog>
 */
class LeaveLogFactory extends Factory
{
    protected $model = LeaveLog::class;

    public function definition(): array
    {
        $start = now()->subDays(5);

        return [
            'booking_id' => Booking::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'total_days' => 3,
            'food_opt_out' => true,
            'status' => LeaveStatus::Requested->value,
        ];
    }
}

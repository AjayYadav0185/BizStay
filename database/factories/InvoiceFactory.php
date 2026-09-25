<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'invoice_number' => 'BZ-'.$start->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'booking_id' => Booking::factory(),
            'billing_cycle_start' => $start->toDateString(),
            'billing_cycle_end' => $start->copy()->endOfMonth()->toDateString(),
            'rent_amount' => 8500,
            'utility_amount' => 0,
            'maintenance_charges' => 0,
            'food_deduction' => 0,
            'other_charges' => 0,
            'gst_percent' => 0,
            'gst_amount' => 0,
            'previous_balance' => 0,
            'total_due' => 8500,
            'amount_paid' => 0,
            'due_date' => $start->copy()->addDays(4)->toDateString(),
            'status' => InvoiceStatus::Unpaid->value,
        ];
    }
}

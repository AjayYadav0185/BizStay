<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'booking_id' => Booking::factory(),
            'payment_type' => PaymentType::Rent->value,
            'amount' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
            'status' => PaymentStatus::Success->value,
            'paid_on' => now()->toDateString(),
        ];
    }
}

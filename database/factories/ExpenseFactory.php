<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'category' => ExpenseCategory::Other->value,
            'amount' => $this->faker->numberBetween(500, 20000),
            'spent_on' => now()->toDateString(),
        ];
    }
}

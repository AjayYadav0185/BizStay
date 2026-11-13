<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visitor_name' => $this->faker->name(),
            'visitor_phone' => $this->faker->numerify('98########'),
            'relation' => $this->faker->randomElement(['Parent', 'Sibling', 'Friend', 'Colleague']),
            'purpose' => 'Meeting resident',
            'checked_in_at' => now()->subHour(),
            'checked_out_at' => null,
            'is_overnight' => false,
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(fn (): array => ['checked_out_at' => now()]);
    }
}

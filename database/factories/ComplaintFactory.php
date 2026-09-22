<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(10),
            'category' => 'other',
            'priority' => ComplaintPriority::Medium->value,
            'status' => ComplaintStatus::Open->value,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Month;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Month>
 */
class MonthFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => 2026,
            'month' => fake()->unique()->numberBetween(1, 12),
            'breakfast_price' => 20,
            'is_closed' => false,
        ];
    }
}

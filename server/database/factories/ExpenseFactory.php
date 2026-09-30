<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Month;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'month_id' => Month::factory(),
            'date' => now()->toDateString(),
            'cause' => fake()->words(2, true),
            'note' => fake()->optional()->sentence(),
            'is_grouped' => true,
            'amount' => fake()->numberBetween(100, 2000),
        ];
    }
}

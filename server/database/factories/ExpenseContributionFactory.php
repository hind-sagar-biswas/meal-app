<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Month;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseContribution>
 */
class ExpenseContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'month_id' => Month::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(100, 1000),
        ];
    }
}

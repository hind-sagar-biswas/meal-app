<?php

namespace Database\Factories;

use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meal>
 */
class MealFactory extends Factory
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
            'breakfast' => 0,
            'lunch' => 1,
            'dinner' => 1,
            'has_logged' => false,
        ];
    }
}

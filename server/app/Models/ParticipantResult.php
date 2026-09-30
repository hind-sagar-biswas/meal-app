<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantResult extends Model
{
    public static function calculate(MonthResult $result, User $participant): self
    {
        $month = $result->month;

        // Breakfast, lunch, dinner counts for the whole month's meals
        $counts = Meal::where('month_id', $month->id)
            ->where('has_logged', true)
            ->where('user_id', $participant->id)
            ->selectRaw('SUM(breakfast) as total_breakfast, SUM(lunch) as total_lunch, SUM(dinner) as total_dinner')
            ->first();

        $breakfastCount = $counts->total_breakfast;
        $mealCount = $counts->total_lunch + $counts->total_dinner;

        $breakfastExpense = $breakfastCount * $month->breakfast_price;
        $mealExpense = $mealCount * $result->meal_rate;
        $groupExpense = $result->group_expense_per_person;

        $totalExpense = round($breakfastExpense + ($mealExpense + $groupExpense) / 1_000_000);

        $totalContribution = $participant->contributions()->where('month_id', $month->id)->sum('amount');

        $adjustment = $totalExpense - $totalContribution;

        return self::updateOrCreate(
            [
                'month_result_id' => $result->id,
                'user_id' => $participant->id,
            ],
            [
                'month_id' => $month->id,
                'breakfast_count' => $breakfastCount,
                'meal_count' => $mealCount,
                'breakfast_expense' => $breakfastExpense,
                'meal_expense' => $mealExpense,
                'group_expense' => $groupExpense,
                'total_expense' => $totalExpense,
                'total_contribution' => $totalContribution,
                'adjustment' => $adjustment,
            ]
        );
    }
}

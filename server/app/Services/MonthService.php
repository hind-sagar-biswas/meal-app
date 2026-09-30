<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Meal;
use App\Models\Month;
use App\Models\MonthResult;
use App\Models\User;
use App\Notifications\MonthClosedNotification;
use App\Notifications\MonthReopenedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;

class MonthService
{
    /**
     * Get or create the ongoing active month.
     */
    public function getOrCreateCurrentMonth(): Month
    {
        return Month::ongoing();
    }

    /**
     * Set/update the unit breakfast price for an open month.
     */
    public function setBreakfastPrice(Month $month, int $price, User $editor): Month
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Breakfast price cannot be negative.');
        }

        if ($month->is_closed) {
            throw new RuntimeException('Cannot update breakfast price for a closed month.');
        }

        return DB::transaction(function () use ($month, $price, $editor) {
            $oldPrice = $month->breakfast_price;
            $month->update(['breakfast_price' => $price]);

            AuditLog::create([
                'user_id' => $editor->id,
                'action' => 'month.set_breakfast_price',
                'auditable_type' => Month::class,
                'auditable_id' => $month->id,
                'before' => ['breakfast_price' => $oldPrice],
                'after' => ['breakfast_price' => $price],
                'note' => "Updated breakfast price from {$oldPrice} to {$price} Tk.",
            ]);

            return $month->fresh();
        });
    }

    /**
     * Live calculation and summary of an ongoing month before closing.
     */
    public function getLiveSummary(Month $month): array
    {
        $activeUsers = User::where('is_active', true)->get(['id', 'name']);
        $participantCount = $activeUsers->count();

        // 1. Logged meal counts for the month
        $counts = Meal::where('month_id', $month->id)
            ->where('has_logged', true)
            ->selectRaw('SUM(breakfast) as total_breakfast, SUM(lunch) as total_lunch, SUM(dinner) as total_dinner')
            ->first();

        $breakfastCount = (int) ($counts?->total_breakfast ?? 0);
        $mealCount = (int) (($counts?->total_lunch ?? 0) + ($counts?->total_dinner ?? 0));

        // 2. Expenses breakdown
        $bazarExpense = (int) $month->expenses()->where('is_grouped', false)->sum('amount');
        $groupedExpense = (int) $month->expenses()->where('is_grouped', true)->sum('amount');
        $totalExpense = $bazarExpense + $groupedExpense;

        $breakfastExpense = $breakfastCount * $month->breakfast_price;
        $mealExpense = $bazarExpense - $breakfastExpense;

        $mealRate = $mealCount > 0 ? ($mealExpense / $mealCount) : 0.0;
        $groupExpensePerPerson = $participantCount > 0 ? ($groupedExpense / $participantCount) : 0.0;

        // 3. Member breakdowns
        $membersSummary = [];
        foreach ($activeUsers as $user) {
            $userCounts = Meal::where('month_id', $month->id)
                ->where('has_logged', true)
                ->where('user_id', $user->id)
                ->selectRaw('SUM(breakfast) as total_breakfast, SUM(lunch) as total_lunch, SUM(dinner) as total_dinner')
                ->first();

            $uBfCount = (int) ($userCounts?->total_breakfast ?? 0);
            $uMealCount = (int) (($userCounts?->total_lunch ?? 0) + ($userCounts?->total_dinner ?? 0));

            $uBfExpense = $uBfCount * $month->breakfast_price;
            $uMealExpense = (int) round($uMealCount * $mealRate);
            $uGroupExpense = (int) round($groupExpensePerPerson);
            $uTotalExpense = $uBfExpense + $uMealExpense + $uGroupExpense;

            $uContribution = (int) $user->contributions()->where('month_id', $month->id)->sum('amount');
            $uAdjustment = $uTotalExpense - $uContribution;

            $membersSummary[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'breakfast_count' => $uBfCount,
                'meal_count' => $uMealCount,
                'breakfast_expense' => $uBfExpense,
                'meal_expense' => $uMealExpense,
                'group_expense' => $uGroupExpense,
                'total_expense' => $uTotalExpense,
                'total_contribution' => $uContribution,
                'adjustment' => $uAdjustment,
            ];
        }

        return [
            'month' => [
                'id' => $month->id,
                'year' => $month->year,
                'month' => $month->month,
                'is_closed' => (bool) $month->is_closed,
                'breakfast_price' => $month->breakfast_price,
            ],
            'totals' => [
                'participant_count' => $participantCount,
                'breakfast_count' => $breakfastCount,
                'meal_count' => $mealCount,
                'total_expense' => $totalExpense,
                'bazar_expense' => $bazarExpense,
                'grouped_expense' => $groupedExpense,
                'breakfast_expense' => $breakfastExpense,
                'meal_expense' => $mealExpense,
                'meal_rate' => round($mealRate, 4),
                'group_expense_per_person' => round($groupExpensePerPerson, 4),
            ],
            'members' => $membersSummary,
        ];
    }

    /**
     * Close the month, snapshotting results and broadcasting notifications.
     */
    public function closeMonth(Month $month, User $closedBy): MonthResult
    {
        if ($month->is_closed) {
            throw new RuntimeException('Month is already closed!');
        }

        return DB::transaction(function () use ($month, $closedBy) {
            $result = $month->close($closedBy);

            AuditLog::create([
                'user_id' => $closedBy->id,
                'action' => 'month.close',
                'auditable_type' => Month::class,
                'auditable_id' => $month->id,
                'before' => ['is_closed' => false],
                'after' => [
                    'is_closed' => true,
                    'closed_by' => $closedBy->id,
                    'meal_rate' => $result->meal_rate / 1_000_000,
                    'total_expense' => $result->total_expense,
                ],
                'note' => "Month closed by {$closedBy->name}.",
            ]);

            $activeUsers = User::where('is_active', true)->get();
            if ($activeUsers->isNotEmpty()) {
                Notification::send($activeUsers, new MonthClosedNotification($month, $result, $closedBy));
            }

            return $result->load('participants.user:id,name');
        });
    }

    /**
     * Reopen a closed month within the 6-hour window.
     */
    public function reopenMonth(Month $month, User $reopenedBy): Month
    {
        if (! $month->is_closed) {
            throw new RuntimeException('Month is not closed!');
        }

        if (now()->greaterThan($month->closed_at->copy()->addHours(6))) {
            throw new RuntimeException('The deadline to open the month has passed!');
        }

        return DB::transaction(function () use ($month, $reopenedBy) {
            $month->open();

            AuditLog::create([
                'user_id' => $reopenedBy->id,
                'action' => 'month.reopen',
                'auditable_type' => Month::class,
                'auditable_id' => $month->id,
                'before' => ['is_closed' => true],
                'after' => ['is_closed' => false],
                'note' => "Month reopened by {$reopenedBy->name}.",
            ]);

            $otherMembers = User::where('is_active', true)->where('id', '!=', $reopenedBy->id)->get();
            if ($otherMembers->isNotEmpty()) {
                Notification::send($otherMembers, new MonthReopenedNotification($month, $reopenedBy));
            }

            return $month->fresh();
        });
    }

    /**
     * Retrieve the snapshot results of a closed month.
     */
    public function getClosedMonthResult(Month $month): ?MonthResult
    {
        return MonthResult::where('month_id', $month->id)
            ->with(['participants.user:id,name', 'month'])
            ->first();
    }
}

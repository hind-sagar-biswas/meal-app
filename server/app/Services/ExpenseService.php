<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Month;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ExpenseService
{
    /**
     * Create a new expense with one or more contributor allocations.
     *
     * @param  array{cause: string, amount: int, is_grouped?: bool, date?: Carbon|string, note?: ?string}  $data
     * @param  array<int, array{user_id: int, amount: int}>  $contributions
     */
    public function createExpense(User $creator, array $data, array $contributions, ?string $note = null): Expense
    {
        $cause = trim($data['cause'] ?? '');
        if ($cause === '') {
            throw new InvalidArgumentException('Expense cause is required.');
        }

        $amount = (int) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        $date = isset($data['date']) ? Carbon::parse($data['date']) : now();
        $isGrouped = (bool) ($data['is_grouped'] ?? true);
        $expenseNote = $note ?? ($data['note'] ?? null);

        $month = Month::findFromDate($date);
        if (! $month) {
            throw new RuntimeException('Month not found for the provided expense date.');
        }

        if ($month->is_closed) {
            throw new RuntimeException('Cannot add expenses to a closed month.');
        }

        $this->validateContributions($amount, $contributions);

        return DB::transaction(function () use ($creator, $month, $date, $cause, $isGrouped, $amount, $expenseNote, $contributions) {
            /** @var Expense $expense */
            $expense = Expense::create([
                'user_id' => $creator->id,
                'month_id' => $month->id,
                'date' => $date->toDateString(),
                'cause' => $cause,
                'note' => $expenseNote,
                'is_grouped' => $isGrouped,
                'amount' => $amount,
            ]);

            foreach ($contributions as $contrib) {
                ExpenseContribution::create([
                    'expense_id' => $expense->id,
                    'month_id' => $month->id,
                    'user_id' => $contrib['user_id'],
                    'amount' => (int) $contrib['amount'],
                ]);
            }

            AuditLog::create([
                'user_id' => $creator->id,
                'action' => 'expense.create',
                'auditable_type' => Expense::class,
                'auditable_id' => $expense->id,
                'before' => null,
                'after' => [
                    'cause' => $cause,
                    'amount' => $amount,
                    'is_grouped' => $isGrouped,
                    'date' => $date->toDateString(),
                    'contributions' => $contributions,
                ],
                'note' => $expenseNote,
            ]);

            return $expense->load(['user:id,name', 'contributions.user:id,name']);
        });
    }

    /**
     * Create an additive or compensating adjustment for an existing expense.
     *
     * @param  array<int, array{user_id: int, amount: int}>  $contributions
     */
    public function createAdjustmentExpense(Expense $original, User $creator, int $deltaAmount, array $contributions, string $note): Expense
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A note is required for expense adjustments.');
        }

        if ($deltaAmount === 0) {
            throw new InvalidArgumentException('Adjustment delta amount cannot be zero.');
        }

        $month = $original->month;
        if (! $month) {
            throw new RuntimeException('Month not found for original expense.');
        }

        if ($month->is_closed) {
            throw new RuntimeException('Cannot adjust expenses for a closed month.');
        }

        $this->validateContributions($deltaAmount, $contributions, allowNegative: true);

        return DB::transaction(function () use ($original, $creator, $month, $deltaAmount, $contributions, $note) {
            $cause = "Adjustment: {$original->cause} (#{$original->id})";

            /** @var Expense $expense */
            $expense = Expense::create([
                'user_id' => $creator->id,
                'month_id' => $month->id,
                'date' => now()->toDateString(),
                'cause' => $cause,
                'note' => $note,
                'is_grouped' => $original->is_grouped,
                'amount' => $deltaAmount,
            ]);

            foreach ($contributions as $contrib) {
                ExpenseContribution::create([
                    'expense_id' => $expense->id,
                    'month_id' => $month->id,
                    'user_id' => $contrib['user_id'],
                    'amount' => (int) $contrib['amount'],
                ]);
            }

            AuditLog::create([
                'user_id' => $creator->id,
                'action' => 'expense.adjustment',
                'auditable_type' => Expense::class,
                'auditable_id' => $expense->id,
                'before' => [
                    'original_expense_id' => $original->id,
                    'original_amount' => $original->amount,
                ],
                'after' => [
                    'delta_amount' => $deltaAmount,
                    'contributions' => $contributions,
                ],
                'note' => $note,
            ]);

            return $expense->load(['user:id,name', 'contributions.user:id,name']);
        });
    }

    /**
     * Get all expenses for a month with contributors.
     */
    public function getMonthExpenses(Month $month, ?bool $isGrouped = null): Collection
    {
        $query = Expense::where('month_id', $month->id)
            ->with(['user:id,name', 'contributions.user:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if ($isGrouped !== null) {
            $query->where('is_grouped', $isGrouped);
        }

        return $query->get();
    }

    /**
     * Summary of user contributions in a month.
     */
    public function getUserContributionsSummary(Month $month, User $user): array
    {
        $contributions = ExpenseContribution::where('month_id', $month->id)
            ->where('user_id', $user->id)
            ->with('expense:id,is_grouped,cause,date')
            ->get();

        $bazarContribution = (int) $contributions->filter(fn ($c) => $c->expense && ! $c->expense->is_grouped)->sum('amount');
        $groupContribution = (int) $contributions->filter(fn ($c) => $c->expense && $c->expense->is_grouped)->sum('amount');

        return [
            'month_id' => $month->id,
            'user_id' => $user->id,
            'bazar_contribution' => $bazarContribution,
            'group_contribution' => $groupContribution,
            'total_contribution' => $bazarContribution + $groupContribution,
            'contributions_count' => $contributions->count(),
        ];
    }

    /**
     * Validate that contributor rows are valid and sum matches the total amount.
     *
     * @param  array<int, array{user_id: int, amount: int}>  $contributions
     */
    private function validateContributions(int $totalAmount, array $contributions, bool $allowNegative = false): void
    {
        if (empty($contributions)) {
            throw new InvalidArgumentException('At least one contributor is required.');
        }

        $sum = 0;
        $seenUserIds = [];

        foreach ($contributions as $index => $contrib) {
            $userId = (int) ($contrib['user_id'] ?? 0);
            $amount = (int) ($contrib['amount'] ?? 0);

            if ($userId <= 0) {
                throw new InvalidArgumentException("Invalid user ID at contributor row #{$index}.");
            }

            if (isset($seenUserIds[$userId])) {
                throw new InvalidArgumentException('Duplicate contributor in the same expense.');
            }
            $seenUserIds[$userId] = true;

            if (! $allowNegative && $amount <= 0) {
                throw new InvalidArgumentException('Contributor amount must be greater than zero.');
            }

            $sum += $amount;
        }

        if ($sum !== $totalAmount) {
            throw new InvalidArgumentException("The sum of contributions ({$sum}) must equal the total expense amount ({$totalAmount}).");
        }
    }
}

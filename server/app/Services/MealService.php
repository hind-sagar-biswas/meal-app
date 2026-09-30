<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use App\Notifications\DateRangeMealOffNotification;
use App\Notifications\DayMealOffNotification;
use App\Notifications\DayMealTallyUpdatedNotification;
use App\Notifications\MealCountIncreasedNotification;
use App\Notifications\MemberMealEditedNotification;
use App\Notifications\OwnMealEditedNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;

class MealService
{
    /**
     * Silent one-way opt-in for breakfast (0 -> 1).
     */
    public function optInBreakfast(Meal $meal, User $user): Meal
    {
        if ($meal->user_id !== $user->id) {
            throw new InvalidArgumentException('You can only opt in to breakfast for your own meal.');
        }

        $this->ensureMonthIsOpen($meal);

        return DB::transaction(function () use ($meal) {
            $meal->optInBreakfast();

            return $meal->fresh();
        });
    }

    /**
     * Silent one-way opt-out for lunch (sets lunch to 0 before 5:00 AM).
     */
    public function optOutLunch(Meal $meal, User $user): Meal
    {
        if ($meal->user_id !== $user->id) {
            throw new InvalidArgumentException('You can only opt out of lunch for your own meal.');
        }

        $this->ensureMonthIsOpen($meal);

        return DB::transaction(function () use ($meal) {
            $meal->optOutLunch();

            return $meal->fresh();
        });
    }

    /**
     * Silent one-way opt-out for dinner (sets dinner to 0 before 2:20 PM).
     */
    public function optOutDinner(Meal $meal, User $user): Meal
    {
        if ($meal->user_id !== $user->id) {
            throw new InvalidArgumentException('You can only opt out of dinner for your own meal.');
        }

        $this->ensureMonthIsOpen($meal);

        return DB::transaction(function () use ($meal) {
            $meal->optOutDinner();

            return $meal->fresh();
        });
    }

    /**
     * Manual edit of an individual meal entry.
     */
    public function editMeal(Meal $meal, User $editor, int $breakfast, int $lunch, int $dinner, string $note): Meal
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A note is required for manual meal edits.');
        }

        if ($breakfast < 0 || $lunch < 0 || $dinner < 0) {
            throw new InvalidArgumentException('Meal counts cannot be negative.');
        }

        $this->ensureMonthIsOpen($meal);

        return DB::transaction(function () use ($meal, $editor, $breakfast, $lunch, $dinner, $note) {
            $before = [
                'breakfast' => $meal->breakfast,
                'lunch' => $meal->lunch,
                'dinner' => $meal->dinner,
            ];

            $meal->update([
                'breakfast' => $breakfast,
                'lunch' => $lunch,
                'dinner' => $dinner,
            ]);

            $after = [
                'breakfast' => $meal->breakfast,
                'lunch' => $meal->lunch,
                'dinner' => $meal->dinner,
            ];

            AuditLog::create([
                'user_id' => $editor->id,
                'action' => 'meal.edit',
                'auditable_type' => Meal::class,
                'auditable_id' => $meal->id,
                'before' => $before,
                'after' => $after,
                'note' => $note,
            ]);

            $this->dispatchIndividualEditNotification($meal, $editor, $before, $after, $note);

            return $meal->fresh();
        });
    }

    /**
     * Update a whole day's tally for all active members.
     */
    public function updateDayTally(Carbon|string $date, User $editor, int $breakfast, int $lunch, int $dinner, string $note): int
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A note is required to update day meal tally.');
        }

        if ($breakfast < 0 || $lunch < 0 || $dinner < 0) {
            throw new InvalidArgumentException('Meal counts cannot be negative.');
        }

        $carbonDate = $this->parseDate($date);
        $month = Month::findFromDate($carbonDate);
        if ($month && $month->is_closed) {
            throw new RuntimeException('Cannot modify meals for a closed month.');
        }

        return DB::transaction(function () use ($carbonDate, $editor, $breakfast, $lunch, $dinner, $note) {
            $activeUserIds = User::where('is_active', true)->pluck('id');

            $count = Meal::where('date', $carbonDate->toDateString())
                ->whereIn('user_id', $activeUserIds)
                ->update([
                    'breakfast' => $breakfast,
                    'lunch' => $lunch,
                    'dinner' => $dinner,
                ]);

            AuditLog::create([
                'user_id' => $editor->id,
                'action' => 'meal.day_tally_updated',
                'auditable_type' => null,
                'auditable_id' => null,
                'before' => null,
                'after' => [
                    'date' => $carbonDate->toDateString(),
                    'breakfast' => $breakfast,
                    'lunch' => $lunch,
                    'dinner' => $dinner,
                    'affected_count' => $count,
                ],
                'note' => $note,
            ]);

            $otherMembers = User::where('is_active', true)->where('id', '!=', $editor->id)->get();
            if ($otherMembers->isNotEmpty()) {
                Notification::send($otherMembers, new DayMealTallyUpdatedNotification(
                    $carbonDate,
                    $editor,
                    $breakfast,
                    $lunch,
                    $dinner,
                    $note
                ));
            }

            return $count;
        });
    }

    /**
     * Turn all meals off (0, 0, 0) for a single day for all active members.
     */
    public function setDayMealsOff(Carbon|string $date, User $editor, string $note): int
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A note is required to mark day meals off.');
        }

        $carbonDate = $this->parseDate($date);
        $month = Month::findFromDate($carbonDate);
        if ($month && $month->is_closed) {
            throw new RuntimeException('Cannot modify meals for a closed month.');
        }

        return DB::transaction(function () use ($carbonDate, $editor, $note) {
            $activeUserIds = User::where('is_active', true)->pluck('id');

            $count = Meal::where('date', $carbonDate->toDateString())
                ->whereIn('user_id', $activeUserIds)
                ->update([
                    'breakfast' => 0,
                    'lunch' => 0,
                    'dinner' => 0,
                ]);

            AuditLog::create([
                'user_id' => $editor->id,
                'action' => 'meal.day_off',
                'auditable_type' => null,
                'auditable_id' => null,
                'before' => null,
                'after' => [
                    'date' => $carbonDate->toDateString(),
                    'affected_count' => $count,
                ],
                'note' => $note,
            ]);

            $otherMembers = User::where('is_active', true)->where('id', '!=', $editor->id)->get();
            if ($otherMembers->isNotEmpty()) {
                Notification::send($otherMembers, new DayMealOffNotification(
                    $carbonDate,
                    $editor,
                    $note
                ));
            }

            return $count;
        });
    }

    /**
     * Turn all meals off (0, 0, 0) across a date range for all active members.
     */
    public function setDateRangeMealsOff(Carbon|string $startDate, Carbon|string $endDate, User $editor, string $note): int
    {
        $note = trim($note);
        if ($note === '') {
            throw new InvalidArgumentException('A note is required to mark date range meals off.');
        }

        $start = $this->parseDate($startDate)->startOfDay();
        $end = $this->parseDate($endDate)->startOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('Start date must be before or equal to end date.');
        }

        // Check if any month in the period is closed
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $d) {
            $month = Month::findFromDate($d);
            if ($month && $month->is_closed) {
                throw new RuntimeException("Cannot modify meals for closed month ({$d->format('F Y')}).");
            }
        }

        return DB::transaction(function () use ($start, $end, $editor, $note) {
            $activeUserIds = User::where('is_active', true)->pluck('id');

            $count = Meal::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->whereIn('user_id', $activeUserIds)
                ->update([
                    'breakfast' => 0,
                    'lunch' => 0,
                    'dinner' => 0,
                ]);

            AuditLog::create([
                'user_id' => $editor->id,
                'action' => 'meal.date_range_off',
                'auditable_type' => null,
                'auditable_id' => null,
                'before' => null,
                'after' => [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'affected_count' => $count,
                ],
                'note' => $note,
            ]);

            $otherMembers = User::where('is_active', true)->where('id', '!=', $editor->id)->get();
            if ($otherMembers->isNotEmpty()) {
                Notification::send($otherMembers, new DateRangeMealOffNotification(
                    $start,
                    $end,
                    $editor,
                    $note
                ));
            }

            return $count;
        });
    }

    /**
     * Today's meal dashboard breakdown.
     */
    public function getTodayDashboard(Carbon|string|null $date = null): array
    {
        $carbonDate = $date ? $this->parseDate($date) : now();
        $dateString = $carbonDate->toDateString();

        $activeUsers = User::where('is_active', true)->get(['id', 'name']);
        $activeUserIds = $activeUsers->pluck('id');

        $meals = Meal::where('date', $dateString)
            ->whereIn('user_id', $activeUserIds)
            ->with('user:id,name')
            ->get();

        $bfHeadcount = $meals->where('breakfast', '>', 0)->count();
        $bfUnits = (int) $meals->sum('breakfast');

        $lcHeadcount = $meals->where('lunch', '>', 0)->count();
        $lcUnits = (int) $meals->sum('lunch');

        $dnHeadcount = $meals->where('dinner', '>', 0)->count();
        $dnUnits = (int) $meals->sum('dinner');

        $members = $meals->map(fn (Meal $m) => [
            'user_id' => $m->user_id,
            'name' => $m->user->name ?? 'Unknown',
            'breakfast' => $m->breakfast,
            'lunch' => $m->lunch,
            'dinner' => $m->dinner,
            'has_logged' => (bool) $m->has_logged,
        ])->values()->all();

        return [
            'date' => $dateString,
            'summary' => [
                'breakfast' => ['headcount' => $bfHeadcount, 'units' => $bfUnits],
                'lunch' => ['headcount' => $lcHeadcount, 'units' => $lcUnits],
                'dinner' => ['headcount' => $dnHeadcount, 'units' => $dnUnits],
                'total_units' => $bfUnits + $lcUnits + $dnUnits,
            ],
            'members' => $members,
        ];
    }

    private function ensureMonthIsOpen(Meal $meal): void
    {
        if ($meal->month && $meal->month->is_closed) {
            throw new RuntimeException('Cannot modify meals for a closed month.');
        }
    }

    private function parseDate(Carbon|string $date): Carbon
    {
        return $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
    }

    private function dispatchIndividualEditNotification(Meal $meal, User $editor, array $before, array $after, string $note): void
    {
        if ($editor->id === $meal->user_id) {
            $otherMembers = User::where('is_active', true)->where('id', '!=', $editor->id)->get();
            if ($otherMembers->isEmpty()) {
                return;
            }

            // Check if it was a single meal count increase (guest meals)
            $increasedMealType = null;
            $oldCount = 0;
            $newCount = 0;
            $changeCount = 0;

            foreach (['breakfast', 'lunch', 'dinner'] as $type) {
                if ($after[$type] !== $before[$type]) {
                    $changeCount++;
                    if ($after[$type] > $before[$type]) {
                        $increasedMealType = $type;
                        $oldCount = $before[$type];
                        $newCount = $after[$type];
                    }
                }
            }

            if ($changeCount === 1 && $increasedMealType !== null && $oldCount > 0) {
                Notification::send($otherMembers, new MealCountIncreasedNotification(
                    $meal,
                    $editor,
                    $increasedMealType,
                    $oldCount,
                    $newCount,
                    $note
                ));
            } else {
                Notification::send($otherMembers, new OwnMealEditedNotification(
                    $meal,
                    $editor,
                    $before,
                    $note
                ));
            }
        } else {
            $targetUser = $meal->user;
            if ($targetUser) {
                $targetUser->notify(new MemberMealEditedNotification(
                    $meal,
                    $editor,
                    $before,
                    $note
                ));
            }
        }
    }
}

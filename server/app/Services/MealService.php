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
use Illuminate\Support\Facades\Cache;
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
            $this->invalidateMealCaches($meal->month_id, $meal->date, $meal->user_id);

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
            $this->invalidateMealCaches($meal->month_id, $meal->date, $meal->user_id);

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
            $this->invalidateMealCaches($meal->month_id, $meal->date, $meal->user_id);

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
            $this->invalidateMealCaches($meal->month_id, $meal->date, $meal->user_id);

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

        return DB::transaction(function () use ($carbonDate, $month, $editor, $breakfast, $lunch, $dinner, $note) {
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

            if ($month) {
                $this->invalidateMealCaches($month->id, $carbonDate->toDateString());
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

        return DB::transaction(function () use ($carbonDate, $month, $editor, $note) {
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

            if ($month) {
                $this->invalidateMealCaches($month->id, $carbonDate->toDateString());
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
        $monthsToInvalidate = [];
        foreach ($period as $d) {
            $month = Month::findFromDate($d);
            if ($month && $month->is_closed) {
                throw new RuntimeException("Cannot modify meals for closed month ({$d->format('F Y')}).");
            }
            if ($month) {
                $monthsToInvalidate[$month->id] = true;
            }
        }

        return DB::transaction(function () use ($start, $end, $monthsToInvalidate, $editor, $note) {
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

            foreach (array_keys($monthsToInvalidate) as $mId) {
                $this->invalidateMealCaches($mId, $start->toDateString());
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

        return [
            'server_time' => now()->toIso8601String(),
            'date' => $dateString,
            'summary' => $this->getTodaySummary($carbonDate),
            'members' => $this->getTodayMembers($carbonDate),
        ];
    }

    /**
     * Get the logged in user's today meal status with cutoff eligibility.
     */
    public function getMyToday(User $user, Carbon|string|null $date = null): array
    {
        $carbonDate = $date ? $this->parseDate($date) : now();
        $dateString = $carbonDate->toDateString();

        $month = Month::findFromDate($carbonDate);
        $isClosed = (bool) ($month?->is_closed ?? false);

        $meal = Meal::where('user_id', $user->id)
            ->where('date', $dateString)
            ->first();

        $cutoffLc = $carbonDate->copy()->setTime(5, 0, 0);
        $cutoffDn = $carbonDate->copy()->setTime(14, 20, 0);
        $now = now();

        $isToday = $dateString === $now->toDateString();

        return [
            'server_time' => $now->toIso8601String(),
            'date' => $dateString,
            'is_month_closed' => $isClosed,
            'cutoffs' => [
                'breakfast_cutoff' => null,
                'lunch_cutoff' => '05:00',
                'dinner_cutoff' => '14:20',
                'breakfast_opt_in_allowed' => ! $isClosed && ($meal?->breakfast ?? 0) === 0,
                'lunch_opt_out_allowed' => ! $isClosed && $isToday && $now->lessThan($cutoffLc) && ($meal?->lunch ?? 0) > 0,
                'dinner_opt_out_allowed' => ! $isClosed && $isToday && $now->lessThan($cutoffDn) && ($meal?->dinner ?? 0) > 0,
            ],
            'meal' => $meal ? [
                'id' => $meal->id,
                'user_id' => $meal->user_id,
                'date' => $meal->date ? (is_string($meal->date) ? $meal->date : $meal->date->toDateString()) : null,
                'breakfast' => (int) $meal->breakfast,
                'lunch' => (int) $meal->lunch,
                'dinner' => (int) $meal->dinner,
                'total' => (int) ($meal->breakfast + $meal->lunch + $meal->dinner),
                'has_logged' => (bool) $meal->has_logged,
                'is_editable' => ! $isClosed,
            ] : null,
        ];
    }

    /**
     * Get aggregate total meal counts for a date (cached 15s).
     */
    public function getTodaySummary(Carbon|string|null $date = null): array
    {
        $carbonDate = $date ? $this->parseDate($date) : now();
        $dateString = $carbonDate->toDateString();

        return Cache::remember("meals:today_summary:{$dateString}", 15, function () use ($dateString) {
            $activeUserIds = User::where('is_active', true)->pluck('id');

            $meals = Meal::where('date', $dateString)
                ->whereIn('user_id', $activeUserIds)
                ->get();

            $bfHeadcount = $meals->where('breakfast', '>', 0)->count();
            $bfUnits = (int) $meals->sum('breakfast');

            $lcHeadcount = $meals->where('lunch', '>', 0)->count();
            $lcUnits = (int) $meals->sum('lunch');

            $dnHeadcount = $meals->where('dinner', '>', 0)->count();
            $dnUnits = (int) $meals->sum('dinner');

            return [
                'date' => $dateString,
                'breakfast' => ['headcount' => $bfHeadcount, 'units' => $bfUnits],
                'lunch' => ['headcount' => $lcHeadcount, 'units' => $lcUnits],
                'dinner' => ['headcount' => $dnHeadcount, 'units' => $dnUnits],
                'total_units' => $bfUnits + $lcUnits + $dnUnits,
            ];
        });
    }

    /**
     * Get list of all active roommates and their meal counts for a date (cached 15s).
     */
    public function getTodayMembers(Carbon|string|null $date = null): array
    {
        $carbonDate = $date ? $this->parseDate($date) : now();
        $dateString = $carbonDate->toDateString();

        return Cache::remember("meals:today_members:{$dateString}", 15, function () use ($dateString) {
            $activeUsers = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
            $activeUserIds = $activeUsers->pluck('id');

            $meals = Meal::where('date', $dateString)
                ->whereIn('user_id', $activeUserIds)
                ->with('user:id,name')
                ->get()
                ->keyBy('user_id');

            return $activeUsers->map(function (User $u) use ($meals) {
                $m = $meals->get($u->id);

                return [
                    'meal_id' => $m?->id,
                    'user_id' => $u->id,
                    'name' => $u->name,
                    'breakfast' => (int) ($m?->breakfast ?? 0),
                    'lunch' => (int) ($m?->lunch ?? 0),
                    'dinner' => (int) ($m?->dinner ?? 0),
                    'total' => (int) (($m?->breakfast ?? 0) + ($m?->lunch ?? 0) + ($m?->dinner ?? 0)),
                    'has_logged' => (bool) ($m?->has_logged ?? false),
                ];
            })->values()->all();
        });
    }

    /**
     * Get 31-day meal breakdown for a specific user in a month (cached 30s).
     */
    public function getUserMonthMeals(User $user, Month $month): array
    {
        return Cache::remember("meals:my_month:{$user->id}:{$month->id}", 30, function () use ($user, $month) {
            $meals = Meal::where('month_id', $month->id)
                ->where('user_id', $user->id)
                ->orderBy('date', 'asc')
                ->get();

            $totalBf = (int) $meals->sum('breakfast');
            $totalLc = (int) $meals->sum('lunch');
            $totalDn = (int) $meals->sum('dinner');

            $dayRows = $meals->map(function (Meal $m) {
                $cDate = Carbon::parse($m->date);

                return [
                    'id' => $m->id,
                    'date' => $cDate->toDateString(),
                    'day' => (int) $cDate->format('j'),
                    'day_name' => $cDate->format('l'),
                    'is_friday' => $cDate->isFriday(),
                    'breakfast' => (int) $m->breakfast,
                    'lunch' => (int) $m->lunch,
                    'dinner' => (int) $m->dinner,
                    'total' => (int) ($m->breakfast + $m->lunch + $m->dinner),
                    'has_logged' => (bool) $m->has_logged,
                ];
            })->values()->all();

            return [
                'month' => [
                    'id' => $month->id,
                    'year' => $month->year,
                    'month' => $month->month,
                    'is_closed' => (bool) $month->is_closed,
                ],
                'totals' => [
                    'breakfast' => $totalBf,
                    'lunch' => $totalLc,
                    'dinner' => $totalDn,
                    'total_meals' => $totalBf + $totalLc + $totalDn,
                ],
                'days' => $dayRows,
            ];
        });
    }

    /**
     * Get the full spreadsheet matrix (31 Days as Rows x 8 Members as Columns, cached 30s).
     */
    public function getMonthSheet(Month $month): array
    {
        return Cache::remember("meals:sheet:{$month->id}", 30, function () use ($month) {
            $activeUsers = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
            $activeUserIds = $activeUsers->pluck('id');

            $allMeals = Meal::where('month_id', $month->id)
                ->whereIn('user_id', $activeUserIds)
                ->orderBy('date', 'asc')
                ->get()
                ->groupBy('date');

            $startDate = Carbon::createFromDate($month->year, $month->month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->startOfDay();
            $period = CarbonPeriod::create($startDate, $endDate);

            $memberTotals = [];
            foreach ($activeUsers as $user) {
                $memberTotals[$user->id] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'breakfast' => 0,
                    'lunch' => 0,
                    'dinner' => 0,
                    'total' => 0,
                ];
            }

            $rows = [];
            $grandTotal = 0;

            foreach ($period as $date) {
                $dateString = $date->toDateString();
                $dayMeals = $allMeals->get($dateString, collect())->keyBy('user_id');

                $rowMeals = [];
                $dailyTotal = 0;

                foreach ($activeUsers as $user) {
                    $m = $dayMeals->get($user->id);
                    $bf = (int) ($m?->breakfast ?? 0);
                    $lc = (int) ($m?->lunch ?? 0);
                    $dn = (int) ($m?->dinner ?? 0);
                    $t = $bf + $lc + $dn;

                    $rowMeals[$user->id] = [
                        'meal_id' => $m?->id,
                        'breakfast' => $bf,
                        'lunch' => $lc,
                        'dinner' => $dn,
                        'total' => $t,
                    ];

                    $dailyTotal += $t;
                    $memberTotals[$user->id]['breakfast'] += $bf;
                    $memberTotals[$user->id]['lunch'] += $lc;
                    $memberTotals[$user->id]['dinner'] += $dn;
                    $memberTotals[$user->id]['total'] += $t;
                }

                $grandTotal += $dailyTotal;

                $rows[] = [
                    'date' => $dateString,
                    'day' => (int) $date->format('j'),
                    'day_name' => $date->format('l'),
                    'is_friday' => $date->isFriday(),
                    'meals' => $rowMeals,
                    'daily_total' => $dailyTotal,
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
                'members' => $activeUsers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'rows' => $rows,
                'member_totals' => array_values($memberTotals),
                'grand_total' => $grandTotal,
            ];
        });
    }

    /**
     * Invalidate micro-caches associated with meal changes.
     */
    public function invalidateMealCaches(Month|int $monthId, \DateTimeInterface|string $date, ?int $userId = null): void
    {
        $id = $monthId instanceof Month ? $monthId->id : $monthId;
        $dateString = is_string($date) ? Carbon::parse($date)->toDateString() : $date->format('Y-m-d');

        Cache::forget("meals:today_summary:{$dateString}");
        Cache::forget("meals:today_members:{$dateString}");
        Cache::forget("meals:sheet:{$id}");
        Cache::forget("month_live_summary:{$id}");

        if ($userId) {
            Cache::forget("meals:my_month:{$userId}:{$id}");
        } else {
            $activeUserIds = User::where('is_active', true)->pluck('id');
            foreach ($activeUserIds as $uid) {
                Cache::forget("meals:my_month:{$uid}:{$id}");
            }
        }
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

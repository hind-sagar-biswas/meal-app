<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Override;

class Month extends Model
{
    /** @use HasFactory<MonthFactory> */
    use HasFactory;

    protected $casts = [
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    #[Override]
    public static function booted()
    {
        parent::booted();

        static::created(function (self $month) {
            // Create the whole month's meal entries for everyone
            $startDate = Carbon::createFromDate($month->year, $month->month, 1)->startOfDay();
            $endDate = $startDate->copy()->endOfMonth()->startOfDay();

            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                Meal::make($date->copy()->startOfDay(), $month);
            }
        });
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(ExpenseContribution::class);
    }

    public function participantResults(): HasMany
    {
        return $this->hasMany(ParticipantResult::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(MonthResult::class);
    }

    public static function ongoing(): self
    {
        return self::firstOrCreate([
            'year' => now()->year,
            'month' => now()->month,
        ]);
    }

    public static function previous(): ?self
    {
        $previousMonth = now()->subMonth();

        return self::where('year', $previousMonth->year)->where('month', $previousMonth->month)->first();
    }

    public static function findFromDate(Carbon $date): ?self
    {
        return self::where('year', $date->year)->where('month', $date->month)->first();
    }

    public function open()
    {
        if (! $this->is_closed) {
            throw new \Exception('Month is not closed!');
        }

        if (now()->greaterThan($this->closed_at->copy()->addHours(6))) {
            throw new \Exception('The deadline to open the month has passed!');
        }

        $this->is_closed = false;
        $this->closed_by = null;
        $this->closed_at = null;
        $this->save();
    }

    public function close(?User $user = null): MonthResult
    {
        if ($this->is_closed) {
            throw new \Exception('Month is already closed!');
        }

        $user_id = $user?->id ?? Auth::id();

        if ($user_id === null) {
            throw new \Exception('No user logged in!');
        }

        $participantCount = User::where('is_active', true)->count();

        // Breakfast, lunch, dinner counts for the whole month's meals
        $counts = Meal::where('month_id', $this->id)->where('has_logged', true)
            ->selectRaw('SUM(breakfast) as total_breakfast, SUM(lunch) as total_lunch, SUM(dinner) as total_dinner')
            ->first();

        $breakfastCount = (int) ($counts?->total_breakfast ?? 0);
        $mealCount = (int) (($counts?->total_lunch ?? 0) + ($counts?->total_dinner ?? 0));

        $bazarExpense = (int) $this->expenses()->where('is_grouped', false)->sum('amount');
        $groupedExpense = (int) $this->expenses()->where('is_grouped', true)->sum('amount');
        $totalExpense = (int) $this->expenses()->sum('amount');

        $breakfastExpense = $breakfastCount * $this->breakfast_price;
        $mealExpense = $bazarExpense - $breakfastExpense;

        $mealRate = $mealCount > 0 ? (int) (($mealExpense / $mealCount) * 1_000_000) : 0;
        $groupExpensePerPerson = $participantCount > 0 ? (int) (($groupedExpense / $participantCount) * 1_000_000) : 0;

        /** @var MonthResult $result */
        $result = MonthResult::updateOrCreate(
            ['month_id' => $this->id],
            [
                'participant_count' => $participantCount,
                'breakfast_count' => $breakfastCount,
                'meal_count' => $mealCount,
                'total_expense' => $totalExpense,
                'bazar_expense' => $bazarExpense,
                'grouped_expense' => $groupedExpense,
                'breakfast_expense' => $breakfastExpense,
                'meal_expense' => $mealExpense,
                'meal_rate' => $mealRate,
                'group_expense_per_person' => $groupExpensePerPerson,
            ]
        );

        $this->is_closed = true;
        $this->closed_by = $user_id;
        $this->closed_at = now();
        $this->save();

        return $result;
    }
}

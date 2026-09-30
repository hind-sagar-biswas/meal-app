<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class Month extends Model
{
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
            $startDate = Carbon::createFromDate($month->year, $month->month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                Meal::make($date, $month);
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

    public function open() {
        if (!$this->is_closed) {
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
}

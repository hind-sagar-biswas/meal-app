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
}

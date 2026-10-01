<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Meal extends Model
{
    use HasFactory;

    public const LUNCH_OPT_CUTOFF = '5:00 AM';

    public const DINNER_OPT_CUTOFF = '2:20 PM';

    protected $casts = [
        'date' => 'date:Y-m-d',
        'has_logged' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public static function make(Carbon|string $date, ?Month $month = null)
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        if (! $month) {
            $month = Month::where('year', $carbonDate->year)->where('month', $carbonDate->month)->firstOrFail();
        }

        $users = Cache::rememberForever(User::CACHE_KEY, fn () => User::where('is_active', true)->get(['id', 'name', 'email']));
        $lunchDefault = $carbonDate->isFriday() ? 2 : 1;

        foreach ($users as $user) {
            self::create([
                'user_id' => $user->id,
                'month_id' => $month->id,
                'date' => $carbonDate->toDateString(),
                'breakfast' => 0,
                'lunch' => $lunchDefault,
                'dinner' => 1,
            ]);
        }
    }

    public static function logToday(): int
    {
        $activeUserIds = User::where('is_active', true)->pluck('id');

        return self::where('date', now()->toDateString())
            ->whereIn('user_id', $activeUserIds)
            ->where('has_logged', false)
            ->update(['has_logged' => true]);
    }

    public function optInBreakfast(): void
    {
        if ($this->breakfast !== 0) {
            throw new \RuntimeException('Breakfast is already opted in. Any further changes require manual edit.');
        }

        $this->update(['breakfast' => 1]);
    }

    public function optOutLunch(): void
    {
        $cutoff = Carbon::parse($this->date)->setTime(5, 0, 0);

        if (! now()->lessThan($cutoff)) {
            throw new \RuntimeException('Too late to opt out of lunch');
        }

        $this->update(['lunch' => 0]);
    }

    public function optOutDinner(): void
    {
        $cutoff = Carbon::parse($this->date)->setTime(14, 20, 0);

        if (! now()->lessThan($cutoff)) {
            throw new \RuntimeException('Too late to opt out of dinner');
        }

        $this->update(['dinner' => 0]);
    }
}

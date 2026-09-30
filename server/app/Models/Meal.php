<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Meal extends Model
{
    public const LUNCH_OPT_CUTOFF = '5:00 AM';

    public const DINNER_OPT_CUTOFF = '2:20 PM';

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public static function make(Carbon $date, ?Month $month = null)
    {
        if (! $month) {
            $month = Month::where('year', $date->year)->where('month', $date->month)->firstOrFail();
        }

        $users = Cache::rememberForever(User::CACHE_KEY, fn () => User::where('is_active', true)->get(['id', 'name', 'email']));

        foreach ($users as $user) {
            self::create([
                'user_id' => $user->id,
                'month_id' => $month->id,
                'date' => $date,
            ]);
        }
    }

    public static function logToday()
    {
        self::where('date', now()->startOfDay())->update(['has_logged' => true]);
    }

    public function optOutLunch()
    {
        $cutoff = $this->date->copy()->setTimeFromTimeString(self::LUNCH_OPT_CUTOFF);

        if (! now()->lessThan($cutoff)) {
            throw new \Exception('Too late to opt out of lunch');
        }

        $this->update(['lunch' => 0]);
    }

    public function optOutDinner()
    {
        $cutoff = $this->date->copy()->setTimeFromTimeString(self::DINNER_OPT_CUTOFF);

        if (! now()->lessThan($cutoff)) {
            throw new \Exception('Too late to opt out of dinner');
        }

        $this->update(['dinner' => 0]);
    }
}

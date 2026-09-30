<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CACHE_KEY = 'users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function booted()
    {
        parent::booted();

        static::saved(function (self $user) {
            Cache::forget(self::CACHE_KEY);
            Cache::forever(self::CACHE_KEY, self::where('is_active', true)->get(['id', 'name', 'email']));
        });

        static::deleted(function (self $user) {
            Cache::forget(self::CACHE_KEY);
            Cache::forever(self::CACHE_KEY, self::where('is_active', true)->get(['id', 'name', 'email']));
        });
    }

    /**
     * Where the Expo channel sends this user's notifications.
     *
     * @return Collection<int, ExpoPushToken>
     */
    public function routeNotificationForExpo(): Collection
    {
        return $this->deviceTokens->pluck('token');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
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
}

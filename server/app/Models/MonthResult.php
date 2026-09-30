<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class MonthResult extends Model
{
    #[Override]
    public static function booted()
    {
        parent::booted();

        static::created(function (self $result) {
            $users = User::where('is_active', true)->get();

            foreach ($users as $user) {
                ParticipantResult::calculate($result, $user);
            }
        });

        static::updated(function (self $result) {
            $users = User::where('is_active', true)->get();

            foreach ($users as $user) {
                ParticipantResult::calculate($result, $user);
            }
        });
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ParticipantResult::class);
    }
}

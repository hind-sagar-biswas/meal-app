<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NotificationChannels\Expo\ExpoPushToken;

class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token'];

    protected function casts(): array
    {
        return ['token' => ExpoPushToken::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushTickets(): HasMany
    {
        return $this->hasMany(PushTicket::class);
    }

}

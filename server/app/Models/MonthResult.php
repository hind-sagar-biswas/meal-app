<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthResult extends Model
{
    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }
}

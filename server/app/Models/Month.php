<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Month extends Model
{
    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
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

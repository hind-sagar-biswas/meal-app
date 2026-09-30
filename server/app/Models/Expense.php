<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Expense extends Model
{
    protected $casts = [
        'date' => 'date',
        'is_grouped' => 'boolean',
    ];

    public static function booted()
    {
        parent::booted();

        static::creating(function (self $expense) {
            if (! $expense->user_id) {
                if (! Auth::check()) {
                    throw new \Exception('User not found');
                }
                $expense->user_id = Auth::id();
            }

            if (! $expense->date) {
                $expense->date = now();
            }

            if (! $expense->month_id) {
                $month = Month::findFromDate($expense->date);
                if (! $month) {
                    throw new \Exception('Month not found for the provided date!');
                }
                if ($month->is_closed) {
                    throw new \Exception('Closed month cannot accept new expenses!');
                }
                $expense->month_id = $month->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(ExpenseContribution::class);
    }
}

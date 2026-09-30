<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class ExpenseContribution extends Model
{
    use HasFactory;
    #[Override]
    public static function booted()
    {
        parent::booted();

        static::creating(function (self $contribution) {
            $contribution->month_id = $contribution->expense->month_id;
        });
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

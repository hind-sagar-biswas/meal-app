<?php

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Month;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('auto assigns authenticated user_id, date, and month_id when creating an expense', function () {
    $user = User::factory()->create();
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
        'is_closed' => false,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00'));
    Auth::login($user);

    $expense = Expense::create([
        'cause' => 'Weekly Groceries',
        'is_grouped' => false,
        'amount' => 1500,
    ]);

    expect($expense->user_id)->toBe($user->id)
        ->and($expense->month_id)->toBe($month->id)
        ->and($expense->date->toDateString())->toBe('2026-05-15')
        ->and($expense->is_grouped)->toBeFalse();

    Carbon::setTestNow();
});

test('throws exception when creating an expense for a closed month', function () {
    $user = User::factory()->create();
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 4,
        'is_closed' => true,
    ]);

    Auth::login($user);

    expect(fn () => Expense::create([
        'date' => '2026-04-10',
        'cause' => 'Late Grocery Entry',
        'amount' => 500,
    ]))->toThrow(Exception::class, 'Closed month cannot accept new expenses!');
});

test('throws exception when creating expense without authentication and without user_id', function () {
    Month::factory()->create(['year' => 2026, 'month' => 5]);

    expect(fn () => Expense::create([
        'date' => '2026-05-10',
        'cause' => 'Unauthenticated expense',
        'amount' => 500,
    ]))->toThrow(Exception::class, 'User not found');
});

test('throws exception when month does not exist for expense date', function () {
    $user = User::factory()->create();
    Auth::login($user);

    expect(fn () => Expense::create([
        'date' => '2026-12-01', // Month not created in DB
        'cause' => 'Far Future Expense',
        'amount' => 500,
    ]))->toThrow(Exception::class, 'Month not found for the provided date!');
});

test('automatically assigns month_id to expense contribution from parent expense', function () {
    $user = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'month_id' => $month->id,
        'date' => '2026-05-05',
        'cause' => 'Kitchen Utensils',
        'is_grouped' => true,
        'amount' => 800,
    ]);

    $contribution = ExpenseContribution::create([
        'expense_id' => $expense->id,
        'user_id' => $user->id,
        'amount' => 800,
    ]);

    expect($contribution->month_id)->toBe($month->id)
        ->and($contribution->expense->id)->toBe($expense->id)
        ->and($contribution->user->id)->toBe($user->id);
});

test('prevents duplicate contribution row for same user on same expense', function () {
    $user = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'month_id' => $month->id,
        'date' => '2026-05-05',
        'cause' => 'Spice Rack',
        'is_grouped' => true,
        'amount' => 600,
    ]);

    ExpenseContribution::create([
        'expense_id' => $expense->id,
        'user_id' => $user->id,
        'amount' => 300,
    ]);

    expect(fn () => ExpenseContribution::create([
        'expense_id' => $expense->id,
        'user_id' => $user->id,
        'amount' => 300,
    ]))->toThrow(QueryException::class);
});

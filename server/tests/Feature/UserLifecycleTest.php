<?php

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('caches active users and refreshes on save, update and delete', function () {
    Cache::flush();

    // 1. Create active user
    $user1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $cached = Cache::get(User::CACHE_KEY);

    expect($cached)->not->toBeNull()
        ->and($cached->pluck('id'))->toContain($user1->id);

    // 2. Create inactive user - should not be in active cache
    $user2 = User::factory()->create(['name' => 'Bob', 'is_active' => false]);
    $cached = Cache::get(User::CACHE_KEY);

    expect($cached->pluck('id'))->not->toContain($user2->id);

    // 3. Update user to inactive - should be removed from cache
    $user1->update(['is_active' => false]);
    $cached = Cache::get(User::CACHE_KEY);

    expect($cached->pluck('id'))->not->toContain($user1->id);

    // 4. Delete user - should update cache
    $user3 = User::factory()->create(['name' => 'Charlie', 'is_active' => true]);
    expect(Cache::get(User::CACHE_KEY)->pluck('id'))->toContain($user3->id);

    $user3->delete();
    expect(Cache::get(User::CACHE_KEY)->pluck('id'))->not->toContain($user3->id);
});

test('user relationships can be queried properly', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // Meals relation (auto-created by month)
    expect($user->meals)->not->toBeEmpty();

    // Expenses relation
    $expense = Expense::create([
        'user_id' => $user->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'cause' => 'Bazar',
        'is_grouped' => false,
        'amount' => 500,
    ]);
    expect($user->expenses->pluck('id'))->toContain($expense->id);

    // Contributions relation
    $contribution = ExpenseContribution::create([
        'expense_id' => $expense->id,
        'user_id' => $user->id,
        'amount' => 500,
    ]);
    expect($user->contributions->pluck('id'))->toContain($contribution->id);
});

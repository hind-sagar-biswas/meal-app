<?php

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Meal;
use App\Models\Month;
use App\Models\MonthResult;
use App\Models\ParticipantResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calculates the worked example correctly and verifies mathematical invariants', function () {
    // 1. Setup 3 active users
    $userA = User::factory()->create(['name' => 'Member A', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Member B', 'is_active' => true]);
    $userC = User::factory()->create(['name' => 'Member C', 'is_active' => true]);

    // 2. Create month
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
        'breakfast_price' => 20,
    ]);

    // Remove auto-generated meals to accurately set exact test counts
    Meal::where('month_id', $month->id)->delete();

    // User A: BF = 10, Lunch+Dinner = 50
    Meal::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'breakfast' => 10,
        'lunch' => 30,
        'dinner' => 20,
        'has_logged' => true,
    ]);

    // User B: BF = 0, Lunch+Dinner = 40
    Meal::create([
        'user_id' => $userB->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'breakfast' => 0,
        'lunch' => 20,
        'dinner' => 20,
        'has_logged' => true,
    ]);

    // User C: BF = 5, Lunch+Dinner = 30
    Meal::create([
        'user_id' => $userC->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'breakfast' => 5,
        'lunch' => 15,
        'dinner' => 15,
        'has_logged' => true,
    ]);

    // 3. Create Bazar Expenses (is_grouped = false, total = 6000)
    // Expense 1: 3000 by User A
    $expA = Expense::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-02',
        'cause' => 'Meat and Fish',
        'is_grouped' => false,
        'amount' => 3000,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expA->id,
        'month_id' => $month->id,
        'user_id' => $userA->id,
        'amount' => 3000,
    ]);

    // Expense 2: 2000 by User B
    $expB = Expense::create([
        'user_id' => $userB->id,
        'month_id' => $month->id,
        'date' => '2026-05-03',
        'cause' => 'Vegetables and Oil',
        'is_grouped' => false,
        'amount' => 2000,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expB->id,
        'month_id' => $month->id,
        'user_id' => $userB->id,
        'amount' => 2000,
    ]);

    // Expense 3: 1000 by User C
    $expC = Expense::create([
        'user_id' => $userC->id,
        'month_id' => $month->id,
        'date' => '2026-05-04',
        'cause' => 'Spices and Rice',
        'is_grouped' => false,
        'amount' => 1000,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expC->id,
        'month_id' => $month->id,
        'user_id' => $userC->id,
        'amount' => 1000,
    ]);

    // 4. Create Group Expenses (is_grouped = true, total = 900)
    // Multi-payer Expense: 900 total (User A: 600, User B: 300)
    $expGroup = Expense::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-05',
        'cause' => 'Gas Cylinder',
        'is_grouped' => true,
        'amount' => 900,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expGroup->id,
        'month_id' => $month->id,
        'user_id' => $userA->id,
        'amount' => 600,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expGroup->id,
        'month_id' => $month->id,
        'user_id' => $userB->id,
        'amount' => 300,
    ]);

    // 5. Close month
    $result = $month->close($userA);

    // 6. Assert MonthResult figures
    expect($result->participant_count)->toBe(3)
        ->and($result->breakfast_count)->toBe(15)
        ->and($result->meal_count)->toBe(120)
        ->and($result->bazar_expense)->toBe(6000)
        ->and($result->grouped_expense)->toBe(900)
        ->and($result->total_expense)->toBe(6900)
        ->and($result->breakfast_expense)->toBe(300)
        ->and($result->meal_expense)->toBe(5700)
        ->and($result->meal_rate)->toBe(47_500_000) // 47.5 * 1,000,000
        ->and($result->group_expense_per_person)->toBe(300_000_000); // 300 * 1,000,000

    // 7. Assert Participant Results for User A, B, C
    $resA = ParticipantResult::where('month_result_id', $result->id)->where('user_id', $userA->id)->first();
    $resB = ParticipantResult::where('month_result_id', $result->id)->where('user_id', $userB->id)->first();
    $resC = ParticipantResult::where('month_result_id', $result->id)->where('user_id', $userC->id)->first();

    // Member A: expense = 2875, contribution = 3600 (3000 + 600), adjustment = -725 (gets refund)
    expect($resA->breakfast_expense)->toBe(200)
        ->and($resA->total_expense)->toBe(2875)
        ->and($resA->total_contribution)->toBe(3600)
        ->and($resA->adjustment)->toBe(-725);

    // Member B: expense = 2200, contribution = 2300 (2000 + 300), adjustment = -100 (gets refund)
    expect($resB->breakfast_expense)->toBe(0)
        ->and($resB->total_expense)->toBe(2200)
        ->and($resB->total_contribution)->toBe(2300)
        ->and($resB->adjustment)->toBe(-100);

    // Member C: expense = 1825, contribution = 1000, adjustment = +825 (needs to pay)
    expect($resC->breakfast_expense)->toBe(100)
        ->and($resC->total_expense)->toBe(1825)
        ->and($resC->total_contribution)->toBe(1000)
        ->and($resC->adjustment)->toBe(825);

    // 8. Assert System Invariants
    // Sum of expenses == Total Expense
    expect($resA->total_expense + $resB->total_expense + $resC->total_expense)->toBe(6900);
    // Sum of adjustments == 0
    expect($resA->adjustment + $resB->adjustment + $resC->adjustment)->toBe(0);
});

test('handles closing a month with zero meals without error', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 6,
    ]);

    Meal::where('month_id', $month->id)->delete();

    $result = $month->close($user);

    expect($result->meal_count)->toBe(0)
        ->and($result->meal_rate)->toBe(0)
        ->and($month->is_closed)->toBeTrue();
});

test('allows reopening a month within the 6 hour window', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 7]);

    $month->close($user);
    expect($month->is_closed)->toBeTrue();

    // Travel 2 hours forward
    Carbon::setTestNow(now()->addHours(2));

    $month->open();
    expect($month->is_closed)->toBeFalse()
        ->and($month->closed_by)->toBeNull()
        ->and($month->closed_at)->toBeNull();

    Carbon::setTestNow();
});

test('prevents reopening a month after the 6 hour window has passed', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 8]);

    $month->close($user);

    // Travel 7 hours forward
    Carbon::setTestNow(now()->addHours(7));

    expect(fn () => $month->open())
        ->toThrow(Exception::class, 'The deadline to open the month has passed!');

    Carbon::setTestNow();
});

test('prevents closing an already closed month', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 9]);

    $month->close($user);

    expect(fn () => $month->close($user))
        ->toThrow(Exception::class, 'Month is already closed!');
});

test('prevents opening a month that is not closed', function () {
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    expect(fn () => $month->open())
        ->toThrow(Exception::class, 'Month is not closed!');
});

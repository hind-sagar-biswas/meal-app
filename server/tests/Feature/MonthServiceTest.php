<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use App\Notifications\MonthClosedNotification;
use App\Notifications\MonthReopenedNotification;
use App\Services\MonthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MonthService;
});

test('getLiveSummary calculates live ongoing month numbers before closing', function () {
    $userA = User::factory()->create(['name' => 'Member A', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Member B', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'breakfast_price' => 20]);

    // Clean auto-created meals
    Meal::where('month_id', $month->id)->delete();

    // User A: BF=5, LC=20, DN=20 (meal count = 40)
    Meal::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'breakfast' => 5,
        'lunch' => 20,
        'dinner' => 20,
        'has_logged' => true,
    ]);

    // User B: BF=0, LC=10, DN=10 (meal count = 20)
    Meal::create([
        'user_id' => $userB->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'breakfast' => 0,
        'lunch' => 10,
        'dinner' => 10,
        'has_logged' => true,
    ]);

    // Expenses:
    // Bazar expense: 3100 (BF cost = 5 * 20 = 100, Meal cost = 3000 -> Meal rate = 3000 / 60 = 50.0)
    $expBazar = Expense::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-02',
        'cause' => 'Bazar',
        'is_grouped' => false,
        'amount' => 3100,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expBazar->id,
        'month_id' => $month->id,
        'user_id' => $userA->id,
        'amount' => 3100,
    ]);

    // Group expense: 600 (Group per person = 600 / 2 = 300.0)
    $expGroup = Expense::create([
        'user_id' => $userB->id,
        'month_id' => $month->id,
        'date' => '2026-05-03',
        'cause' => 'Utilities',
        'is_grouped' => true,
        'amount' => 600,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expGroup->id,
        'month_id' => $month->id,
        'user_id' => $userB->id,
        'amount' => 600,
    ]);

    $summary = $this->service->getLiveSummary($month);

    expect($summary['totals']['participant_count'])->toBe(2)
        ->and($summary['totals']['breakfast_count'])->toBe(5)
        ->and($summary['totals']['meal_count'])->toBe(60)
        ->and($summary['totals']['bazar_expense'])->toBe(3100)
        ->and($summary['totals']['grouped_expense'])->toBe(600)
        ->and($summary['totals']['total_expense'])->toBe(3700)
        ->and($summary['totals']['breakfast_expense'])->toBe(100)
        ->and($summary['totals']['meal_expense'])->toBe(3000)
        ->and($summary['totals']['meal_rate'])->toEqual(50.0)
        ->and($summary['totals']['group_expense_per_person'])->toEqual(300.0);

    // Member A: BF=100, Meal=40*50=2000, Group=300 -> Total Exp=2400. Contrib=3100 -> Adj = -700 (refund)
    $memA = collect($summary['members'])->firstWhere('user_id', $userA->id);
    expect($memA['total_expense'])->toBe(2400)
        ->and($memA['total_contribution'])->toBe(3100)
        ->and($memA['adjustment'])->toBe(-700);

    // Member B: BF=0, Meal=20*50=1000, Group=300 -> Total Exp=1300. Contrib=600 -> Adj = +700 (to pay)
    $memB = collect($summary['members'])->firstWhere('user_id', $userB->id);
    expect($memB['total_expense'])->toBe(1300)
        ->and($memB['total_contribution'])->toBe(600)
        ->and($memB['adjustment'])->toBe(700);
});

test('closeMonth snapshots results, records AuditLog, and broadcasts MonthClosedNotification', function () {
    Notification::fake();

    $user = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $result = $this->service->closeMonth($month, $user);

    expect($month->fresh()->is_closed)->toBeTrue()
        ->and($result->id)->not->toBeNull()
        ->and($result->month_id)->toBe($month->id);

    $audit = AuditLog::where('action', 'month.close')->where('auditable_id', $month->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($user->id);

    Notification::assertSentTo($user, MonthClosedNotification::class);
});

test('reopenMonth reopens within 6 hours, records AuditLog, and broadcasts MonthReopenedNotification', function () {
    Notification::fake();

    $userA = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Tanmay', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $this->service->closeMonth($month, $userA);
    expect($month->fresh()->is_closed)->toBeTrue();

    // Reopen 2 hours later
    Carbon::setTestNow(now()->addHours(2));

    $reopened = $this->service->reopenMonth($month, $userA);
    expect($reopened->is_closed)->toBeFalse()
        ->and($reopened->closed_by)->toBeNull();

    $audit = AuditLog::where('action', 'month.reopen')->where('auditable_id', $month->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($userA->id);

    Notification::assertSentTo($userB, MonthReopenedNotification::class);
    Notification::assertNotSentTo($userA, MonthReopenedNotification::class);

    Carbon::setTestNow();
});

test('reopenMonth throws exception after 6 hours', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $this->service->closeMonth($month, $user);

    // Fast-forward 7 hours
    Carbon::setTestNow(now()->addHours(7));

    expect(fn () => $this->service->reopenMonth($month, $user))
        ->toThrow(RuntimeException::class, 'The deadline to open the month has passed!');

    Carbon::setTestNow();
});

test('setBreakfastPrice updates price, records AuditLog, and rejects negative or closed month', function () {
    $editor = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'breakfast_price' => 20]);

    $updated = $this->service->setBreakfastPrice($month, 25, $editor);
    expect($updated->breakfast_price)->toBe(25);

    $audit = AuditLog::where('action', 'month.set_breakfast_price')->where('auditable_id', $month->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->before['breakfast_price'])->toBe(20)
        ->and($audit->after['breakfast_price'])->toBe(25);

    // Reject negative price
    expect(fn () => $this->service->setBreakfastPrice($month, -5, $editor))
        ->toThrow(InvalidArgumentException::class, 'Breakfast price cannot be negative.');

    // Reject on closed month
    $this->service->closeMonth($month, $editor);
    expect(fn () => $this->service->setBreakfastPrice($month, 30, $editor))
        ->toThrow(RuntimeException::class, 'Cannot update breakfast price for a closed month.');
});

<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Month;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ExpenseService;
});

test('createExpense creates expense with single contributor and records AuditLog', function () {
    $creator = User::factory()->create(['name' => 'Hind']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense = $this->service->createExpense(
        $creator,
        [
            'cause' => 'Weekly Chicken & Fish',
            'amount' => 2500,
            'is_grouped' => false,
            'date' => '2026-05-04',
            'note' => 'Bought from local bazaar',
        ],
        [
            ['user_id' => $creator->id, 'amount' => 2500],
        ]
    );

    expect($expense->id)->not->toBeNull()
        ->and($expense->cause)->toBe('Weekly Chicken & Fish')
        ->and($expense->amount)->toBe(2500)
        ->and($expense->is_grouped)->toBeFalse()
        ->and($expense->contributions->count())->toBe(1)
        ->and($expense->contributions->first()->amount)->toBe(2500)
        ->and($expense->contributions->first()->user_id)->toBe($creator->id);

    // Verify AuditLog
    $audit = AuditLog::where('action', 'expense.create')->where('auditable_id', $expense->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($creator->id)
        ->and($audit->note)->toBe('Bought from local bazaar');
});

test('createExpense creates multi-payer shared expense with multiple contributions', function () {
    $creator = User::factory()->create(['name' => 'Hind']);
    $userB = User::factory()->create(['name' => 'Tanmay']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // 1200 total shared: Hind paid 700, Tanmay paid 500
    $expense = $this->service->createExpense(
        $creator,
        [
            'cause' => 'Gas Cylinder Refill',
            'amount' => 1200,
            'is_grouped' => true,
            'date' => '2026-05-06',
        ],
        [
            ['user_id' => $creator->id, 'amount' => 700],
            ['user_id' => $userB->id, 'amount' => 500],
        ]
    );

    expect($expense->amount)->toBe(1200)
        ->and($expense->is_grouped)->toBeTrue()
        ->and($expense->contributions->count())->toBe(2)
        ->and($expense->contributions->sum('amount'))->toBe(1200);
});

test('createExpense rejects when contribution sum does not equal total amount', function () {
    $creator = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    expect(fn () => $this->service->createExpense(
        $creator,
        ['cause' => 'Groceries', 'amount' => 1000, 'date' => '2026-05-05'],
        [['user_id' => $creator->id, 'amount' => 900]] // sum is 900, amount is 1000
    ))->toThrow(InvalidArgumentException::class, 'The sum of contributions (900) must equal the total expense amount (1000).');
});

test('createExpense rejects duplicate contributor user in same expense', function () {
    $creator = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    expect(fn () => $this->service->createExpense(
        $creator,
        ['cause' => 'Groceries', 'amount' => 1000, 'date' => '2026-05-05'],
        [
            ['user_id' => $creator->id, 'amount' => 500],
            ['user_id' => $creator->id, 'amount' => 500],
        ]
    ))->toThrow(InvalidArgumentException::class, 'Duplicate contributor in the same expense.');
});

test('createExpense rejects empty cause or non-positive amount', function () {
    $creator = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    expect(fn () => $this->service->createExpense(
        $creator,
        ['cause' => '', 'amount' => 500, 'date' => '2026-05-05'],
        [['user_id' => $creator->id, 'amount' => 500]]
    ))->toThrow(InvalidArgumentException::class, 'Expense cause is required.');

    expect(fn () => $this->service->createExpense(
        $creator,
        ['cause' => 'Oil', 'amount' => 0, 'date' => '2026-05-05'],
        [['user_id' => $creator->id, 'amount' => 0]]
    ))->toThrow(InvalidArgumentException::class, 'Expense amount must be greater than zero.');
});

test('createExpense rejects creating an expense in a closed month', function () {
    $creator = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'is_closed' => true]);

    expect(fn () => $this->service->createExpense(
        $creator,
        ['cause' => 'Late Entry', 'amount' => 500, 'date' => '2026-05-05'],
        [['user_id' => $creator->id, 'amount' => 500]]
    ))->toThrow(RuntimeException::class, 'Cannot add expenses to a closed month.');
});

test('createAdjustmentExpense creates compensating entry with AuditLog', function () {
    $creator = User::factory()->create(['name' => 'Hind']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $original = $this->service->createExpense(
        $creator,
        ['cause' => 'Dining Table Mat', 'amount' => 600, 'is_grouped' => true, 'date' => '2026-05-05'],
        [['user_id' => $creator->id, 'amount' => 600]]
    );

    // Adjustment: -100 refund / price discount
    $adjustment = $this->service->createAdjustmentExpense(
        $original,
        $creator,
        -100,
        [['user_id' => $creator->id, 'amount' => -100]],
        'Shopkeeper returned 100 tk overcharge'
    );

    expect($adjustment->amount)->toBe(-100)
        ->and($adjustment->cause)->toContain('Adjustment: Dining Table Mat')
        ->and($adjustment->contributions->first()->amount)->toBe(-100);

    $audit = AuditLog::where('action', 'expense.adjustment')->where('auditable_id', $adjustment->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->note)->toBe('Shopkeeper returned 100 tk overcharge');
});

test('getMonthExpenses and getUserContributionsSummary query accurately', function () {
    $userA = User::factory()->create(['name' => 'Hind']);
    $userB = User::factory()->create(['name' => 'Tanmay']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // Bazar expense: 1500 (Hind paid 1500)
    $this->service->createExpense(
        $userA,
        ['cause' => 'Bazar 1', 'amount' => 1500, 'is_grouped' => false, 'date' => '2026-05-02'],
        [['user_id' => $userA->id, 'amount' => 1500]]
    );

    // Group expense: 800 (Hind paid 400, Tanmay paid 400)
    $this->service->createExpense(
        $userA,
        ['cause' => 'Wifi Bill', 'amount' => 800, 'is_grouped' => true, 'date' => '2026-05-03'],
        [
            ['user_id' => $userA->id, 'amount' => 400],
            ['user_id' => $userB->id, 'amount' => 400],
        ]
    );

    $allExpenses = $this->service->getMonthExpenses($month);
    $bazarOnly = $this->service->getMonthExpenses($month, isGrouped: false);
    $groupOnly = $this->service->getMonthExpenses($month, isGrouped: true);

    expect($allExpenses->count())->toBe(2)
        ->and($bazarOnly->count())->toBe(1)
        ->and($groupOnly->count())->toBe(1);

    $summaryA = $this->service->getUserContributionsSummary($month, $userA);
    expect($summaryA['bazar_contribution'])->toBe(1500)
        ->and($summaryA['group_contribution'])->toBe(400)
        ->and($summaryA['total_contribution'])->toBe(1900)
        ->and($summaryA['contributions_count'])->toBe(2);

    $summaryB = $this->service->getUserContributionsSummary($month, $userB);
    expect($summaryB['bazar_contribution'])->toBe(0)
        ->and($summaryB['group_contribution'])->toBe(400)
        ->and($summaryB['total_contribution'])->toBe(400)
        ->and($summaryB['contributions_count'])->toBe(1);
});

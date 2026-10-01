<?php

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can list all months and get current month', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month1 = Month::factory()->create(['year' => 2026, 'month' => 4, 'is_closed' => true]);
    $month2 = Month::factory()->create(['year' => 2026, 'month' => 5, 'is_closed' => false]);

    Sanctum::actingAs($user);

    // 1. List months
    $response = $this->getJson(route('months.index'));
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'year', 'month', 'is_closed', 'breakfast_price', 'created_at'],
            ],
        ]);
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(2);

    // 2. Get current month
    $resCurrent = $this->getJson(route('months.current'));
    $resCurrent->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'year', 'month', 'is_closed', 'breakfast_price'],
        ]);
});

test('user can get live calculation preview of an open month', function () {
    $userA = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'breakfast_price' => 20]);

    // Log some meals
    Meal::where('month_id', $month->id)->where('user_id', $userA->id)->limit(10)->update([
        'has_logged' => true,
        'breakfast' => 1,
        'lunch' => 1,
        'dinner' => 1,
    ]);
    Meal::where('month_id', $month->id)->where('user_id', $userB->id)->limit(10)->update([
        'has_logged' => true,
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    // Log an expense
    $expense = Expense::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-02',
        'cause' => 'Bazar',
        'is_grouped' => false,
        'amount' => 4000,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expense->id,
        'month_id' => $month->id,
        'user_id' => $userA->id,
        'amount' => 4000,
    ]);

    Sanctum::actingAs($userA);

    $response = $this->getJson(route('months.live-summary', $month->id));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'month' => ['id', 'year', 'month', 'is_closed', 'breakfast_price'],
                'totals' => [
                    'participant_count',
                    'breakfast_count',
                    'meal_count',
                    'total_expense',
                    'bazar_expense',
                    'grouped_expense',
                    'breakfast_expense',
                    'meal_expense',
                    'meal_rate',
                    'group_expense_per_person',
                ],
                'members' => [
                    '*' => [
                        'user_id',
                        'name',
                        'breakfast_count',
                        'meal_count',
                        'total_expense',
                        'total_contribution',
                        'adjustment',
                    ],
                ],
            ],
        ]);
});

test('user cannot view closed results if month is not closed', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'is_closed' => false]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('months.results', $month->id));
    $response->assertBadRequest()
        ->assertJson(['message' => 'Month is not closed yet.']);
});

test('authenticated user can close month and view snapshot results', function () {
    $manager = User::factory()->create(['name' => 'Manager', 'is_active' => true]);
    $member = User::factory()->create(['name' => 'Member', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'breakfast_price' => 20]);

    // Setup meals & expenses
    Meal::where('month_id', $month->id)->update(['has_logged' => true]);

    $expense = Expense::create([
        'user_id' => $manager->id,
        'month_id' => $month->id,
        'date' => '2026-05-01',
        'cause' => 'Bazar',
        'is_grouped' => false,
        'amount' => 6000,
    ]);
    ExpenseContribution::create([
        'expense_id' => $expense->id,
        'month_id' => $month->id,
        'user_id' => $manager->id,
        'amount' => 6000,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->postJson(route('months.close', $month->id));

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'month_id',
                'participant_count',
                'meal_rate',
                'participants' => [
                    '*' => ['user_id', 'user_name', 'total_expense', 'total_contribution', 'adjustment', 'status'],
                ],
            ],
        ])
        ->assertJson(['message' => 'Month closed successfully.']);

    expect($month->fresh()->is_closed)->toBeTrue();

    // Query closed results
    $resResults = $this->getJson(route('months.results', $month->id));
    $resResults->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'month_id', 'meal_rate', 'participants'],
        ]);
});

test('idempotency key replays exact same month closure without throwing already closed error', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $idempotencyKey = (string) Str::uuid();

    Meal::where('month_id', $month->id)->update(['has_logged' => true]);

    Sanctum::actingAs($manager);

    // First close
    $res1 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->postJson(route('months.close', $month->id));
    $res1->assertOk();

    // Replay
    $res2 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->postJson(route('months.close', $month->id));
    $res2->assertOk()
        ->assertHeader('X-Idempotent-Replay', 'true');
});

test('user can reopen a closed month within 6 hours and cannot reopen after 6 hours', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
        'is_closed' => true,
        'closed_at' => now()->subHours(2), // 2 hours ago (within 6h window)
    ]);

    Sanctum::actingAs($manager);

    // Reopen within window
    $response = $this->postJson(route('months.reopen', $month->id));
    $response->assertOk()
        ->assertJson(['message' => 'Month reopened successfully.']);

    expect($month->fresh()->is_closed)->toBeFalse();

    // Fast-forward expired month
    $expiredMonth = Month::factory()->create([
        'year' => 2026,
        'month' => 4,
        'is_closed' => true,
        'closed_at' => now()->subHours(7), // 7 hours ago (expired)
    ]);

    $expiredRes = $this->postJson(route('months.reopen', $expiredMonth->id));
    $expiredRes->assertBadRequest()
        ->assertJson(['message' => 'The deadline to open the month has passed!']);
});

test('user can update unit breakfast price for an open month', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'breakfast_price' => 20, 'is_closed' => false]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('months.breakfast-price', $month->id), [
        'breakfast_price' => 25,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Breakfast price updated successfully.',
            'data' => [
                'breakfast_price' => 25,
            ],
        ]);

    expect($month->fresh()->breakfast_price)->toBe(25);
});

test('unauthenticated user cannot access month endpoints', function () {
    $this->getJson(route('months.index'))->assertUnauthorized();
    $this->getJson(route('months.current'))->assertUnauthorized();
    $this->getJson(route('months.live-summary', 1))->assertUnauthorized();
    $this->getJson(route('months.results', 1))->assertUnauthorized();
    $this->postJson(route('months.close', 1))->assertUnauthorized();
    $this->postJson(route('months.reopen', 1))->assertUnauthorized();
    $this->patchJson(route('months.breakfast-price', 1), [])->assertUnauthorized();
});

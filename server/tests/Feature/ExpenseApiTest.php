<?php

use App\Models\Expense;
use App\Models\ExpenseContribution;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can create a multi-payer expense', function () {
    $creator = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $payer1 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    Sanctum::actingAs($creator);

    $response = $this->postJson(route('expenses.store'), [
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Bazar - Meat and Veggies',
        'is_grouped' => false,
        'amount' => 3000,
        'note' => 'Bazar bought together',
        'contributions' => [
            ['user_id' => $creator->id, 'amount' => 2000],
            ['user_id' => $payer1->id, 'amount' => 1000],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'month_id',
                'date',
                'cause',
                'note',
                'is_grouped',
                'amount',
                'user' => ['id', 'name'],
                'contributions' => [
                    '*' => ['id', 'user_id', 'user_name', 'amount'],
                ],
                'created_at',
            ],
        ])
        ->assertJson([
            'data' => [
                'amount' => 3000,
                'cause' => 'Bazar - Meat and Veggies',
                'is_grouped' => false,
            ],
        ]);

    expect(Expense::count())->toBe(1)
        ->and(ExpenseContribution::count())->toBe(2);
});

test('expense creation fails when contributions sum does not match total amount', function () {
    $creator = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    Sanctum::actingAs($creator);

    $response = $this->postJson(route('expenses.store'), [
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Bazar mismatch',
        'amount' => 3000,
        'contributions' => [
            ['user_id' => $creator->id, 'amount' => 2500], // 2500 != 3000
        ],
    ]);

    $response->assertBadRequest();
    expect(Expense::count())->toBe(0);
});

test('idempotency key prevents duplicate expense creation on client retries', function () {
    $creator = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $idempotencyKey = (string) Str::uuid();

    Sanctum::actingAs($creator);

    $payload = [
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Bazar - Chicken',
        'amount' => 1200,
        'contributions' => [
            ['user_id' => $creator->id, 'amount' => 1200],
        ],
    ];

    // 1. First Request
    $res1 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->postJson(route('expenses.store'), $payload);

    $res1->assertCreated();
    $firstExpenseId = $res1->json('data.id');

    // 2. Second Request with same Idempotency key (replay)
    $res2 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
        ->postJson(route('expenses.store'), $payload);

    $res2->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    expect($res2->json('data.id'))->toBe($firstExpenseId)
        ->and(Expense::count())->toBe(1);
});

test('authenticated user can create compensating adjustment for an existing expense', function () {
    $creator = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense = Expense::create([
        'user_id' => $creator->id,
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Original Bazar',
        'is_grouped' => false,
        'amount' => 2000,
    ]);

    Sanctum::actingAs($creator);

    $response = $this->postJson(route('expenses.adjust', $expense->id), [
        'amount' => -200,
        'reason' => 'Vendor refunded 200 tk',
        'contributions' => [
            ['user_id' => $creator->id, 'amount' => -200],
        ],
    ]);

    $response->assertCreated()
        ->assertJson([
            'data' => [
                'amount' => -200,
                'note' => 'Vendor refunded 200 tk',
                'is_grouped' => false,
            ],
        ]);

    expect(Expense::count())->toBe(2);
});

test('user can fetch paginated expense list with filters', function () {
    $user1 = User::factory()->create(['name' => 'User1', 'is_active' => true]);
    $user2 = User::factory()->create(['name' => 'User2', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // Bazar expense
    Expense::create([
        'user_id' => $user1->id,
        'month_id' => $month->id,
        'date' => '2026-05-05',
        'cause' => 'Bazar 1',
        'is_grouped' => false,
        'amount' => 1000,
    ]);

    // Group utility expense
    Expense::create([
        'user_id' => $user2->id,
        'month_id' => $month->id,
        'date' => '2026-05-12',
        'cause' => 'WiFi Bill',
        'is_grouped' => true,
        'amount' => 1500,
    ]);

    Sanctum::actingAs($user1);

    // 1. Fetch all
    $resAll = $this->getJson(route('expenses.index', ['month_id' => $month->id]));
    $resAll->assertOk();
    expect($resAll->json('total'))->toBe(2);

    // 2. Filter by is_grouped = true
    $resGrouped = $this->getJson(route('expenses.index', ['month_id' => $month->id, 'is_grouped' => 1]));
    $resGrouped->assertOk();
    expect($resGrouped->json('total'))->toBe(1)
        ->and($resGrouped->json('data.0.cause'))->toBe('WiFi Bill');

    // 3. Filter by user_id
    $resUser = $this->getJson(route('expenses.index', ['month_id' => $month->id, 'user_id' => $user1->id]));
    $resUser->assertOk();
    expect($resUser->json('total'))->toBe(1)
        ->and($resUser->json('data.0.cause'))->toBe('Bazar 1');
});

test('user can fetch single expense details', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'month_id' => $month->id,
        'date' => '2026-05-15',
        'cause' => 'Gas Cylinder',
        'is_grouped' => true,
        'amount' => 1400,
    ]);

    ExpenseContribution::create([
        'expense_id' => $expense->id,
        'month_id' => $month->id,
        'user_id' => $user->id,
        'amount' => 1400,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('expenses.show', $expense->id));

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $expense->id,
                'cause' => 'Gas Cylinder',
                'amount' => 1400,
                'is_grouped' => true,
            ],
        ]);
});

test('user can fetch monthly expense summary with member contributions', function () {
    $userA = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $expense1 = Expense::create([
        'user_id' => $userA->id,
        'month_id' => $month->id,
        'date' => '2026-05-02',
        'cause' => 'Fish Bazar',
        'is_grouped' => false,
        'amount' => 2000,
    ]);

    ExpenseContribution::create([
        'expense_id' => $expense1->id,
        'month_id' => $month->id,
        'user_id' => $userA->id,
        'amount' => 2000,
    ]);

    $expense2 = Expense::create([
        'user_id' => $userB->id,
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Electricity',
        'is_grouped' => true,
        'amount' => 1000,
    ]);

    ExpenseContribution::create([
        'expense_id' => $expense2->id,
        'month_id' => $month->id,
        'user_id' => $userB->id,
        'amount' => 1000,
    ]);

    Sanctum::actingAs($userA);

    $response = $this->getJson(route('expenses.summary', ['month_id' => $month->id]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'month_id',
                'year',
                'month',
                'total_expense',
                'bazar_expense',
                'grouped_expense',
                'member_contributions' => [
                    '*' => ['user_id', 'name', 'email', 'bazar_paid', 'grouped_paid', 'total_paid'],
                ],
            ],
        ])
        ->assertJson([
            'data' => [
                'total_expense' => 3000,
                'bazar_expense' => 2000,
                'grouped_expense' => 1000,
            ],
        ]);
});

test('cannot create expense for a closed month', function () {
    $creator = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'is_closed' => true]);

    Sanctum::actingAs($creator);

    $response = $this->postJson(route('expenses.store'), [
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'cause' => 'Late Bazar',
        'amount' => 500,
        'contributions' => [
            ['user_id' => $creator->id, 'amount' => 500],
        ],
    ]);

    $response->assertBadRequest();
});

test('unauthenticated user cannot access expenses endpoints', function () {
    $this->getJson(route('expenses.index'))->assertUnauthorized();
    $this->getJson(route('expenses.summary'))->assertUnauthorized();
    $this->getJson(route('expenses.show', 1))->assertUnauthorized();
    $this->postJson(route('expenses.store'), [])->assertUnauthorized();
    $this->postJson(route('expenses.adjust', 1), [])->assertUnauthorized();
});

<?php

use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('idempotency key is scoped by method and endpoint path', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    Sanctum::actingAs($user);

    $sharedKey = 'uuid-shared-test-key-1234';

    // Step 1: Expense creation with sharedKey
    $expenseRes = $this->withHeader('X-Idempotency-Key', $sharedKey)
        ->postJson(route('expenses.store'), [
            'month_id' => $month->id,
            'date' => '2026-10-05',
            'cause' => 'Bazar Meat',
            'amount' => 1000,
            'is_grouped' => false,
            'contributions' => [
                ['user_id' => $user->id, 'amount' => 1000],
            ],
        ]);

    $expenseRes->assertCreated();
    expect($expenseRes->headers->has('X-Idempotent-Replay'))->toBeFalse();

    // Replay on same endpoint -> returns replay header and expense data
    $expenseReplay = $this->withHeader('X-Idempotency-Key', $sharedKey)
        ->postJson(route('expenses.store'), [
            'month_id' => $month->id,
            'date' => '2026-10-05',
            'cause' => 'Bazar Meat',
            'amount' => 1000,
            'is_grouped' => false,
            'contributions' => [
                ['user_id' => $user->id, 'amount' => 1000],
            ],
        ]);

    $expenseReplay->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true');

    // Step 2: Now call a DIFFERENT endpoint (meals.day-tally) with the SAME sharedKey
    // It must NOT return the cached expense response!
    $dayTallyRes = $this->withHeader('X-Idempotency-Key', $sharedKey)
        ->postJson(route('meals.day-tally'), [
            'date' => '2026-10-05',
            'breakfast' => 1,
            'lunch' => 1,
            'dinner' => 1,
            'note' => 'Set meals for all',
        ]);

    $dayTallyRes->assertOk()
        ->assertJson([
            'message' => 'Day meal tally updated successfully.',
        ]);
    expect($dayTallyRes->headers->has('X-Idempotent-Replay'))->toBeFalse();
});

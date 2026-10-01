<?php

use App\Models\AuditLog;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can fetch paginated audit logs', function () {
    $editor = User::factory()->create(['name' => 'Editor User', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $editor->id)->first();

    AuditLog::create([
        'user_id' => $editor->id,
        'action' => 'meal.edited',
        'auditable_type' => Meal::class,
        'auditable_id' => $meal->id,
        'before' => ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
        'after' => ['breakfast' => 1, 'lunch' => 1, 'dinner' => 1],
        'note' => 'Guest arrived for breakfast',
    ]);

    Sanctum::actingAs($editor);

    $response = $this->getJson(route('audit-logs.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'action',
                    'user' => ['id', 'name', 'email'],
                    'auditable_type',
                    'auditable_id',
                    'before',
                    'after',
                    'note',
                    'created_at',
                ],
            ],
            'current_page',
            'last_page',
            'per_page',
            'total',
        ])
        ->assertJson([
            'total' => 1,
            'data' => [
                [
                    'action' => 'meal.edited',
                    'auditable_type' => 'Meal',
                    'auditable_id' => $meal->id,
                    'note' => 'Guest arrived for breakfast',
                    'user' => [
                        'id' => $editor->id,
                        'name' => 'Editor User',
                    ],
                ],
            ],
        ]);
});

test('audit logs can be filtered by action, user_id, and auditable_type', function () {
    $user1 = User::factory()->create(['is_active' => true]);
    $user2 = User::factory()->create(['is_active' => true]);

    AuditLog::create([
        'user_id' => $user1->id,
        'action' => 'meal.edited',
        'note' => 'Edit 1',
    ]);

    AuditLog::create([
        'user_id' => $user2->id,
        'action' => 'meal.day_off',
        'note' => 'Cook absent',
    ]);

    AuditLog::create([
        'user_id' => $user1->id,
        'action' => 'expense.adjusted',
        'note' => 'Discount refund',
    ]);

    Sanctum::actingAs($user1);

    // Filter by action
    $resAction = $this->getJson(route('audit-logs.index', ['action' => 'meal.day_off']));
    $resAction->assertOk();
    expect($resAction->json('total'))->toBe(1)
        ->and($resAction->json('data.0.note'))->toBe('Cook absent');

    // Filter by user_id
    $resUser = $this->getJson(route('audit-logs.index', ['user_id' => $user2->id]));
    $resUser->assertOk();
    expect($resUser->json('total'))->toBe(1)
        ->and($resUser->json('data.0.user.id'))->toBe($user2->id);

    // Filter by action + user_id
    $resBoth = $this->getJson(route('audit-logs.index', ['user_id' => $user1->id, 'action' => 'meal.edited']));
    $resBoth->assertOk();
    expect($resBoth->json('total'))->toBe(1)
        ->and($resBoth->json('data.0.note'))->toBe('Edit 1');
});

test('unauthenticated user cannot access audit logs', function () {
    $this->getJson(route('audit-logs.index'))->assertUnauthorized();
});

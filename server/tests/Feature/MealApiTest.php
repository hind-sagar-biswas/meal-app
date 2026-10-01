<?php

use App\Models\AuditLog;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use App\Notifications\DateRangeMealOffNotification;
use App\Notifications\DayMealOffNotification;
use App\Notifications\DayMealTallyUpdatedNotification;
use App\Notifications\MealCountIncreasedNotification;
use App\Notifications\MemberMealEditedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access meal endpoints', function () {
    $this->getJson(route('meals.my-today'))->assertUnauthorized();
    $this->getJson(route('meals.today-summary'))->assertUnauthorized();
    $this->getJson(route('meals.today-members'))->assertUnauthorized();
    $this->getJson(route('meals.today'))->assertUnauthorized();
    $this->getJson(route('meals.sheet'))->assertUnauthorized();
    $this->getJson(route('meals.my-month'))->assertUnauthorized();
});

test('myToday returns today meal with cutoff eligibility flags', function () {
    $user = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    // Set time to 4:00 AM on 2026-10-01
    Carbon::setTestNow(Carbon::parse('2026-10-01 04:00:00'));

    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
        'has_logged' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('meals.my-today'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'server_time',
                'date',
                'is_month_closed',
                'cutoffs' => [
                    'breakfast_cutoff',
                    'lunch_cutoff',
                    'dinner_cutoff',
                    'breakfast_opt_in_allowed',
                    'lunch_opt_out_allowed',
                    'dinner_opt_out_allowed',
                ],
                'meal' => [
                    'id',
                    'user_id',
                    'date',
                    'breakfast',
                    'lunch',
                    'dinner',
                    'total',
                    'has_logged',
                    'is_editable',
                ],
            ],
        ])
        ->assertJson([
            'data' => [
                'date' => '2026-10-01',
                'is_month_closed' => false,
                'cutoffs' => [
                    'breakfast_opt_in_allowed' => true,
                    'lunch_opt_out_allowed' => true,
                    'dinner_opt_out_allowed' => true,
                ],
                'meal' => [
                    'id' => $meal->id,
                    'breakfast' => 0,
                    'lunch' => 1,
                    'dinner' => 1,
                    'total' => 2,
                ],
            ],
        ]);
});

test('todaySummary returns aggregated headcount and unit counts', function () {
    $u1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $u2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $u3 = User::factory()->create(['name' => 'Inactive Charlie', 'is_active' => false]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

    Meal::where('user_id', $u1->id)->where('date', '2026-10-01')->update([
        'breakfast' => 1,
        'lunch' => 2,
        'dinner' => 1,
    ]);

    Meal::where('user_id', $u2->id)->where('date', '2026-10-01')->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($u1);

    $response = $this->getJson(route('meals.today-summary'));

    $response->assertOk()
        ->assertJson([
            'data' => [
                'date' => '2026-10-01',
                'breakfast' => ['headcount' => 1, 'units' => 1],
                'lunch' => ['headcount' => 2, 'units' => 3],
                'dinner' => ['headcount' => 2, 'units' => 2],
                'total_units' => 6,
            ],
        ]);
});

test('todayMembers returns breakdown per active member', function () {
    $u1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $u2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

    Sanctum::actingAs($u1);

    $response = $this->getJson(route('meals.today-members'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'meal_id',
                    'user_id',
                    'name',
                    'breakfast',
                    'lunch',
                    'dinner',
                    'total',
                    'has_logged',
                ],
            ],
        ]);

    expect(count($response->json('data')))->toBe(2);
});

test('today endpoint returns composite payload', function () {
    $u1 = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

    Sanctum::actingAs($u1);

    $response = $this->getJson(route('meals.today'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'server_time',
                'date',
                'summary' => ['breakfast', 'lunch', 'dinner', 'total_units'],
                'members',
            ],
        ]);
});

test('myMonth returns 31 day breakdown for the authenticated user', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

    // Update day 1 and day 2 meals for testing totals
    Meal::where('user_id', $user->id)->where('date', '2026-10-01')->update([
        'breakfast' => 1,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Meal::where('user_id', $user->id)->where('date', '2026-10-02')->update([
        'breakfast' => 0,
        'lunch' => 2,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('meals.my-month', ['month_id' => $month->id]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'month' => ['id', 'year', 'month', 'is_closed'],
                'totals' => ['breakfast', 'lunch', 'dinner', 'total_meals'],
                'days' => [
                    '*' => ['id', 'date', 'day', 'day_name', 'is_friday', 'breakfast', 'lunch', 'dinner', 'total', 'has_logged'],
                ],
            ],
        ]);

    expect(count($response->json('data.days')))->toBe(31);
});

test('sheet endpoint returns full matrix with day rows and member columns', function () {
    $u1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $u2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'breakfast_price' => 25]);

    Sanctum::actingAs($u1);

    $response = $this->getJson(route('meals.sheet', ['month_id' => $month->id]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'month' => ['id', 'year', 'month', 'is_closed', 'breakfast_price'],
                'members' => [
                    '*' => ['id', 'name'],
                ],
                'rows' => [
                    '*' => ['date', 'day', 'day_name', 'is_friday', 'meals', 'daily_total'],
                ],
                'member_totals' => [
                    '*' => ['user_id', 'name', 'breakfast', 'lunch', 'dinner', 'total'],
                ],
                'grand_total',
            ],
        ]);

    // October has 31 days
    expect(count($response->json('data.rows')))->toBe(31);
});

test('byDate returns all meals for specific date', function () {
    $u1 = User::factory()->create(['is_active' => true]);
    $u2 = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10]);

    Sanctum::actingAs($u1);

    $response = $this->getJson(route('meals.by-date', ['date' => '2026-10-15']));

    $response->assertOk()
        ->assertJsonStructure([
            'date',
            'data' => [
                '*' => ['id', 'user_id', 'date', 'breakfast', 'lunch', 'dinner', 'total'],
            ],
        ]);

    $invalidResponse = $this->getJson(route('meals.by-date', ['date' => 'invalid-date']));
    $invalidResponse->assertStatus(422);
});

test('silent opt-in breakfast succeeds at any time for own meal and fails for other users or if already opted in', function () {
    $user1 = User::factory()->create(['is_active' => true]);
    $user2 = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    // Can opt in at any time of day (e.g. 10:30 AM)
    Carbon::setTestNow(Carbon::parse('2026-10-01 10:30:00'));

    $meal = Meal::where('user_id', $user1->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    // Cannot opt in for someone else's meal
    Sanctum::actingAs($user2);
    $this->postJson(route('meals.opt-in-breakfast', $meal))
        ->assertBadRequest();

    // User1 opts in successfully
    Sanctum::actingAs($user1);
    $response = $this->postJson(route('meals.opt-in-breakfast', $meal));
    $response->assertOk()
        ->assertJson([
            'data' => [
                'breakfast' => 1,
            ],
        ]);

    expect($meal->fresh()->breakfast)->toBe(1);

    // Attempting to opt in again when already opted in fails
    $this->postJson(route('meals.opt-in-breakfast', $meal))
        ->assertBadRequest();
});

test('silent opt-out lunch succeeds before 5am cutoff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    // 4:55 AM
    Carbon::setTestNow(Carbon::parse('2026-10-01 04:55:00'));

    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($user);
    $response = $this->postJson(route('meals.opt-out-lunch', $meal));

    $response->assertOk()
        ->assertJson([
            'data' => [
                'lunch' => 0,
            ],
        ]);

    expect($meal->fresh()->lunch)->toBe(0);

    // 5:05 AM fails
    Carbon::setTestNow(Carbon::parse('2026-10-01 05:05:00'));
    $meal->update(['lunch' => 1]);
    $this->postJson(route('meals.opt-out-lunch', $meal))
        ->assertBadRequest();
});

test('silent opt-out dinner succeeds before 2:20pm cutoff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    // 14:15
    Carbon::setTestNow(Carbon::parse('2026-10-01 14:15:00'));

    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($user);
    $response = $this->postJson(route('meals.opt-out-dinner', $meal));

    $response->assertOk()
        ->assertJson([
            'data' => [
                'dinner' => 0,
            ],
        ]);

    expect($meal->fresh()->dinner)->toBe(0);

    // 14:25 fails
    Carbon::setTestNow(Carbon::parse('2026-10-01 14:25:00'));
    $meal->update(['dinner' => 1]);
    $this->postJson(route('meals.opt-out-dinner', $meal))
        ->assertBadRequest();
});

test('manual meal edit creates audit log and dispatches appropriate notification', function () {
    Notification::fake();

    $user1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $user2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    $meal = Meal::where('user_id', $user1->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($user1);

    // Case 1: Increasing lunch count (guest meal) -> triggers MealCountIncreasedNotification
    $response = $this->patchJson(route('meals.update', $meal), [
        'breakfast' => 0,
        'lunch' => 3,
        'dinner' => 1,
        'note' => '2 guest meals for brother lunch',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'lunch' => 3,
            ],
        ]);

    expect(AuditLog::where('action', 'meal.edit')->count())->toBe(1);

    Notification::assertSentTo(
        $user2,
        MealCountIncreasedNotification::class
    );

    // Case 2: Editing someone else's meal -> triggers MemberMealEditedNotification
    Sanctum::actingAs($user2);
    $this->patchJson(route('meals.update', $meal), [
        'breakfast' => 1,
        'lunch' => 1,
        'dinner' => 1,
        'note' => 'Manager corrected meal count',
    ])->assertOk();

    Notification::assertSentTo(
        $user1,
        MemberMealEditedNotification::class
    );
});

test('manual meal edit requires a note and positive counts', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();

    Sanctum::actingAs($user);

    $this->patchJson(route('meals.update', $meal), [
        'breakfast' => -1,
        'lunch' => 1,
        'dinner' => 1,
        'note' => 'Some note',
    ])->assertUnprocessable();

    $this->patchJson(route('meals.update', $meal), [
        'breakfast' => 1,
        'lunch' => 1,
        'dinner' => 1,
        'note' => '',
    ])->assertUnprocessable();
});

test('history endpoint returns audit logs for a meal', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();

    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'meal.edit',
        'auditable_type' => Meal::class,
        'auditable_id' => $meal->id,
        'before' => ['breakfast' => 0],
        'after' => ['breakfast' => 1],
        'note' => 'Test edit note',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('meals.history', $meal));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'action', 'user', 'note', 'created_at'],
            ],
        ])
        ->assertJson([
            'data' => [
                [
                    'action' => 'meal.edit',
                    'note' => 'Test edit note',
                ],
            ],
        ]);
});

test('dayTally updates meal counts for all active members and creates audit log and notifications', function () {
    Notification::fake();

    $manager = User::factory()->create(['name' => 'Manager Dave', 'is_active' => true]);
    $u1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $u2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    Sanctum::actingAs($manager);

    $response = $this->postJson(route('meals.day-tally'), [
        'date' => '2026-10-05',
        'breakfast' => 1,
        'lunch' => 1,
        'dinner' => 1,
        'note' => 'Regular meals for all on Monday',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Day meal tally updated successfully.',
            'affected_count' => 3,
        ]);

    expect(Meal::where('date', '2026-10-05')->sum('lunch'))->toBe(3)
        ->and(AuditLog::where('action', 'meal.day_tally_updated')->count())->toBe(1);

    Notification::assertSentTo([$u1, $u2], DayMealTallyUpdatedNotification::class);
});

test('dayOff turns off meals for all active members', function () {
    Notification::fake();

    $manager = User::factory()->create(['is_active' => true]);
    $u1 = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    Sanctum::actingAs($manager);

    $response = $this->postJson(route('meals.day-off'), [
        'date' => '2026-10-10',
        'note' => 'Eid holiday mess closed',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Day meals turned off successfully.',
            'affected_count' => 2,
        ]);

    expect(Meal::where('date', '2026-10-10')->sum('lunch'))->toBe(0)
        ->and(AuditLog::where('action', 'meal.day_off')->count())->toBe(1);

    Notification::assertSentTo($u1, DayMealOffNotification::class);
});

test('dateRangeOff turns off meals for a date range across active members', function () {
    Notification::fake();

    $manager = User::factory()->create(['is_active' => true]);
    $u1 = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    Sanctum::actingAs($manager);

    $response = $this->postJson(route('meals.date-range-off'), [
        'from_date' => '2026-10-10',
        'to_date' => '2026-10-12',
        'note' => 'Puja holidays',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Date range meals turned off successfully.',
            'affected_count' => 6,
        ]);

    expect(Meal::whereBetween('date', ['2026-10-10', '2026-10-12'])->sum('lunch'))->toBe(0)
        ->and(AuditLog::where('action', 'meal.date_range_off')->count())->toBe(1);

    Notification::assertSentTo($u1, DateRangeMealOffNotification::class);
});

test('meal mutations invalidate micro caches', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 10, 'is_closed' => false]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

    $meal = Meal::where('user_id', $user->id)->where('date', '2026-10-01')->first();
    $meal->update([
        'breakfast' => 0,
        'lunch' => 1,
        'dinner' => 1,
    ]);

    Sanctum::actingAs($user);

    // Initial summary cache warmed
    $res1 = $this->getJson(route('meals.today-summary'));
    expect($res1->json('data.total_units'))->toBe(2);

    // Mutate meal
    $this->patchJson(route('meals.update', $meal), [
        'breakfast' => 1,
        'lunch' => 2,
        'dinner' => 2,
        'note' => 'Updated after guest arrival',
    ])->assertOk();

    // Re-query summary - should immediately reflect updated count (5 units)
    $res2 = $this->getJson(route('meals.today-summary'));
    expect($res2->json('data.total_units'))->toBe(5);
});

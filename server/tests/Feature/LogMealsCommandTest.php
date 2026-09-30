<?php

use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('meal:log command logs today meals for active users', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-10 19:00:00'));

    $activeUser = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // Inactive user meal manually created
    $inactiveMeal = Meal::create([
        'user_id' => $inactiveUser->id,
        'month_id' => $month->id,
        'date' => '2026-05-10',
        'has_logged' => false,
    ]);

    $activeMeal = Meal::where('user_id', $activeUser->id)->where('date', '2026-05-10')->first();
    expect($activeMeal->has_logged)->toBeFalse();

    $this->artisan('meal:log')
        ->expectsOutputToContain('Successfully logged 1 meal(s) for today (2026-05-10).')
        ->assertSuccessful();

    expect($activeMeal->fresh()->has_logged)->toBeTrue()
        ->and($inactiveMeal->fresh()->has_logged)->toBeFalse();

    Carbon::setTestNow();
});

test('meal:log command is idempotent and can be run multiple times safely', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-10 19:00:00'));

    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // 1st run: logs the meal
    $this->artisan('meal:log')
        ->expectsOutputToContain('Successfully logged 1 meal(s) for today (2026-05-10).')
        ->assertSuccessful();

    // 2nd run (e.g. at 19:30): idempotent, does not crash or double log
    Carbon::setTestNow(Carbon::parse('2026-05-10 19:30:00'));
    $this->artisan('meal:log')
        ->expectsOutputToContain('No unlogged meals found for active users today (2026-05-10).')
        ->assertSuccessful();

    // 3rd run (e.g. at 20:00): idempotent
    Carbon::setTestNow(Carbon::parse('2026-05-10 20:00:00'));
    $this->artisan('meal:log')
        ->expectsOutputToContain('No unlogged meals found for active users today (2026-05-10).')
        ->assertSuccessful();

    Carbon::setTestNow();
});

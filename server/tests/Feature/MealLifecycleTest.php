<?php

use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('auto-generates meals for all active users across all days in the month upon month creation', function () {
    $activeUser1 = User::factory()->create(['is_active' => true]);
    $activeUser2 = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    // May has 31 days
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
    ]);

    // 31 days * 2 active users = 62 meal records
    expect(Meal::where('month_id', $month->id)->count())->toBe(62)
        ->and(Meal::where('month_id', $month->id)->where('user_id', $activeUser1->id)->count())->toBe(31)
        ->and(Meal::where('month_id', $month->id)->where('user_id', $activeUser2->id)->count())->toBe(31)
        ->and(Meal::where('month_id', $month->id)->where('user_id', $inactiveUser->id)->count())->toBe(0);
});

test('sets default meal counts to (0, 1, 1) on weekdays and (0, 2, 1) on Fridays', function () {
    $user = User::factory()->create(['is_active' => true]);

    // 2026-05-01 is a Friday, 2026-05-02 is a Saturday
    $month = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
    ]);

    $fridayMeal = Meal::where('user_id', $user->id)->where('date', '2026-05-01')->first();
    $saturdayMeal = Meal::where('user_id', $user->id)->where('date', '2026-05-02')->first();

    expect($fridayMeal->breakfast)->toBe(0)
        ->and($fridayMeal->lunch)->toBe(2)
        ->and($fridayMeal->dinner)->toBe(1);

    expect($saturdayMeal->breakfast)->toBe(0)
        ->and($saturdayMeal->lunch)->toBe(1)
        ->and($saturdayMeal->dinner)->toBe(1);
});

test('allows silent opt in to breakfast only when breakfast count is 0', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-10 04:30:00'));

    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $meal->update(['breakfast' => 0]);

    $meal->optInBreakfast();
    expect($meal->fresh()->breakfast)->toBe(1);

    // Attempting to opt in again must fail
    expect(fn () => $meal->optInBreakfast())
        ->toThrow(Exception::class, 'Breakfast is already opted in. Any further changes require manual edit.');
});

test('allows silent opt out of lunch before 5:00 AM on meal date', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $meal->update(['lunch' => 1]);

    // Time is 4:45 AM on meal date
    Carbon::setTestNow(Carbon::parse('2026-05-10 04:45:00'));

    $meal->optOutLunch();
    expect($meal->fresh()->lunch)->toBe(0);

    Carbon::setTestNow();
});

test('prevents silent opt out of lunch at or after 5:00 AM on meal date', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $meal->update(['lunch' => 1]);

    // Time is 5:00 AM exactly on meal date
    Carbon::setTestNow(Carbon::parse('2026-05-10 05:00:00'));

    expect(fn () => $meal->optOutLunch())
        ->toThrow(Exception::class, 'Too late to opt out of lunch');

    Carbon::setTestNow();
});

test('allows silent opt out of dinner before 2:20 PM on meal date', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $meal->update(['dinner' => 1]);

    // Time is 1:30 PM on meal date
    Carbon::setTestNow(Carbon::parse('2026-05-10 13:30:00'));

    $meal->optOutDinner();
    expect($meal->fresh()->dinner)->toBe(0);

    Carbon::setTestNow();
});

test('prevents silent opt out of dinner at or after 2:20 PM on meal date', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $meal->update(['dinner' => 1]);

    // Time is 2:21 PM on meal date
    Carbon::setTestNow(Carbon::parse('2026-05-10 14:21:00'));

    expect(fn () => $meal->optOutDinner())
        ->toThrow(Exception::class, 'Too late to opt out of dinner');

    Carbon::setTestNow();
});

test('logToday sets has_logged to true for current day meals', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-10 23:00:00'));

    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $todayMeal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();
    $tomorrowMeal = Meal::where('user_id', $user->id)->where('date', '2026-05-11')->first();

    $todayMeal->update(['has_logged' => false]);
    $tomorrowMeal->update(['has_logged' => false]);

    Meal::logToday();

    expect($todayMeal->fresh()->has_logged)->toBeTrue()
        ->and($tomorrowMeal->fresh()->has_logged)->toBeFalse();

    Carbon::setTestNow();
});

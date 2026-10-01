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

test('month:ensure-current initializes month and pre-seeds meal entries', function () {
    $u1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $u2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

    Carbon::setTestNow(Carbon::parse('2026-11-01 00:01:00'));

    expect(Month::count())->toBe(0)
        ->and(Meal::count())->toBe(0);

    // Run command
    $this->artisan('month:ensure-current')
        ->assertSuccessful()
        ->expectsOutputToContain('Successfully initialized Month');

    expect(Month::count())->toBe(1);

    $month = Month::first();
    expect($month->year)->toBe(2026)
        ->and($month->month)->toBe(11)
        ->and($month->is_closed)->toBeFalse();

    // November has 30 days * 2 active users = 60 meal entries
    expect(Meal::where('month_id', $month->id)->count())->toBe(60);

    // Running again reports existing
    $this->artisan('month:ensure-current')
        ->assertSuccessful()
        ->expectsOutputToContain('is already initialized');
});

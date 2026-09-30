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
use App\Notifications\OwnMealEditedNotification;
use App\Services\MealService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MealService;
});

test('optInBreakfast allows silent breakfast opt-in for own meal', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();

    expect($meal->breakfast)->toBe(0);

    $updated = $this->service->optInBreakfast($meal, $user);
    expect($updated->breakfast)->toBe(1);
});

test('optInBreakfast throws exception if attempting to opt in for another user', function () {
    $userA = User::factory()->create(['is_active' => true]);
    $userB = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $mealA = Meal::where('user_id', $userA->id)->where('date', '2026-05-10')->first();

    expect(fn () => $this->service->optInBreakfast($mealA, $userB))
        ->toThrow(InvalidArgumentException::class, 'You can only opt in to breakfast for your own meal.');
});

test('optOutLunch allows silent opt-out before cutoff for own meal and rejects after cutoff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();

    // 4:30 AM on meal date -> success
    Carbon::setTestNow(Carbon::parse('2026-05-10 04:30:00'));
    $updated = $this->service->optOutLunch($meal, $user);
    expect($updated->lunch)->toBe(0);

    // After 5:00 AM on meal date -> exception
    $meal->update(['lunch' => 1]);
    Carbon::setTestNow(Carbon::parse('2026-05-10 05:01:00'));
    expect(fn () => $this->service->optOutLunch($meal, $user))
        ->toThrow(Exception::class, 'Too late to opt out of lunch');

    Carbon::setTestNow();
});

test('optOutDinner allows silent opt-out before cutoff for own meal and rejects after cutoff', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();

    // 1:00 PM on meal date -> success
    Carbon::setTestNow(Carbon::parse('2026-05-10 13:00:00'));
    $updated = $this->service->optOutDinner($meal, $user);
    expect($updated->dinner)->toBe(0);

    // After 2:20 PM on meal date -> exception
    $meal->update(['dinner' => 1]);
    Carbon::setTestNow(Carbon::parse('2026-05-10 14:21:00'));
    expect(fn () => $this->service->optOutDinner($meal, $user))
        ->toThrow(Exception::class, 'Too late to opt out of dinner');

    Carbon::setTestNow();
});

test('editMeal requires a non-empty note and non-negative counts', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();

    // Empty note
    expect(fn () => $this->service->editMeal($meal, $user, 0, 1, 1, '   '))
        ->toThrow(InvalidArgumentException::class, 'A note is required for manual meal edits.');

    // Negative counts
    expect(fn () => $this->service->editMeal($meal, $user, -1, 1, 1, 'Valid note'))
        ->toThrow(InvalidArgumentException::class, 'Meal counts cannot be negative.');
});

test('editMeal rejects editing in a closed month', function () {
    $user = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5, 'is_closed' => true]);
    $meal = Meal::where('user_id', $user->id)->where('date', '2026-05-10')->first();

    expect(fn () => $this->service->editMeal($meal, $user, 0, 1, 1, 'Note'))
        ->toThrow(RuntimeException::class, 'Cannot modify meals for a closed month.');
});

test('editMeal on someone elses meal writes AuditLog and sends MemberMealEditedNotification to target only', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Tanmay', 'is_active' => true]);
    $target = User::factory()->create(['name' => 'Prodip', 'is_active' => true]);
    $other = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $meal = Meal::where('user_id', $target->id)->where('date', '2026-05-10')->first();

    $updated = $this->service->editMeal($meal, $editor, 0, 0, 1, 'Prodip is eating lunch at office');

    expect($updated->lunch)->toBe(0);

    // AuditLog verification
    $audit = AuditLog::where('action', 'meal.edit')->where('auditable_id', $meal->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($editor->id)
        ->and($audit->note)->toBe('Prodip is eating lunch at office')
        ->and($audit->before['lunch'])->toBe(1)
        ->and($audit->after['lunch'])->toBe(0);

    // Target user was notified directly
    Notification::assertSentTo($target, MemberMealEditedNotification::class, function ($n) use ($editor) {
        return $n->editor->id === $editor->id && $n->note === 'Prodip is eating lunch at office';
    });

    // Other users and editor were NOT notified
    Notification::assertNotSentTo($other, MemberMealEditedNotification::class);
    Notification::assertNotSentTo($editor, MemberMealEditedNotification::class);
});

test('editMeal on own meal broadcasts OwnMealEditedNotification to all other active members', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $member1 = User::factory()->create(['name' => 'Tanmay', 'is_active' => true]);
    $member2 = User::factory()->create(['name' => 'Prodip', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $meal = Meal::where('user_id', $editor->id)->where('date', '2026-05-10')->first();

    $this->service->editMeal($meal, $editor, 1, 0, 1, 'Having early breakfast and skipping lunch');

    // Broadcast to member1 and member2
    Notification::assertSentTo([$member1, $member2], OwnMealEditedNotification::class);
    Notification::assertNotSentTo($editor, OwnMealEditedNotification::class);
});

test('editMeal on own meal with guest count increase broadcasts MealCountIncreasedNotification', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Rafy', 'is_active' => true]);
    $member = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $meal = Meal::where('user_id', $editor->id)->where('date', '2026-05-10')->first();
    // Default dinner was 1 -> increasing to 4
    $this->service->editMeal($meal, $editor, 0, 1, 4, '3 cousins visiting for dinner');

    Notification::assertSentTo($member, MealCountIncreasedNotification::class, function ($n) {
        return $n->mealType === 'dinner' && $n->oldCount === 1 && $n->newCount === 4;
    });
});

test('updateDayTally updates meals for all active users, writes AuditLog, and broadcasts DayMealTallyUpdatedNotification', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Tanmay', 'is_active' => true]);
    $member1 = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $member2 = User::factory()->create(['name' => 'Prodip', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $count = $this->service->updateDayTally('2026-05-15', $editor, 0, 1, 2, 'Friday Beef Feast');

    expect($count)->toBe(3);

    // Check all active members meals updated
    $meals = Meal::where('date', '2026-05-15')->get();
    foreach ($meals as $m) {
        expect($m->lunch)->toBe(1)
            ->and($m->dinner)->toBe(2);
    }

    // AuditLog recorded
    $audit = AuditLog::where('action', 'meal.day_tally_updated')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($editor->id)
        ->and($audit->note)->toBe('Friday Beef Feast');

    // Broadcast sent to all active members except editor
    Notification::assertSentTo([$member1, $member2], DayMealTallyUpdatedNotification::class);
    Notification::assertNotSentTo($editor, DayMealTallyUpdatedNotification::class);
});

test('setDayMealsOff sets all active members meals to zero and broadcasts DayMealOffNotification', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Prodip', 'is_active' => true]);
    $member = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $count = $this->service->setDayMealsOff('2026-05-18', $editor, 'Mess Picnic Day');

    expect($count)->toBe(2);

    $meals = Meal::where('date', '2026-05-18')->get();
    foreach ($meals as $m) {
        expect($m->breakfast)->toBe(0)
            ->and($m->lunch)->toBe(0)
            ->and($m->dinner)->toBe(0);
    }

    Notification::assertSentTo($member, DayMealOffNotification::class);
    Notification::assertNotSentTo($editor, DayMealOffNotification::class);
});

test('setDateRangeMealsOff turns off all meals across date range and broadcasts DateRangeMealOffNotification', function () {
    Notification::fake();

    $editor = User::factory()->create(['name' => 'Rownak', 'is_active' => true]);
    $member = User::factory()->create(['is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $count = $this->service->setDateRangeMealsOff('2026-05-20', '2026-05-22', $editor, 'Eid Vacation');

    // 3 days * 2 active users = 6 meal records updated
    expect($count)->toBe(6);

    $meals = Meal::whereBetween('date', ['2026-05-20', '2026-05-22'])->get();
    foreach ($meals as $m) {
        expect($m->breakfast)->toBe(0)
            ->and($m->lunch)->toBe(0)
            ->and($m->dinner)->toBe(0);
    }

    Notification::assertSentTo($member, DateRangeMealOffNotification::class);
    Notification::assertNotSentTo($editor, DateRangeMealOffNotification::class);
});

test('getTodayDashboard accurately aggregates headcounts, meal units, and member statuses', function () {
    $userA = User::factory()->create(['name' => 'Hind', 'is_active' => true]);
    $userB = User::factory()->create(['name' => 'Tanmay', 'is_active' => true]);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    // User A: (BF=1, LC=2, DN=1)
    Meal::where('user_id', $userA->id)->where('date', '2026-05-10')->update([
        'breakfast' => 1,
        'lunch' => 2,
        'dinner' => 1,
    ]);

    // User B: (BF=0, LC=0, DN=3)
    Meal::where('user_id', $userB->id)->where('date', '2026-05-10')->update([
        'breakfast' => 0,
        'lunch' => 0,
        'dinner' => 3,
    ]);

    $dashboard = $this->service->getTodayDashboard('2026-05-10');

    expect($dashboard['date'])->toBe('2026-05-10')
        ->and($dashboard['summary']['breakfast']['headcount'])->toBe(1)
        ->and($dashboard['summary']['breakfast']['units'])->toBe(1)
        ->and($dashboard['summary']['lunch']['headcount'])->toBe(1)
        ->and($dashboard['summary']['lunch']['units'])->toBe(2)
        ->and($dashboard['summary']['dinner']['headcount'])->toBe(2)
        ->and($dashboard['summary']['dinner']['units'])->toBe(4)
        ->and($dashboard['summary']['total_units'])->toBe(7)
        ->and(count($dashboard['members']))->toBe(2);
});

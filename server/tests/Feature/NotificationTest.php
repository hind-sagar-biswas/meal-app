<?php

use App\Models\Meal;
use App\Models\Month;
use App\Models\MonthResult;
use App\Models\User;
use App\Notifications\DateRangeMealOffNotification;
use App\Notifications\DayMealOffNotification;
use App\Notifications\DayMealTallyUpdatedNotification;
use App\Notifications\GenericMessNotification;
use App\Notifications\MealCountIncreasedNotification;
use App\Notifications\MemberMealEditedNotification;
use App\Notifications\MonthClosedNotification;
use App\Notifications\MonthReopenedNotification;
use App\Notifications\OwnMealEditedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('MonthClosedNotification formats title, message, and payload correctly', function () {
    $user = User::factory()->create(['name' => 'Hind']);
    $recipient = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $result = MonthResult::create([
        'month_id' => $month->id,
        'participant_count' => 8,
        'breakfast_count' => 20,
        'meal_count' => 200,
        'total_expense' => 10000,
        'bazar_expense' => 8000,
        'grouped_expense' => 2000,
        'breakfast_expense' => 400,
        'meal_expense' => 7600,
        'meal_rate' => 38_000_000,
        'group_expense_per_person' => 250_000_000,
    ]);

    $notification = new MonthClosedNotification($month, $result, $user);
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Month Closed: May 2026')
        ->and($dbNotification->data['message'])->toContain('Hind closed May 2026. Meal rate: 38.00 Tk.')
        ->and($dbNotification->data['severity'])->toBe('success')
        ->and($dbNotification->data['data']['meal_rate'])->toEqual(38);
});

test('MonthReopenedNotification formats title and warning message correctly', function () {
    $user = User::factory()->create(['name' => 'Tanmay']);
    $recipient = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);

    $notification = new MonthReopenedNotification($month, $user);
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Month Reopened: May 2026')
        ->and($dbNotification->data['message'])->toBe('Tanmay reopened May 2026 for adjustments.')
        ->and($dbNotification->data['severity'])->toBe('warning');
});

test('MemberMealEditedNotification formats edit details and note correctly', function () {
    $editor = User::factory()->create(['name' => 'Tanmay']);
    $member = User::factory()->create(['name' => 'Prodip']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $member->id)->where('date', '2026-05-10')->first();

    $meal->update(['breakfast' => 0, 'lunch' => 0, 'dinner' => 1]);

    $notification = new MemberMealEditedNotification(
        $meal,
        $editor,
        ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
        "You're eating lunch outside"
    );
    $member->notify($notification);

    $dbNotification = $member->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Meal Edited (May 10)')
        ->and($dbNotification->data['message'])->toContain("Tanmay updated your May 10 meal to (0, 0, 1). Note: You're eating lunch outside")
        ->and($dbNotification->data['data']['editor_id'])->toBe($editor->id);
});

test('OwnMealEditedNotification formats self edit broadcast message correctly', function () {
    $editor = User::factory()->create(['name' => 'Hind']);
    $recipient = User::factory()->create(['name' => 'Tanmay']);
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $editor->id)->where('date', '2026-05-10')->first();

    $meal->update(['breakfast' => 1, 'lunch' => 2, 'dinner' => 1]);

    $notification = new OwnMealEditedNotification(
        $meal,
        $editor,
        ['breakfast' => 0, 'lunch' => 1, 'dinner' => 1],
        'Brother visiting for lunch'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Meal Update: Hind (May 10)')
        ->and($dbNotification->data['message'])->toContain('Hind updated their May 10 meal to (1, 2, 1). Note: Brother visiting for lunch');
});

test('MealCountIncreasedNotification formats guest count increase correctly', function () {
    $editor = User::factory()->create(['name' => 'Rafy']);
    $recipient = User::factory()->create();
    $month = Month::factory()->create(['year' => 2026, 'month' => 5]);
    $meal = Meal::where('user_id', $editor->id)->where('date', '2026-05-15')->first();

    $meal->update(['dinner' => 4]);

    $notification = new MealCountIncreasedNotification(
        $meal,
        $editor,
        'dinner',
        1,
        4,
        '3 college friends joining for dinner'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Guest Meals Added: Rafy (May 15)')
        ->and($dbNotification->data['message'])->toBe('Rafy increased May 15 Dinner from 1 to 4. Note: 3 college friends joining for dinner');
});

test('DayMealTallyUpdatedNotification formats whole day meal tally change correctly', function () {
    $editor = User::factory()->create(['name' => 'Tanmay']);
    $recipient = User::factory()->create();

    $notification = new DayMealTallyUpdatedNotification(
        '2026-05-20',
        $editor,
        0,
        1,
        2,
        'Special beef feast for dinner'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Day Meal Tally Updated (May 20)')
        ->and($dbNotification->data['message'])->toBe("Tanmay updated everyone's May 20 meal to (0, 1, 2). Note: Special beef feast for dinner");
});

test('DayMealOffNotification formats cook absent / day off alert correctly', function () {
    $editor = User::factory()->create(['name' => 'Prodip']);
    $recipient = User::factory()->create();

    $notification = new DayMealOffNotification(
        '2026-05-22',
        $editor,
        'Cook is on leave today'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('All Meals Off (May 22)')
        ->and($dbNotification->data['message'])->toBe('Prodip marked all meals off for May 22. Note: Cook is on leave today')
        ->and($dbNotification->data['severity'])->toBe('warning');
});

test('DateRangeMealOffNotification formats multi-day vacation off correctly', function () {
    $editor = User::factory()->create(['name' => 'Rownak']);
    $recipient = User::factory()->create();

    $notification = new DateRangeMealOffNotification(
        '2026-05-25',
        '2026-05-30',
        $editor,
        'Eid Vacation'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Holiday Meals Off (May 25 - May 30)')
        ->and($dbNotification->data['message'])->toBe('Rownak turned off all meals from May 25 to May 30. Note: Eid Vacation');
});

test('GenericMessNotification formats custom announcements properly', function () {
    $sender = User::factory()->create(['name' => 'Tanvir']);
    $recipient = User::factory()->create();

    $notification = new GenericMessNotification(
        'Mess Meeting Tonight',
        'Everyone please be present in dining room at 10:00 PM.',
        $sender,
        'Discussion about monthly bazaar'
    );
    $recipient->notify($notification);

    $dbNotification = $recipient->notifications()->first();
    expect($dbNotification)->not->toBeNull()
        ->and($dbNotification->data['title'])->toBe('Mess Meeting Tonight')
        ->and($dbNotification->data['message'])->toBe('Everyone please be present in dining room at 10:00 PM. Note: Discussion about monthly bazaar');
});

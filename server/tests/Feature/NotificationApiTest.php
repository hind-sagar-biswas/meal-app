<?php

use App\Models\User;
use App\Notifications\GenericMessNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can fetch paginated notifications inbox and unread count', function () {
    $user = User::factory()->create(['is_active' => true]);
    $sender = User::factory()->create(['is_active' => true]);

    $notification1 = new GenericMessNotification('Notice 1', 'Message 1', $sender);
    $notification2 = new GenericMessNotification('Notice 2', 'Message 2', $sender);

    $user->notify($notification1);
    $user->notify($notification2);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('notifications.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'unread_count',
            'notifications' => [
                'data' => [
                    '*' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'],
                ],
            ],
        ])
        ->assertJson([
            'unread_count' => 2,
        ]);

    expect(count($response->json('notifications.data')))->toBe(2);
});

test('user only sees their own notifications and not other users notifications', function () {
    $userA = User::factory()->create(['is_active' => true]);
    $userB = User::factory()->create(['is_active' => true]);

    $userA->notify(new GenericMessNotification('For User A', 'Secret A', $userA));
    $userB->notify(new GenericMessNotification('For User B', 'Secret B', $userB));

    Sanctum::actingAs($userA);

    $response = $this->getJson(route('notifications.index'));

    $response->assertOk();
    $titles = collect($response->json('notifications.data'))->pluck('data.title')->all();

    expect($titles)->toContain('For User A')
        ->and($titles)->not->toContain('For User B');
});

test('user can mark a single notification as read', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->notify(new GenericMessNotification('Unread Notice', 'Details', $user));

    $notification = $user->notifications()->first();
    expect($notification->read_at)->toBeNull();

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('notifications.read', $notification->id));

    $response->assertOk()
        ->assertJson(['message' => 'Notification marked as read.']);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function () {
    $userA = User::factory()->create(['is_active' => true]);
    $userB = User::factory()->create(['is_active' => true]);

    $userB->notify(new GenericMessNotification('Notice B', 'Details', $userB));
    $notificationB = $userB->notifications()->first();

    Sanctum::actingAs($userA);

    $response = $this->patchJson(route('notifications.read', $notificationB->id));

    $response->assertNotFound();
    expect($notificationB->fresh()->read_at)->toBeNull();
});

test('user can mark all unread notifications as read', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->notify(new GenericMessNotification('Notice 1', 'D1', $user));
    $user->notify(new GenericMessNotification('Notice 2', 'D2', $user));

    expect($user->unreadNotifications()->count())->toBe(2);

    Sanctum::actingAs($user);

    $response = $this->postJson(route('notifications.read-all'));

    $response->assertOk()
        ->assertJson(['message' => 'All notifications marked as read.']);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('user can broadcast an announcement to all active mess members', function () {
    $sender = User::factory()->create(['name' => 'Manager', 'is_active' => true]);
    $member1 = User::factory()->create(['is_active' => true]);
    $member2 = User::factory()->create(['is_active' => true]);
    $inactive = User::factory()->inactive()->create();

    Sanctum::actingAs($sender);

    $response = $this->postJson(route('notifications.broadcast'), [
        'title' => 'Mess Meeting',
        'message' => 'Meeting at 10 PM in dining room.',
        'note' => 'Eid budget plan',
        'severity' => 'warning',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Announcement broadcast successfully.']);

    expect($sender->notifications()->count())->toBe(1)
        ->and($member1->notifications()->count())->toBe(1)
        ->and($member2->notifications()->count())->toBe(1)
        ->and($inactive->notifications()->count())->toBe(0);

    $saved = $member1->notifications()->first();
    expect($saved->data['title'])->toBe('Mess Meeting')
        ->and($saved->data['message'])->toContain('Eid budget plan')
        ->and($saved->data['severity'])->toBe('warning');
});

test('broadcast announcement validates required fields', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $response = $this->postJson(route('notifications.broadcast'), [
        'title' => '',
        'message' => '',
        'severity' => 'invalid-severity',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'message', 'severity']);
});

test('broadcast endpoint is rate limited to 3 requests per minute', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('notifications.broadcast'), [
            'title' => 'Broadcast '.$i,
            'message' => 'Message '.$i,
        ])->assertOk();
    }

    $this->postJson(route('notifications.broadcast'), [
        'title' => 'Broadcast 4',
        'message' => 'Message 4',
    ])->assertStatus(429);
});

test('read-all endpoint is rate limited to 10 requests per minute', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson(route('notifications.read-all'))->assertOk();
    }

    $this->postJson(route('notifications.read-all'))->assertStatus(429);
});

test('unauthenticated user cannot access notifications endpoints', function () {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
    $this->patchJson(route('notifications.read', 'any-id'))->assertUnauthorized();
    $this->postJson(route('notifications.read-all'))->assertUnauthorized();
    $this->postJson(route('notifications.broadcast'), [])->assertUnauthorized();
});

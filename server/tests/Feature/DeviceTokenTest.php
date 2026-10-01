<?php

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can register a new device token', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $response = $this->postJson(route('device-tokens.store'), [
        'token' => 'ExponentPushToken[device-token-12345]',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Device token registered successfully.',
            'device_token' => [
                'user_id' => $user->id,
                'token' => 'ExponentPushToken[device-token-12345]',
            ],
        ]);

    expect(DeviceToken::where('token', 'ExponentPushToken[device-token-12345]')->count())->toBe(1)
        ->and($user->deviceTokens()->count())->toBe(1);
});

test('existing device token is reassigned when another user logs in and registers it', function () {
    $userA = User::factory()->create(['is_active' => true]);
    $userB = User::factory()->create(['is_active' => true]);

    DeviceToken::create([
        'user_id' => $userA->id,
        'token' => 'ExponentPushToken[shared-device-token]',
    ]);

    Sanctum::actingAs($userB);

    $response = $this->postJson(route('device-tokens.store'), [
        'token' => 'ExponentPushToken[shared-device-token]',
    ]);

    $response->assertOk();

    expect(DeviceToken::where('token', 'ExponentPushToken[shared-device-token]')->count())->toBe(1)
        ->and($userA->deviceTokens()->count())->toBe(0)
        ->and($userB->deviceTokens()->count())->toBe(1);
});

test('authenticated user can unregister their device token', function () {
    $user = User::factory()->create(['is_active' => true]);
    DeviceToken::create([
        'user_id' => $user->id,
        'token' => 'ExponentPushToken[token-to-remove]',
    ]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson(route('device-tokens.destroy'), [
        'token' => 'ExponentPushToken[token-to-remove]',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Device token unregistered successfully.',
        ]);

    expect(DeviceToken::where('token', 'ExponentPushToken[token-to-remove]')->count())->toBe(0);
});

test('unauthenticated user cannot register or unregister device token', function () {
    $this->postJson(route('device-tokens.store'), [
        'token' => 'ExponentPushToken[token-test]',
    ])->assertUnauthorized();

    $this->deleteJson(route('device-tokens.destroy'), [
        'token' => 'ExponentPushToken[token-test]',
    ])->assertUnauthorized();
});

test('device token registration validates Expo token format regex', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    // Garbage token rejected
    $this->postJson(route('device-tokens.store'), ['token' => 'invalid-random-token'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);

    $this->postJson(route('device-tokens.store'), ['token' => 'ExponentPushToken[]'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);

    // Valid formats accepted
    $this->postJson(route('device-tokens.store'), ['token' => 'ExpoPushToken[abc-123_xyz]'])
        ->assertOk();

    $this->postJson(route('device-tokens.store'), ['token' => 'ExponentPushToken[abc-123_xyz]'])
        ->assertOk();
});

test('device token requests validate required token', function () {
    $user = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($user);

    $this->postJson(route('device-tokens.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);

    $this->deleteJson(route('device-tokens.destroy'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('logging out with device_token removes device token and revokes access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('test-device');

    DeviceToken::create([
        'user_id' => $user->id,
        'token' => 'ExponentPushToken[token-during-logout]',
    ]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson(route('auth.logout'), [
            'device_token' => 'ExponentPushToken[token-during-logout]',
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out.']);

    expect($user->tokens()->count())->toBe(0)
        ->and(DeviceToken::where('token', 'ExponentPushToken[token-during-logout]')->count())->toBe(0);
});

test('logging out with token parameter key removes device token and revokes access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('test-device');

    DeviceToken::create([
        'user_id' => $user->id,
        'token' => 'ExponentPushToken[token-key-param]',
    ]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson(route('auth.logout'), [
            'token' => 'ExponentPushToken[token-key-param]',
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out.']);

    expect($user->tokens()->count())->toBe(0)
        ->and(DeviceToken::where('token', 'ExponentPushToken[token-key-param]')->count())->toBe(0);
});

test('logging out without device_token retains device token and revokes access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('test-device');

    DeviceToken::create([
        'user_id' => $user->id,
        'token' => 'ExponentPushToken[token-to-keep]',
    ]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson(route('auth.logout'), []);

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out.']);

    expect($user->tokens()->count())->toBe(0)
        ->and(DeviceToken::where('token', 'ExponentPushToken[token-to-keep]')->count())->toBe(1);
});

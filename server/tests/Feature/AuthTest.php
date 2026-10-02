<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can log in with valid credentials and receive sanctum token and user details', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret123'),
        'is_active' => true,
    ]);

    $response = $this->postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'secret123',
        'device_name' => 'Pixel 8',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'is_active',
                'created_at',
            ],
        ])
        ->assertJson([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'is_active' => true,
            ],
        ]);

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('Pixel 8');
});

test('user cannot log in with invalid password', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('correct-password'),
        'is_active' => true,
    ]);

    $response = $this->postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('user cannot log in with non-existent email', function () {
    $response = $this->postJson(route('auth.login'), [
        'email' => 'nonexistent@example.com',
        'password' => 'some-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('inactive user is forbidden from logging in', function () {
    User::factory()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson(route('auth.login'), [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Your account is inactive.',
        ]);
});

test('login requires email and password and validates email format', function () {
    $response = $this->postJson(route('auth.login'), [
        'email' => 'not-an-email',
        'password' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('authenticated user can retrieve their own profile via auth/me', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('auth.me'));

    $response->assertOk()
        ->assertJson([
            'user' => [
                'id' => $user->id,
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'is_active' => true,
            ],
        ]);
});

test('unauthenticated user cannot access auth/me', function () {
    $response = $this->getJson(route('auth.me'));

    $response->assertUnauthorized();
});

test('authenticated user can log out and revoke current access token', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('test-token');

    $response = $this->withToken($token->plainTextToken)
        ->postJson(route('auth.logout'));

    $response->assertOk()
        ->assertJson([
            'message' => 'Successfully logged out.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('login is rate limited to 5 attempts per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('auth.login'), [
            'email' => 'brute@example.com',
            'password' => 'wrong',
        ]);
    }

    $this->postJson(route('auth.login'), [
        'email' => 'brute@example.com',
        'password' => 'wrong',
    ])->assertStatus(429);
});

test('authenticated user can update their name and email', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->patchJson(route('auth.profile.update'), [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => 'New Name',
                'email' => 'new@example.com',
            ],
        ]);

    expect($user->fresh()->name)->toBe('New Name')
        ->and($user->fresh()->email)->toBe('new@example.com');
});

test('user cannot update email to another existing user email', function () {
    $user1 = User::factory()->create(['email' => 'user1@example.com']);
    $user2 = User::factory()->create(['email' => 'user2@example.com']);

    Sanctum::actingAs($user1);

    $response = $this->patchJson(route('auth.profile.update'), [
        'name' => 'User One',
        'email' => 'user2@example.com',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('authenticated user can update their password with valid current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-secret-password'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson(route('auth.password.update'), [
        'current_password' => 'old-secret-password',
        'password' => 'new-strong-password123',
        'password_confirmation' => 'new-strong-password123',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Password updated successfully.',
        ]);

    expect(Hash::check('new-strong-password123', $user->fresh()->password))->toBeTrue();
});

test('password update fails with invalid current password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-secret-password'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson(route('auth.password.update'), [
        'current_password' => 'wrong-current-password',
        'password' => 'new-strong-password123',
        'password_confirmation' => 'new-strong-password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

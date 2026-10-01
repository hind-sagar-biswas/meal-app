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

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can retrieve list of active members', function () {
    Cache::flush();

    $user1 = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
    $user2 = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
    $inactiveUser = User::factory()->inactive()->create(['name' => 'Charlie']);

    Sanctum::actingAs($user1);

    $response = $this->getJson(route('members.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'is_active', 'created_at'],
            ],
        ]);

    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->all();

    expect($ids)->toContain($user1->id, $user2->id)
        ->and($ids)->not->toContain($inactiveUser->id);
});

test('unauthenticated user cannot retrieve members list', function () {
    $this->getJson(route('members.index'))
        ->assertUnauthorized();
});

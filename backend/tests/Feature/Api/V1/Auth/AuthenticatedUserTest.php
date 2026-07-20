<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth', 'me');

it('returns authenticated user data', function () {
    $user = User::factory()->create([
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'full_name', 'email', 'role', 'account_status', 'created_at', 'updated_at'],
    ]);

    expect($response->json('data.email'))->toBe('jane@example.com');
    expect($response->json('data.full_name'))->toBe('Jane Doe');
});

it('returns 401 when fetching user without authentication', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401);
});

it('includes email_verified_at when verified', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    expect($response->json('data.email_verified_at'))->not->toBeNull();
});

it('shows null email_verified_at for unverified user', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    expect($response->json('data.email_verified_at'))->toBeNull();
});

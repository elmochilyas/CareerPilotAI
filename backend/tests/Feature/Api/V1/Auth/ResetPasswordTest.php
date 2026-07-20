<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class)->group('api', 'auth', 'reset-password');

it('resets password with valid token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'jane@example.com',
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);
});

it('rejects invalid token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'jane@example.com',
        'token' => 'invalid-token',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure(['message']);
});

it('rejects weak password', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'jane@example.com',
        'token' => $token,
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});

it('rejects missing required fields', function () {
    $response = $this->postJson('/api/v1/auth/reset-password', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email', 'token', 'password']);
});

it('rejects non-matching password confirmation', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'jane@example.com',
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});

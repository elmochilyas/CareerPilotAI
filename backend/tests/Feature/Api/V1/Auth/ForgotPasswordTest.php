<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth', 'forgot-password');

it('sends password reset link for existing email', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'jane@example.com',
    ]);

    $response->assertStatus(202);
    $response->assertJsonStructure(['message']);
});

it('returns success for unknown email without leaking existence', function () {
    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(202);
    $response->assertJsonStructure(['message']);
});

it('rejects invalid email format', function () {
    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

it('hits rate limit after 5 forgot-password attempts in one minute', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);
        $response->assertStatus(202);
    }

    $response = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'jane@example.com',
    ]);
    $response->assertStatus(429);
});

it('rejects missing email field', function () {
    $response = $this->postJson('/api/v1/auth/forgot-password', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

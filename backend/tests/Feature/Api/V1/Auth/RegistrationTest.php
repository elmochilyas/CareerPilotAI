<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class)->group('api', 'auth', 'registration');

it('registers a new user successfully', function () {
    Event::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => ['id', 'full_name', 'email', 'role', 'account_status', 'created_at'],
    ]);

    expect($response->json('data.email'))->toBe('jane@example.com');
    expect($response->json('data.full_name'))->toBe('Jane Doe');
    expect($response->json('data.role'))->toBe('candidate');
    expect($response->json('data.account_status'))->toBe('active');

    Event::assertDispatched(Registered::class);
});

it('rejects duplicate email on registration', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
});

it('rejects weak password on registration', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});

it('rejects missing required fields on registration', function () {
    $response = $this->postJson('/api/v1/auth/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['full_name', 'email', 'password']);
});

it('hits rate limit after 10 registration attempts in one minute', function () {
    for ($i = 0; $i < 10; $i++) {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => "User $i",
            'email' => "user$i@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertStatus(201);
    }

    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Extra User',
        'email' => 'extra@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $response->assertStatus(429);
});

it('rejects registration with non-matching passwords', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'different',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('password');
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth', 'login');

it('logs in with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'full_name', 'email', 'role', 'account_status'],
    ]);

    expect($response->json('data.email'))->toBe('jane@example.com');

    $this->assertAuthenticated();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('rejects login for non-existent email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('rejects login for suspended account', function () {
    $user = User::factory()->suspended()->create([
        'email' => 'suspended@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(403);

    $this->assertGuest();
});

it('rejects login for disabled account with account_disabled code', function () {
    User::factory()->disabled()->create([
        'email' => 'disabled@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'disabled@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'account_disabled');
    $response->assertJsonPath('status', 403);

    $this->assertGuest();
});

it('returns generic invalid credentials for wrong password of a disabled account', function () {
    User::factory()->disabled()->create([
        'email' => 'disabled@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'disabled@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('allows login for active account unchanged', function () {
    $user = User::factory()->create([
        'email' => 'active@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'active@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.account_status'))->toBe('active');

    $this->assertAuthenticated();
});

it('allows login for unverified email and includes email_verified_at as null', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'unverified@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'unverified@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.email_verified_at'))->toBeNull();

    $this->assertAuthenticated();
});

it('hits rate limit after 5 failed login attempts', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret123'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);
    $response->assertStatus(429);
});

it('regenerates session after login', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $this->assertAuthenticated();
});

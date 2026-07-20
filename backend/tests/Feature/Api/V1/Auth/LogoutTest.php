<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth', 'logout');

it('logs out an authenticated user', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ])->assertStatus(200);

    $this->assertAuthenticated('web');

    $this->deleteJson('/api/v1/auth/logout')->assertStatus(204);

    $this->assertGuest('web');
});

it('returns 401 when logging out without authentication', function () {
    $response = $this->deleteJson('/api/v1/auth/logout');

    $response->assertStatus(401);
});

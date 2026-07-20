<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class)->group('api', 'auth', 'verification');

it('verifies email with valid hash', function () {
    $user = User::factory()->unverified()->create();

    $uri = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $response = $this->actingAs($user)->getJson($uri);

    $response->assertStatus(200);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects invalid verification hash', function () {
    $user = User::factory()->unverified()->create();

    $uri = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => 'invalid-hash'],
    );

    $response = $this->actingAs($user)->getJson($uri);

    $response->assertStatus(401);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends verification notification', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/email/verification-notification');

    $response->assertStatus(202);
    $response->assertJsonStructure(['message']);
});

it('does not resend verification if already verified', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/email/verification-notification');

    $response->assertStatus(200);
    $response->assertJsonStructure(['message']);
});

it('requires authentication for email verification resend', function () {
    $response = $this->postJson('/api/v1/email/verification-notification');

    $response->assertStatus(401);
});

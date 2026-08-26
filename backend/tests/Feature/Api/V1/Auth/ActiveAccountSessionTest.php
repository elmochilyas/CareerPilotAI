<?php

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'auth', 'account-status');

it('rejects requests from a disabled account session with account_disabled', function () {
    $user = User::factory()->disabled()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'account_disabled');
    $response->assertJsonPath('status', 403);
    $response->assertJsonPath('request_id', fn ($value) => is_string($value) && $value !== '');
});

it('rejects requests from a suspended account session with account_disabled', function () {
    $user = User::factory()->suspended()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    $response->assertStatus(403);
    $response->assertJsonPath('code', 'account_disabled');
});

it('allows requests from an active account session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/me');

    $response->assertStatus(200);
    expect($response->json('data.account_status'))->toBe('active');
});

it('rejects disabled account sessions on any authenticated route, not only me', function () {
    $user = User::factory()->disabled()->create();
    CandidateProfile::factory()->create(['user_id' => $user->id]);

    $profileResponse = $this->actingAs($user)->getJson('/api/v1/profile');
    $skillsResponse = $this->actingAs($user)->getJson('/api/v1/candidate/skills');

    $profileResponse->assertStatus(403);
    $profileResponse->assertJsonPath('code', 'account_disabled');
    $skillsResponse->assertStatus(403);
    $skillsResponse->assertJsonPath('code', 'account_disabled');
});

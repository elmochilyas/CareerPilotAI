<?php

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'profile');

it('creates and partially updates one profile per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson('/api/v1/profile', ['headline' => 'Laravel Developer'])->assertOk()->assertJsonPath('data.profile_completion', 10);
    $updatedAt = $user->candidateProfile->updated_at->toIso8601String();
    $this->actingAs($user)->patchJson('/api/v1/profile', ['city' => 'Casablanca', 'updated_at' => $updatedAt])->assertOk()->assertJsonPath('data.headline', 'Laravel Developer');
    expect($user->candidateProfile()->count())->toBe(1);
});

it('validates salary urls enums and privileged fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson('/api/v1/profile', ['salary_min' => 80000, 'salary_max' => 40000, 'linkedin_url' => 'javascript:alert(1)', 'work_modes' => ['invalid_mode'], 'user_id' => 999])
        ->assertUnprocessable()->assertJsonValidationErrors(['salary_max', 'linkedin_url', 'work_modes.0', 'user_id']);
});

it('accepts a single salary value and a valid https url', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson('/api/v1/profile', ['salary_min' => 30000, 'linkedin_url' => 'https://linkedin.com/in/user'])->assertOk();
});

it('rejects stale profile updates', function () {
    $profile = CandidateProfile::factory()->create();
    $this->actingAs($profile->user)->patchJson('/api/v1/profile', ['headline' => 'Changed', 'updated_at' => '2020-01-01T00:00:00Z'])->assertConflict()->assertJsonPath('code', 'profile_conflict');
});

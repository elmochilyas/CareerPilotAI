<?php

use App\Models\CandidateProfile;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'show');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
});

it('returns 401 for unauthenticated request', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $this->getJson("/api/v1/resumes/{$resume->id}")
        ->assertStatus(401);
});

it('returns the authenticated users own resume', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/resumes/{$resume->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $resume->id);
    $response->assertJsonStructure([
        'data' => [
            'id', 'candidate_profile_id', 'opportunity_id', 'status',
            'title', 'content', 'version_no', 'approved_at',
            'created_at', 'updated_at',
        ],
    ]);
});

it('returns 404 for non-existent resume', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes/999999');

    $response->assertStatus(404);
});

it('returns 404 for another users resume', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/resumes/{$resume->id}");

    $response->assertStatus(404);
});

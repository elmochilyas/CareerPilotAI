<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'index');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
});

it('returns 401 for unauthenticated request', function () {
    $this->getJson('/api/v1/resumes')
        ->assertStatus(401);
});

it('lists only the authenticated user resumes', function () {
    Resume::factory()->count(3)->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    Resume::factory()->count(2)->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
});

it('returns paginated results with meta', function () {
    Resume::factory()->count(3)->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'status', 'title', 'version_no', 'created_at', 'updated_at'],
        ],
        'meta' => ['next_cursor', 'has_more'],
    ]);
});

it('filters by status', function () {
    Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes?status=draft');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('draft');
});

it('filters by opportunity_id', function () {
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    $otherOpportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);

    Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $opportunity->id,
    ]);
    Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $otherOpportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/resumes?opportunity_id={$opportunity->id}");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.opportunity_id'))->toBe($opportunity->id);
});

it('lists resumes through the opportunity-scoped route', function () {
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opportunity->id}/resumes?opportunity_id={$opportunity->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('does not expose other users resumes', function () {
    Resume::factory()->count(5)->create([
        'candidate_profile_id' => $this->otherProfile->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/resumes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

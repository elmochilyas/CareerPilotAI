<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'store');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
});

it('returns 401 for unauthenticated request', function () {
    $response = $this->postJson('/api/v1/resumes', [
        'opportunity_id' => $this->opportunity->id,
    ]);

    $response->assertStatus(401);
});

it('returns 422 when opportunity_id is missing', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('opportunity_id');
});

it('returns 422 for non-existent opportunity_id', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', [
            'opportunity_id' => 999999,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('opportunity_id');
});

it('creates a resume for a confirmed opportunity', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'candidate_profile_id',
            'opportunity_id',
            'status',
            'title',
            'content',
            'version_no',
            'approved_at',
            'created_at',
            'updated_at',
        ],
    ]);
});

it('creates a resume through the opportunity-scoped route', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/resumes", [
            'opportunity_id' => $this->opportunity->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.opportunity_id', $this->opportunity->id);
});

it('returns status draft and version 1 on creation', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ]);

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.version_no'))->toBe(1);
});

it('returns 409 when a draft already exists for the same profile and opportunity', function () {
    Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ]);

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'existing_draft');
});

it('persists the resume in the database', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ])
        ->assertStatus(201);

    $this->assertDatabaseHas('resumes', [
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'status' => 'draft',
        'version_no' => 1,
    ]);
});

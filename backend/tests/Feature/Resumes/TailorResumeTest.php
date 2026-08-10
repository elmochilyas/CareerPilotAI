<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\ProfileItem;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'tailor');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $this->profile->id]);
    $this->otherUser = User::factory()->create();
    $this->otherProfile = CandidateProfile::factory()->create(['user_id' => $this->otherUser->id]);
    $this->otherOpportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $this->otherProfile->id]);
});

it('returns 401 for unauthenticated request', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $this->postJson("/api/v1/resumes/{$resume->id}/tailor")
        ->assertStatus(401);
});

it('triggers tailoring on the authenticated users own draft resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'proposals',
            'metadata',
        ],
    ]);
});

it('returns 404 when trying to tailor another users resume', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->otherProfile->id,
        'job_opportunity_id' => $this->otherOpportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor");

    $response->assertStatus(404);
});

it('returns 403 when trying to tailor an approved resume', function () {
    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor");

    $response->assertStatus(403);
});

it('returns 200 with tailoring result', function () {
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor");

    $response->assertStatus(200);
    expect($response->json('data.proposals'))->toBeArray();
    expect($response->json('data.metadata'))->toBeArray();
});

it('persists deterministic trusted content for previewing', function () {
    $profileItem = ProfileItem::factory()->experience()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'organization' => 'Example Company',
        'description' => 'Built tested Laravel APIs.',
    ]);
    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'content' => [],
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/resumes/{$resume->id}/tailor", ['use_ai' => false])
        ->assertOk();

    $resume->refresh();

    expect($resume->content)->toHaveKey('experience')
        ->and($resume->content['experience']['items'][0]['source_id'])->toBe($profileItem->id)
        ->and($resume->content['experience']['items'][0]['source_type'])->toBe('profile_item')
        ->and($resume->content['experience']['items'][0]['text'])->toContain('Built tested Laravel APIs.')
        ->and($resume->generated_by)->toBe('manual')
        ->and($resume->ai_metadata['fallback_reason'])->toBe('ai_disabled');
});

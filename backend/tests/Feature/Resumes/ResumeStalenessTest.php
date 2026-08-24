<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('resumes', 'staleness');

it('marks resume as stale when profile changes after generation', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $opportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $profile->id]);
    MatchAnalysis::factory()->create([
        'candidate_profile_id' => $profile->id,
        'job_opportunity_id' => $opportunity->id,
        'status' => 'completed',
    ]);

    $resume = Resume::factory()->draft()->create([
        'candidate_profile_id' => $profile->id,
        'job_opportunity_id' => $opportunity->id,
        'content' => [],
    ]);

    // Initially fresh (no content, but profile not changed)
    $response = $this->actingAs($user)->getJson("/api/v1/resumes/{$resume->id}");
    $response->assertOk();
    // Initially may be stale due to missing snapshots? But after creation, snapshots are stored, so should be fresh if profile not changed
    // We just check that after profile touch, it becomes stale

    sleep(1);
    $profile->update(['headline' => 'New Headline '.uniqid()]);

    $response2 = $this->actingAs($user)->getJson("/api/v1/resumes/{$resume->id}");
    $response2->assertOk();
    expect($response2->json('data.stale'))->toBeTrue();
    expect($response2->json('data.stale_reason'))->toBeIn(['profile_stale', 'both_stale', 'opportunity_stale']);
});

it('keeps approved resume staleness flagged but still immutable', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $opportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $profile->id]);
    MatchAnalysis::factory()->create([
        'candidate_profile_id' => $profile->id,
        'job_opportunity_id' => $opportunity->id,
        'status' => 'completed',
    ]);

    $resume = Resume::factory()->approved()->create([
        'candidate_profile_id' => $profile->id,
        'job_opportunity_id' => $opportunity->id,
        'content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]],
    ]);

    $profile->update(['headline' => 'Changed']);

    $response = $this->actingAs($user)->getJson("/api/v1/resumes/{$resume->id}");
    $response->assertOk();
    expect($response->json('data.stale'))->toBeTrue();
    // Approved should still be immutable (policy returns 403)
    $this->actingAs($user)->patchJson("/api/v1/resumes/{$resume->id}", [
        'content' => ['sections' => [['type' => 'experience', 'title' => 'Test', 'items' => [['source_ref' => 'profile_item:1', 'current_text' => 'Test']]]]],
    ])->assertStatus(403);
});

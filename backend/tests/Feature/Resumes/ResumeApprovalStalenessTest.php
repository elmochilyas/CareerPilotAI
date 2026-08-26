<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'staleness-approval');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'title' => 'Backend Developer',
        'summary' => 'Original summary',
    ]);
    MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
});

it('blocks approval of a stale draft when profile changed and returns 409 resume_stale', function () {
    $create = $this->actingAs($this->user)->postJson('/api/v1/resumes', ['opportunity_id' => $this->opportunity->id]);
    $create->assertStatus(201);
    $resumeId = $create->json('data.id');
    Resume::whereKey($resumeId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello world']]]]]);

    $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")
        ->assertOk()
        ->assertJsonPath('data.stale', false);

    $this->profile->update(['headline' => 'New Headline '.uniqid()]);

    $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")
        ->assertOk()
        ->assertJsonPath('data.stale', true)
        ->assertJsonPath('data.stale_reason', fn ($v) => in_array($v, ['profile_stale', 'both_stale'], true));

    $approve = $this->actingAs($this->user)->postJson("/api/v1/resumes/{$resumeId}/approve");
    $approve->assertStatus(409);
    $approve->assertJsonPath('code', 'resume_stale');
    expect($approve->json('detail'))->toContain('Regenerate the CV before approving it');
    expect(Resume::find($resumeId)->status->value)->toBe('draft');
});

it('blocks approval when opportunity changed', function () {
    $create = $this->actingAs($this->user)->postJson('/api/v1/resumes', ['opportunity_id' => $this->opportunity->id]);
    $create->assertStatus(201);
    $resumeId = $create->json('data.id');
    Resume::whereKey($resumeId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello world']]]]]);

    $this->opportunity->update(['title' => 'Senior Backend Developer '.uniqid()]);

    $approve = $this->actingAs($this->user)->postJson("/api/v1/resumes/{$resumeId}/approve");
    $approve->assertStatus(409);
    $approve->assertJsonPath('code', 'resume_stale');
});

it('allows approval after regeneration via tailoring refreshes snapshots', function () {
    $create = $this->actingAs($this->user)->postJson('/api/v1/resumes', ['opportunity_id' => $this->opportunity->id]);
    $create->assertStatus(201);
    $resumeId = $create->json('data.id');
    Resume::whereKey($resumeId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]]]);

    $this->profile->update(['headline' => 'Mutated Headline '.uniqid()]);

    $this->actingAs($this->user)->postJson("/api/v1/resumes/{$resumeId}/tailor", ['use_ai' => false])->assertStatus(200);

    $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")
        ->assertOk()
        ->assertJsonPath('data.stale', false);

    $resume = Resume::findOrFail($resumeId);
    if (empty($resume->content)) {
        $resume->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]]]);
    }

    $this->actingAs($this->user)->postJson("/api/v1/resumes/{$resumeId}/approve")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'approved');
});

it('keeps approved resumes immutable and flags them stale without affecting immutability', function () {
    $create = $this->actingAs($this->user)->postJson('/api/v1/resumes', ['opportunity_id' => $this->opportunity->id]);
    $create->assertStatus(201);
    $resumeId = $create->json('data.id');
    Resume::whereKey($resumeId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]]]);

    $this->actingAs($this->user)->postJson("/api/v1/resumes/{$resumeId}/approve")->assertStatus(200);
    expect(Resume::find($resumeId)->status->value)->toBe('approved');

    $this->profile->update(['headline' => 'After Approval '.uniqid()]);

    $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")
        ->assertOk()
        ->assertJsonPath('data.stale', true);

    $this->actingAs($this->user)->patchJson("/api/v1/resumes/{$resumeId}", [
        'content' => ['sections' => [['type' => 'experience', 'title' => 'Test', 'items' => [['source_ref' => 'profile_item:1', 'current_text' => 'Test']]]]],
    ])->assertStatus(403);
});

it('reports stale flags correctly in resource after source mutations', function () {
    $create = $this->actingAs($this->user)->postJson('/api/v1/resumes', ['opportunity_id' => $this->opportunity->id]);
    $create->assertStatus(201);
    $resumeId = $create->json('data.id');
    Resume::whereKey($resumeId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]]]);

    $fresh = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")->json('data');
    expect($fresh['stale'])->toBeFalse();
    expect($fresh['stale_reason'])->toBeNull();

    $this->opportunity->update(['summary' => 'New opportunity summary '.uniqid()]);

    $stale = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resumeId}")->json('data');
    expect($stale['stale'])->toBeTrue();
    expect($stale['stale_reason'])->toBeIn(['opportunity_stale', 'both_stale']);
});

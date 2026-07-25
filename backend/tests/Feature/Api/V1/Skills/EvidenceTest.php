<?php

use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'evidence');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->skill = Skill::factory()->create();
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $this->skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);
    $this->candidateSkillId = $cs->json('data.id');
});

it('adds url evidence', function () {
    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert', 'label' => 'My Cert',
    ]);
    $response->assertStatus(200);
    expect($response->json('data.evidence'))->toHaveCount(1);
    expect($response->json('data.evidence.0.type'))->toBe('url');
    expect($response->json('data.evidence.0.label'))->toBe('My Cert');
});

it('adds profile-item evidence for experience', function () {
    $item = ProfileItem::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'type' => 'experience',
    ]);

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'profile_item', 'value' => (string) $item->id,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.evidence'))->toHaveCount(1);
});

it('rejects javascript url scheme', function () {
    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'javascript:alert(1)',
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('skill_evidence_invalid');
});

it('rejects data url scheme', function () {
    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'data:text/plain;base64,SGVsbG8=',
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('skill_evidence_invalid');
});

it('rejects non-https url', function () {
    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'http://example.com',
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('skill_evidence_invalid');
});

it('rejects cross-user profile item', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $item = ProfileItem::factory()->create([
        'candidate_profile_id' => $otherProfile->id,
        'type' => 'experience',
    ]);

    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'profile_item', 'value' => (string) $item->id,
    ]);

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('profile_item_evidence_not_owned');
});

it('rejects non-existent profile item', function () {
    $response = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'profile_item', 'value' => '99999',
    ]);
    $response->assertStatus(422);
    expect($response->json('code'))->toBe('profile_item_evidence_not_owned');
});

it('updates evidence label', function () {
    $ev = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert', 'label' => 'Old Label',
    ]);
    $key = $ev->json('data.evidence.0.key');

    $response = $this->withToken($this->token)->patchJson(
        "/api/v1/candidate/skills/{$this->candidateSkillId}/evidence/{$key}",
        ['label' => 'New Label'],
    );

    $response->assertStatus(200);
    expect($response->json('data.evidence.0.label'))->toBe('New Label');
});

it('removes evidence', function () {
    $ev = $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$this->candidateSkillId}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);
    $key = $ev->json('data.evidence.0.key');

    $response = $this->withToken($this->token)->deleteJson(
        "/api/v1/candidate/skills/{$this->candidateSkillId}/evidence/{$key}",
    );

    $response->assertStatus(200);
    expect($response->json('data.evidence'))->toHaveCount(0);
});

it('returns 404 for non-existent evidence key', function () {
    $response = $this->withToken($this->token)->deleteJson(
        "/api/v1/candidate/skills/{$this->candidateSkillId}/evidence/non-existent-key",
    );

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('skill_evidence_not_found');
});

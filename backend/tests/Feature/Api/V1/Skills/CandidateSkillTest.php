<?php

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'candidate');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('creates a claimed skill from catalog', function () {
    $skill = Skill::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/candidate/skills', [
            'skill_id' => $skill->id,
            'state' => 'claimed',
            'proficiency_level' => 'intermediate',
            'years_experience' => 3,
        ]);

    $response->assertStatus(201);
    expect($response->json('data.state'))->toBe('claimed');
    expect($response->json('data.skill.name'))->toBe($skill->name);
});

it('creates a learning skill', function () {
    $skill = Skill::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/candidate/skills', [
            'skill_id' => $skill->id,
            'state' => 'learning',
            'proficiency_level' => 'beginner',
        ]);

    $response->assertStatus(201);
    expect($response->json('data.state'))->toBe('learning');
});

it('rejects duplicate canonical skill', function () {
    $skill = Skill::factory()->create();

    $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ]);

    $response = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => 'claimed',
        'proficiency_level' => 'advanced',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_duplicate');
});

it('creates a custom skill', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/candidate/skills', [
            'custom_skill_name' => 'My Custom Skill',
            'state' => 'claimed',
            'proficiency_level' => 'intermediate',
        ]);

    $response->assertStatus(201);
    expect($response->json('data.is_custom'))->toBeTrue();
});

it('rejects invalid proficiency level', function () {
    $skill = Skill::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/candidate/skills', [
            'skill_id' => $skill->id,
            'state' => 'claimed',
            'proficiency_level' => 'super-advanced',
        ]);

    $response->assertStatus(422);
});

it('lists skills with state filter', function () {
    $skill1 = Skill::factory()->create(['name' => 'PHP']);
    $skill2 = Skill::factory()->create(['name' => 'Python']);

    $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill1->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);
    $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill2->id, 'state' => 'learning', 'proficiency_level' => 'beginner',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/candidate/skills?state=learning');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.state'))->toBe('learning');
});

it('returns empty list when no skills', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/candidate/skills');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

it('shows owned skill', function () {
    $skill = Skill::factory()->create();
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/candidate/skills/{$cs->json('data.id')}");

    $response->assertStatus(200);
});

it('returns 404 for cross-user skill', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $skill = Skill::factory()->create();
    $cs = CandidateSkill::factory()->create([
        'candidate_profile_id' => $otherProfile->id,
        'skill_id' => $skill->id,
    ]);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/candidate/skills/{$cs->id}");

    $response->assertStatus(404);
});

it('updates proficiency level', function () {
    $skill = Skill::factory()->create();
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);

    $response = $this->withToken($this->token)->patchJson("/api/v1/candidate/skills/{$cs->json('data.id')}", [
        'proficiency_level' => 'advanced',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.proficiency_level'))->toBe('advanced');
});

it('deletes claimed skill', function () {
    $skill = Skill::factory()->create();
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ]);

    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/candidate/skills/{$cs->json('data.id')}");

    $response->assertStatus(204);
});

it('rejects delete for verified skill', function () {
    $skill = Skill::factory()->create();
    $cs = $this->withToken($this->token)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id, 'state' => 'claimed', 'proficiency_level' => 'intermediate',
    ])->json('data');

    $this->withToken($this->token)->postJson("/api/v1/candidate/skills/{$cs['id']}/evidence", [
        'type' => 'url', 'value' => 'https://example.com/cert',
    ]);

    $this->withToken($this->token)->patchJson("/api/v1/candidate/skills/{$cs['id']}", [
        'state' => 'verified',
    ]);

    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/candidate/skills/{$cs['id']}");

    $response->assertStatus(422);
    expect($response->json('code'))->toBe('candidate_skill_removal_forbidden');
});

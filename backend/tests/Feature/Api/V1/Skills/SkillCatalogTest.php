<?php

use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'skills', 'catalog');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('searches skills with query', function () {
    Skill::factory()->create(['name' => 'PHP', 'category' => 'language']);
    Skill::factory()->create(['name' => 'Python', 'category' => 'language']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills?q=PHP');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('PHP');
});

it('returns all skills without query', function () {
    Skill::factory(5)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(5);
});

it('rejects search query less than 2 characters', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills?q=P');

    $response->assertStatus(422);
});

it('filters skills by category', function () {
    Skill::factory()->create(['name' => 'PHP', 'category' => 'language']);
    Skill::factory()->create(['name' => 'Laravel', 'category' => 'framework']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills?category=language');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('PHP');
});

it('paginates skill results', function () {
    Skill::factory(25)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills?page=1');

    $response->assertStatus(200);
    expect($response->json('meta'))->toHaveKey('current_page');
});

it('shows a single skill with aliases', function () {
    $skill = Skill::factory()->create(['name' => 'Laravel']);
    SkillAlias::factory()->create(['skill_id' => $skill->id, 'alias' => 'Laravel Framework']);

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/skills/{$skill->id}");

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Laravel');
    expect($response->json('data.aliases'))->toContain('Laravel Framework');
});

it('returns 404 for non-existent skill', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/skills/99999');

    $response->assertStatus(404);
});

it('returns 401 when unauthenticated', function () {
    $response = $this->getJson('/api/v1/skills');

    $response->assertStatus(401);
});

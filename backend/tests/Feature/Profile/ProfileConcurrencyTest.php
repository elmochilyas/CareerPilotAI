<?php

use App\Domain\Profile\Actions\CreateProfileItemAction;
use App\Domain\Profile\Data\ProfileItemData;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('profile', 'concurrency');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
});

it('prevents two simultaneous equivalent profile item creations to one record', function () {
    $action = app(CreateProfileItemAction::class);

    $data1 = ProfileItemData::from([
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'location' => 'Paris',
        'start_date' => '2022-01-01',
        'end_date' => '2023-01-01',
        'description' => 'First',
        'is_current' => false,
    ]);

    $data2 = ProfileItemData::from([
        'type' => 'experience',
        'title' => ' backend developer ',
        'organization' => ' acme ',
        'location' => 'Paris',
        'start_date' => '2022-01-01',
        'end_date' => '2023-01-01',
        'description' => 'Second',
        'is_current' => false,
    ]);

    $item1 = $action->execute($this->user, $data1);
    expect($item1)->not->toBeNull();

    expect(fn () => $action->execute($this->user, $data2))->toThrow(Exception::class);

    try {
        $action->execute($this->user, $data2);
        $this->fail('Should have thrown');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain('already exists');
    }

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('rejects manual Backend Developer and backend developer same company/date as duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2022-01-01',
        'end_date' => '2023-01-01',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => ' backend developer ',
        'organization' => ' acme ',
        'start_date' => '2022-01-01',
        'end_date' => '2023-01-01',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('profile_item_duplicate');
    expect($response->json('detail'))->toContain('already exists');

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(1);
});

it('allows legitimate same role/company with non-overlapping dates', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2020-01-01',
        'end_date' => '2021-12-31',
    ])->assertStatus(201);

    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
        'start_date' => '2024-01-01',
        'end_date' => '2025-12-31',
    ])->assertStatus(201);

    expect(ProfileItem::where('candidate_profile_id', $this->profile->id)->count())->toBe(2);
});

it('prevents manual education normalized duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'education',
        'title' => 'Bachelor of Science',
        'organization' => 'University of Casablanca',
        'start_date' => '2020-09-01',
    ])->assertStatus(201);

    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'education',
        'title' => ' bachelor of science ',
        'organization' => ' university of casablanca ',
        'start_date' => '2020-09-01',
    ])->assertStatus(409)->assertJsonPath('code', 'profile_item_duplicate');
});

it('prevents manual project normalized duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'project',
        'title' => 'LaraSkills',
        'organization' => null,
    ])->assertStatus(201);

    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'project',
        'title' => ' laraskills ',
        'organization' => null,
    ])->assertStatus(409);
});

it('prevents manual certification normalized duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'certification',
        'title' => 'AWS Certified',
        'organization' => 'Amazon',
    ])->assertStatus(201);

    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'certification',
        'title' => ' aws certified ',
        'organization' => ' amazon ',
    ])->assertStatus(409);
});

it('rejects canonical skill duplicate via API', function () {
    $skill = Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);

    $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_duplicate');
});

it('prevents alias JS when JavaScript already exists', function () {
    $skill = Skill::factory()->create(['name' => 'JavaScript', 'normalized_name' => 'javascript']);
    SkillAlias::factory()->create(['skill_id' => $skill->id, 'alias' => 'JS']);

    $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'skill_id' => $skill->id,
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ])->assertStatus(201);

    // Try to add via alias as custom
    $response = $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'custom_skill_name' => 'JS',
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('candidate_skill_duplicate');
});

it('prevents custom skill normalized duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'custom_skill_name' => 'MyCustomSkill',
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)->postJson('/api/v1/candidate/skills', [
        'custom_skill_name' => ' mycustomskill ',
        'state' => 'claimed',
        'proficiency_level' => 'intermediate',
    ]);

    $response->assertStatus(409);
});

it('returns 409 domain response not raw DB exception for duplicate', function () {
    $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)->postJson('/api/v1/profile/items', [
        'type' => 'experience',
        'title' => 'Backend Developer',
        'organization' => 'Acme',
    ]);

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('profile_item_duplicate');
    expect($response->json('detail'))->not->toContain('SQL');
    expect($response->json('detail'))->not->toContain('Duplicate entry');
});

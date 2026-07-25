<?php

use App\Domain\Skills\Actions\SearchSkillsAction;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('skills', 'unit');

beforeEach(function () {
    $this->action = app(SearchSkillsAction::class);
});

it('finds skill by name case-insensitively', function () {
    Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);

    $results = $this->action->execute(q: 'php');
    expect($results)->toHaveCount(1);
});

it('finds skill by alias', function () {
    $skill = Skill::factory()->create(['name' => 'JavaScript']);
    SkillAlias::factory()->create(['skill_id' => $skill->id, 'alias' => 'JS']);

    $results = $this->action->execute(q: 'JS');
    expect($results)->toHaveCount(1);
});

it('returns empty results for no match', function () {
    $results = $this->action->execute(q: 'zzzznotexist');
    expect($results)->toHaveCount(0);
});

<?php

use App\Domain\Opportunities\Actions\ResolveJobSkillsAction;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('opportunities', 'skills');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    $this->action = new ResolveJobSkillsAction;
});

it('resolves exact skill name match', function () {
    $skill = Skill::factory()->create(['name' => 'Laravel', 'normalized_name' => 'laravel']);

    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'Laravel'],
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion->refresh();
    expect($suggestion->resolution)->toBe(SkillResolutionState::Exact);
    expect($suggestion->resolved_skill_id)->toBe($skill->id);
});

it('resolves via alias', function () {
    $skill = Skill::factory()->create(['name' => 'JavaScript', 'normalized_name' => 'javascript']);
    SkillAlias::factory()->create([
        'skill_id' => $skill->id,
        'alias' => 'js',
    ]);

    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'JS'],
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion->refresh();
    expect($suggestion->resolution)->toBe(SkillResolutionState::Alias);
    expect($suggestion->resolved_skill_id)->toBe($skill->id);
});

it('marks as ambiguous when multiple skills match', function () {
    Skill::factory()->create(['name' => 'React.js', 'normalized_name' => 'reactjs']);
    Skill::factory()->create(['name' => 'React Native', 'normalized_name' => 'react_native']);

    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'React'],
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion->refresh();
    expect($suggestion->resolution)->toBe(SkillResolutionState::Ambiguous);
});

it('marks as unknown when no match found', function () {
    $suggestion = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'ObscureFramework2026'],
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion->refresh();
    expect($suggestion->resolution)->toBe(SkillResolutionState::Unknown);
});

it('skips already resolved suggestions', function () {
    $skill = Skill::factory()->create(['name' => 'PHP', 'normalized_name' => 'php']);

    $suggestion = JobOpportunitySuggestion::factory()->resolved()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'PHP'],
        'resolved_skill_id' => $skill->id,
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion->refresh();
    expect($suggestion->resolution)->toBe(SkillResolutionState::Exact);
});

it('prevents duplicate resolution of same skill across suggestions', function () {
    $skill = Skill::factory()->create(['name' => 'Laravel', 'normalized_name' => 'laravel']);

    JobOpportunitySuggestion::factory()->resolved()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'required_skill',
        'extracted_value' => ['name' => 'Laravel'],
        'resolved_skill_id' => $skill->id,
    ]);

    $suggestion2 = JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $this->ingestion->id,
        'type' => 'preferred_skill',
        'extracted_value' => ['name' => 'Laravel'],
    ]);

    $result = $this->action->execute($this->ingestion);

    $suggestion2->refresh();
    expect($suggestion2->resolution)->toBe(SkillResolutionState::Exact);
});

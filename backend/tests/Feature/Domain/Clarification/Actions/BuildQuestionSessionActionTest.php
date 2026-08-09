<?php

use App\Domain\Clarification\Actions\BuildQuestionSessionAction;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Domain\Clarification\Services\Contracts\ClarificationAssistant;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Skills\Enums\SkillState;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\FakeClarificationAssistant;

uses(RefreshDatabase::class)->group('clarifications', 'actions');

if (! function_exists('persistClarificationFinding')) {
    function persistClarificationFinding(MatchAnalysis $analysis, array $attributes = []): MatchFinding
    {
        return MatchFinding::factory()->create(array_merge([
            'match_analysis_id' => $analysis->id,
            'importance' => MatchImportance::Required,
            'category' => MatchCategory::RequiredSkills->value,
            'match_state' => MatchState::Gap,
            'factor' => 0.00,
            'requirement_text' => 'Docker',
            'requirement_label' => 'Docker',
        ], $attributes));
    }
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->analysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $this->fakeAssistant = new FakeClarificationAssistant;
    $this->app->instance(ClarificationAssistant::class, $this->fakeAssistant);
    $this->action = app(BuildQuestionSessionAction::class);
});

it('creates a pending question for an eligible required-skill gap', function () {
    persistClarificationFinding($this->analysis);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1);
    expect($created[0]->status)->toBe(ClarificationQuestionStatus::Pending)
        ->and($created[0]->question_type)->toBe(ClarificationQuestionType::YesNoWithDetails)
        ->and($created[0]->template_key)->toBe('skill_missing_required')
        ->and($created[0]->question_no)->toBe(1)
        ->and($created[0]->prompt)->toContain('Docker')
        ->and($created[0]->detail)->toContain('required for this role');
});

it('creates a question for an eligible claimed-without-evidence partial', function () {
    $skill = CandidateSkill::factory()->claimed()->create(['candidate_profile_id' => $this->profile->id]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'matched_candidate_skill_id' => $skill->id,
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1)
        ->and($created[0]->template_key)->toBe('skill_claimed_no_evidence_required');
});

it('keeps a preferred partial eligible but never a preferred gap', function () {
    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1)
        ->and($created[0]->template_key)->toBe('skill_claimed_no_evidence_preferred');
});

it('excludes low-impact preferred gaps', function () {
    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Gap,
        'factor' => 0.00,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('excludes matched findings', function () {
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Matched,
        'factor' => 1.00,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('excludes learning partials below the eligible factor', function () {
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.20,
        'requirement_text' => 'Kubernetes',
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('excludes findings whose candidate skill is verified', function () {
    $skill = CandidateSkill::factory()->verified()->create(['candidate_profile_id' => $this->profile->id]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'matched_candidate_skill_id' => $skill->id,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('excludes findings whose candidate skill is rejected or archived', function (SkillState $state) {
    $skill = CandidateSkill::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'state' => $state,
        'evidence' => null,
    ]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'matched_candidate_skill_id' => $skill->id,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
})->with([
    'rejected' => [SkillState::Rejected],
    'archived' => [SkillState::Archived],
]);

it('caps the session at three questions', function () {
    foreach (['Docker', 'Redis', 'Kubernetes', 'GraphQL', 'Terraform'] as $requirement) {
        persistClarificationFinding($this->analysis, [
            'requirement_text' => $requirement,
            'requirement_label' => $requirement,
        ]);
    }

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(3);
});

it('orders questions by impact, then factor gap', function () {
    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'Laravel',
        'requirement_label' => 'Laravel',
    ]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Gap,
        'factor' => 0.00,
        'requirement_text' => 'Docker',
        'requirement_label' => 'Docker',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(3)
        ->and($created[0]->template_key)->toBe('skill_missing_required')
        ->and($created[1]->template_key)->toBe('skill_claimed_no_evidence_required')
        ->and($created[2]->template_key)->toBe('skill_claimed_no_evidence_preferred');
});

it('creates a question for an eligible required-skill unknown', function () {
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Unknown,
        'requirement_text' => 'Kafka',
        'requirement_label' => 'Kafka',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1)
        ->and($created[0]->template_key)->toBe('skill_missing_required')
        ->and($created[0]->prompt)->toContain('Kafka');
});

it('excludes low-impact preferred unknowns', function () {
    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Unknown,
        'factor' => 0.00,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('excludes unclassified unknowns with no resolvable template', function () {
    persistClarificationFinding($this->analysis, [
        'category' => null,
        'match_state' => MatchState::Unknown,
        'factor' => 0.00,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('reports how many questions generation would create and zeroes it after questioning', function () {
    expect($this->action->eligibleCount($this->analysis))->toBe(0);

    persistClarificationFinding($this->analysis, ['requirement_text' => 'Docker', 'requirement_label' => 'Docker']);

    expect($this->action->eligibleCount($this->analysis))->toBe(1);

    $this->action->execute($this->analysis);

    expect($this->action->eligibleCount($this->analysis))->toBe(0);

    persistClarificationFinding($this->analysis, ['requirement_text' => 'Redis', 'requirement_label' => 'Redis']);

    expect($this->action->eligibleCount($this->analysis))->toBe(1);
});

it('returns an empty session when no findings are eligible', function () {
    persistClarificationFinding($this->analysis, ['match_state' => MatchState::Matched, 'factor' => 1.00]);
    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Gap,
        'factor' => 0.00,
    ]);

    expect($this->action->execute($this->analysis))->toBeEmpty();
});

it('never creates a duplicate question for the same finding', function () {
    $finding = persistClarificationFinding($this->analysis);

    $this->action->execute($this->analysis);
    $created = $this->action->execute($this->analysis);

    expect($created)->toBeEmpty();
    expect(ClarificationQuestion::where('match_finding_id', $finding->id)->count())->toBe(1);
});

it('derives the select type and options from the versioned template', function () {
    persistClarificationFinding($this->analysis, [
        'category' => MatchCategory::LanguageSoft->value,
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'French',
        'requirement_label' => 'French',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1);
    expect($created[0]->question_type)->toBe(ClarificationQuestionType::Select)
        ->and($created[0]->options_json)->toBe(['Basic', 'Conversational', 'Professional', 'Fluent', 'Native'])
        ->and($created[0]->template_key)->toBe('language_ambiguous_required');
});

it('continues question numbering after existing questions', function () {
    $prior = MatchFinding::factory()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_state' => MatchState::Matched,
        'factor' => 1.00,
    ]);
    ClarificationQuestion::factory()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $prior->id,
        'question_no' => 4,
    ]);
    persistClarificationFinding($this->analysis);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1)
        ->and($created[0]->question_no)->toBe(5);
});

it('records no assistant metadata when the assistant is disabled', function () {
    Config::set('clarification.assistant.enabled', false);
    persistClarificationFinding($this->analysis);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(1)
        ->and($created[0]->ai_metadata)->toBeNull();
});

it('applies assistant ranking and rewording when enabled and schema-valid', function () {
    Config::set('clarification.assistant.enabled', true);
    $this->fakeAssistant->reverseOrder = true;

    persistClarificationFinding($this->analysis, [
        'importance' => MatchImportance::Preferred,
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Partial,
        'factor' => 0.50,
        'requirement_text' => 'Laravel',
        'requirement_label' => 'Laravel',
    ]);
    persistClarificationFinding($this->analysis, [
        'match_state' => MatchState::Gap,
        'factor' => 0.00,
        'requirement_text' => 'Docker',
        'requirement_label' => 'Docker',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(3)
        ->and($created[0]->template_key)->toBe('skill_claimed_no_evidence_preferred')
        ->and($created[1]->template_key)->toBe('skill_claimed_no_evidence_required')
        ->and($created[2]->template_key)->toBe('skill_missing_required')
        ->and($created[0]->prompt)->toContain('[Reworded]')
        ->and($created[2]->prompt)->toContain('[Reworded]')
        ->and($created[0]->ai_metadata['assistant'])->toBeTrue()
        ->and($created[0]->ai_metadata['provider'])->toBe('fake')
        ->and($created[0]->ai_metadata['schema_version'])->toBe('1.0.0');
});

it('falls back to deterministic questions when the assistant provider fails', function () {
    Config::set('clarification.assistant.enabled', true);
    $this->fakeAssistant->mode = FakeClarificationAssistant::MODE_PROVIDER_FAILURE;

    persistClarificationFinding($this->analysis);
    persistClarificationFinding($this->analysis, [
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(2)
        ->and($created[0]->prompt)->not->toContain('[Reworded]')
        ->and($created[1]->prompt)->not->toContain('[Reworded]')
        ->and($created[0]->ai_metadata['assistant'])->toBeFalse()
        ->and($created[0]->ai_metadata['fallback_reason'])->toBe('ai_assistant_unavailable');
});

it('records the fallback reason when the assistant output is schema-invalid', function () {
    Config::set('clarification.assistant.enabled', true);
    $this->fakeAssistant->mode = FakeClarificationAssistant::MODE_INVALID_SCHEMA;

    persistClarificationFinding($this->analysis);
    persistClarificationFinding($this->analysis, [
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(2)
        ->and($created[0]->prompt)->not->toContain('[Reworded]')
        ->and($created[1]->prompt)->not->toContain('[Reworded]')
        ->and($created[0]->ai_metadata['assistant'])->toBeFalse()
        ->and($created[0]->ai_metadata['fallback_reason'])->toBe('ai_assistant_schema_invalid')
        ->and(ClarificationQuestion::where('match_analysis_id', $this->analysis->id)->count())->toBe(2);
});

it('persists the full deterministic set when the assistant output is malformed', function () {
    Config::set('clarification.assistant.enabled', true);
    $this->fakeAssistant->mode = FakeClarificationAssistant::MODE_MALFORMED;

    persistClarificationFinding($this->analysis);
    persistClarificationFinding($this->analysis, [
        'requirement_text' => 'Redis',
        'requirement_label' => 'Redis',
    ]);

    $created = $this->action->execute($this->analysis);

    expect($created)->toHaveCount(2)
        ->and($created[0]->prompt)->not->toContain('[Reworded]')
        ->and($created[0]->ai_metadata['fallback_reason'])->toBe('ai_assistant_malformed_response');
});

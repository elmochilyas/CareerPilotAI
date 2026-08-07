<?php

use App\Domain\Clarification\Enums\ClarificationFindingType;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Domain\Clarification\Services\QuestionTemplateRegistry;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;

beforeEach(function () {
    $this->registry = new QuestionTemplateRegistry;
});

it('derives skill missing from a required-skill gap', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'required_skills',
        'match_state' => MatchState::Gap,
    ])))->toBe(ClarificationFindingType::SkillMissing);
});

it('derives skill claimed from a required-skill partial', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'preferred_skills',
        'match_state' => MatchState::Partial,
    ])))->toBe(ClarificationFindingType::SkillClaimedNoEvidence);
});

it('derives experience types from the experience category', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'experience_education',
        'match_state' => MatchState::Gap,
    ])))->toBe(ClarificationFindingType::ExperienceMissing)
        ->and($this->registry->findingTypeFor(clarificationFinding([
            'category' => 'experience_education',
            'match_state' => MatchState::Partial,
        ])))->toBe(ClarificationFindingType::ExperienceAmbiguous);
});

it('derives language types from the language category', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'language_soft',
        'match_state' => MatchState::Gap,
    ])))->toBe(ClarificationFindingType::LanguageMissing)
        ->and($this->registry->findingTypeFor(clarificationFinding([
            'category' => 'language_soft',
            'match_state' => MatchState::Partial,
        ])))->toBe(ClarificationFindingType::LanguageAmbiguous);
});

it('derives evidence types from the evidence category', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'evidence',
        'match_state' => MatchState::Gap,
    ])))->toBe(ClarificationFindingType::EvidenceMissing)
        ->and($this->registry->findingTypeFor(clarificationFinding([
            'category' => 'evidence',
            'match_state' => MatchState::Partial,
        ])))->toBe(ClarificationFindingType::EvidenceAmbiguous);
});

it('returns null for matched or unknown states', function () {
    expect($this->registry->findingTypeFor(clarificationFinding([
        'category' => 'required_skills',
        'match_state' => MatchState::Matched,
    ])))->toBeNull()
        ->and($this->registry->findingTypeFor(clarificationFinding([
            'category' => 'required_skills',
            'match_state' => MatchState::Unknown,
        ])))->toBeNull();
});

it('returns null for unsupported categories', function () {
    expect($this->registry->findingTypeFor(clarificationFinding(['category' => null])))->toBeNull();
});

it('resolves a required skill-missing template with a stable key', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'requirement_text' => 'PHP development',
        'requirement_label' => 'PHP',
        'category' => 'required_skills',
        'match_state' => MatchState::Gap,
    ]));

    expect($template)->not->toBeNull()
        ->and($template->templateKey)->toBe('skill_missing_required')
        ->and($template->questionType)->toBe(ClarificationQuestionType::YesNoWithDetails)
        ->and($template->prompt)->toBe('Do you have experience with PHP?')
        ->and($template->detail)->toContain('required for this role')
        ->and($template->detail)->toContain('PHP');
});

it('resolves a preferred skill-missing template with a distinct key', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'importance' => MatchImportance::Preferred,
        'category' => 'required_skills',
        'match_state' => MatchState::Gap,
    ]));

    expect($template)->not->toBeNull()
        ->and($template->templateKey)->toBe('skill_missing_preferred')
        ->and($template->detail)->toContain('preferred for this role');
});

it('resolves an experience-ambiguous number template with a unit', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'category' => 'experience_education',
        'match_state' => MatchState::Partial,
    ]));

    expect($template)->not->toBeNull()
        ->and($template->templateKey)->toBe('experience_ambiguous_required')
        ->and($template->questionType)->toBe(ClarificationQuestionType::Number)
        ->and($template->unit)->toBe('years');
});

it('resolves a language-ambiguous select template with options', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'category' => 'language_soft',
        'match_state' => MatchState::Partial,
    ]));

    expect($template)->not->toBeNull()
        ->and($template->questionType)->toBe(ClarificationQuestionType::Select)
        ->and($template->options)->toBe(['Basic', 'Conversational', 'Professional', 'Fluent', 'Native']);
});

it('resolves null for a finding without a template', function () {
    expect($this->registry->resolve(clarificationFinding(['category' => null])))->toBeNull();
});

it('exposes every stable template key for each importance', function () {
    $keys = $this->registry->allTemplateKeys();

    expect(count($keys))->toBe(16)
        ->and($this->registry->has('skill_missing_required'))->toBeTrue()
        ->and($this->registry->has('skill_claimed_no_evidence_preferred'))->toBeTrue()
        ->and($this->registry->has('experience_ambiguous_required'))->toBeTrue()
        ->and($this->registry->has('language_missing_preferred'))->toBeTrue()
        ->and($this->registry->has('evidence_missing_required'))->toBeTrue()
        ->and($this->registry->has('not_a_real_key'))->toBeFalse();
});

it('uses the label as the requirement when available', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'requirement_text' => 'Object-oriented programming',
        'requirement_label' => 'OOP',
    ]));

    expect($template->prompt)->toBe('Do you have experience with OOP?');
});

it('falls back to the requirement text when no label exists', function () {
    $template = $this->registry->resolve(clarificationFinding([
        'requirement_label' => null,
        'requirement_text' => 'Docker',
    ]));

    expect($template->prompt)->toBe('Do you have experience with Docker?');
});

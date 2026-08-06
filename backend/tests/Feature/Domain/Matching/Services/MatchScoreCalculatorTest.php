<?php

use App\Domain\Matching\Data\MatchAnalysisResult;
use App\Domain\Matching\Data\MatchFindingResult;
use App\Domain\Matching\Data\MatchScoreComponent;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\MatchScoreCalculator;

function matchFinding(
    string $state,
    float $factor,
    string $category = 'required_skills',
    string $importance = 'required',
    array $evidence = [],
    string $text = 'Requirement',
): MatchFindingResult {
    return new MatchFindingResult(
        sourceType: RequirementSourceType::JobRequirement,
        sourceId: 1,
        requirementText: $text,
        requirementLabel: null,
        importance: MatchImportance::from($importance),
        category: $category,
        matchState: MatchState::from($state),
        factor: $factor,
        matchedCandidateSkillId: null,
        evidenceRefs: $evidence,
        justification: null,
        confidence: null,
        classifierSource: null,
        displayOrder: 0,
    );
}

function matchCategoryData(array $overrides = []): array
{
    $defaults = [
        MatchCategory::RequiredSkills->value => true,
        MatchCategory::PreferredSkills->value => true,
        MatchCategory::Evidence->value => true,
        MatchCategory::ExperienceEducation->value => true,
        MatchCategory::LanguageSoft->value => true,
    ];

    return array_replace($defaults, $overrides);
}

function matchComponentsByCategory(MatchScoreCalculator $calculator, array $findings, array $data = []): MatchAnalysisResult
{
    return $calculator->calculate($findings, matchCategoryData($data));
}

beforeEach(function () {
    $this->calculator = new MatchScoreCalculator;
});

it('renormalizes weights over present categories', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('matched', 1.0, 'required_skills'),
        matchFinding('gap', 0.0, 'required_skills', text: 'Docker'),
        matchFinding('matched', 1.0, 'preferred_skills', 'preferred'),
    ]);

    $components = collect($result->scoreComponents)
        ->keyBy(fn (MatchScoreComponent $c): string => $c->category->value);

    expect($components['required_skills']->score)->toBe(50);
    expect($components['required_skills']->weight)->toBe(0.500);
    expect($components['preferred_skills']->score)->toBe(100);
    expect($components['preferred_skills']->weight)->toBe(0.200);
    expect($components)->toHaveCount(2);
    expect($result->overallScore)->toBe((int) round((50 * 0.5 + 100 * 0.2) / 0.7));
});

it('keeps required weight above preferred weight', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('matched', 1.0, 'required_skills'),
        matchFinding('matched', 1.0, 'preferred_skills', 'preferred'),
    ]);

    $components = collect($result->scoreComponents)
        ->keyBy(fn (MatchScoreComponent $c): string => $c->category->value);

    expect($components['required_skills']->weight)->toBeGreaterThan($components['preferred_skills']->weight);
});

it('produces a neutral score and records missing data for an all-unknown category', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('unknown', 0.0, 'evidence', 'required', text: 'Design scalable systems'),
    ], [MatchCategory::Evidence->value => false]);

    $component = collect($result->scoreComponents)
        ->first(fn (MatchScoreComponent $c): bool => $c->category === MatchCategory::Evidence);

    expect($component->score)->toBe((int) config('matching.neutral_score'));
    expect($component->achievedPoints)->toBe(0.0);
    expect($component->totalPoints)->toBe(1.0);
    expect($component->hasCandidateData)->toBeFalse();
    expect($result->overallScore)->toBe((int) config('matching.neutral_score'));
    expect($result->unknownCount)->toBe(1);
    expect($result->gapCount)->toBe(0);
});

it('excludes unknown findings from category scoring while gap findings count', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('matched', 1.0, 'required_skills', evidence: [['type' => 'url', 'id' => null, 'label' => 'Cert']]),
        matchFinding('gap', 0.0, 'required_skills', text: 'Docker'),
        matchFinding('unknown', 0.0, 'required_skills', text: 'Responsibility'),
    ]);

    $component = collect($result->scoreComponents)
        ->first(fn (MatchScoreComponent $c): bool => $c->category === MatchCategory::RequiredSkills);

    expect($component->score)->toBe(50);
    expect($component->achievedPoints)->toBe(1.0);
    expect($component->totalPoints)->toBe(2.0);
    expect($result->overallScore)->toBe(50);
});

it('computes the evidence coverage score from findings with evidence references', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('matched', 1.0, 'required_skills', evidence: [['type' => 'url', 'id' => null, 'label' => 'Cert']]),
        matchFinding('gap', 0.0, 'required_skills', text: 'Docker'),
        matchFinding('matched', 1.0, 'preferred_skills', 'preferred', text: 'Redis'),
    ]);

    expect($result->evidenceCoverageScore)->toBe((int) round(100 / 3));
});

it('counts requirements and results per state', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('matched', 1.0, 'required_skills'),
        matchFinding('gap', 0.0, 'required_skills', text: 'Docker'),
        matchFinding('partial', 0.5, 'preferred_skills', 'preferred', text: 'Redis'),
        matchFinding('unknown', 0.0, 'evidence', text: 'Responsibility'),
    ]);

    expect($result->requiredCount)->toBe(3);
    expect($result->preferredCount)->toBe(1);
    expect($result->matchedCount)->toBe(1);
    expect($result->partialCount)->toBe(1);
    expect($result->gapCount)->toBe(1);
    expect($result->unknownCount)->toBe(1);
});

it('surfaces only required gaps as critical-missing warnings', function () {
    $result = matchComponentsByCategory($this->calculator, [
        matchFinding('gap', 0.0, 'required_skills', text: 'Docker'),
        matchFinding('gap', 0.0, 'preferred_skills', 'preferred', text: 'Redis'),
    ]);

    expect($result->warnings)->toBe(['Docker']);
});

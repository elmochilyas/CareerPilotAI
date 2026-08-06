<?php

use App\Domain\Matching\Data\MatchEvaluationResult;
use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\DeterministicRequirementEvaluator;

function matchRequirement(
    string $category,
    ?string $sourceCategory,
    string $text,
    string $importance = 'required',
    string $sourceType = 'job_opportunity_skill',
    ?string $language = null,
    int $id = 1,
    int $order = 0,
): MatchRequirement {
    return new MatchRequirement(
        sourceType: RequirementSourceType::from($sourceType),
        sourceId: $id,
        text: $text,
        label: $text,
        importance: MatchImportance::from($importance),
        category: $category,
        sourceCategory: $sourceCategory,
        language: $language,
        displayOrder: $order,
    );
}

function matchEvaluate(array $profile, MatchRequirement $requirement): MatchEvaluationResult
{
    $evaluator = new DeterministicRequirementEvaluator;

    return $evaluator->evaluate(
        matchProfileSnapshot(
            skills: $profile['skills'] ?? [],
            items: $profile['items'] ?? [],
            languages: $profile['languages'] ?? [],
        ),
        [$requirement],
    );
}

beforeEach(function () {
    $this->evaluator = new DeterministicRequirementEvaluator;
});

it('maps a verified skill to matched with factor 1.00 and evidence references', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => [['type' => 'url', 'id' => null, 'label' => 'Certification']]]],
    ], matchRequirement('required_skills', 'required', 'PHP'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Matched);
    expect($finding->factor)->toBe(1.0);
    expect($finding->matchedCandidateSkillId)->toBe(7);
    expect($finding->evidenceRefs)->not->toBeEmpty();
});

it('maps a claimed skill to partial with factor 0.50', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'claimed', 'years_experience' => 5.0, 'evidence' => []]],
    ], matchRequirement('required_skills', 'required', 'PHP'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Partial);
    expect($finding->factor)->toBe(0.5);
    expect($finding->evidenceRefs)->toBeEmpty();
});

it('maps a learning skill to partial with factor 0.20', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'learning', 'years_experience' => 0.0, 'evidence' => []]],
    ], matchRequirement('required_skills', 'required', 'PHP'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Partial);
    expect($finding->factor)->toBe(0.2);
});

it('maps a missing skill to gap with factor 0.00', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    ], matchRequirement('required_skills', 'required', 'Docker'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Gap);
    expect($finding->factor)->toBe(0.0);
    expect($finding->matchedCandidateSkillId)->toBeNull();
});

it('maps rejected and archived skills to gap with factor 0.00', function () {
    foreach (['rejected', 'archived'] as $state) {
        $result = matchEvaluate([
            'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => $state, 'years_experience' => 5.0, 'evidence' => []]],
        ], matchRequirement('required_skills', 'required', 'PHP'));

        $finding = $result->findings[0];

        expect($finding->matchState)->toBe(MatchState::Gap);
        expect($finding->factor)->toBe(0.0);
    }
});

it('matches version-suffixed skill labels to the underlying skill', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
    ], matchRequirement('required_skills', 'required', 'PHP 8'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
});

it('maps a fluent language to matched with factor 1.00', function () {
    $result = matchEvaluate([
        'languages' => [['language' => 'English', 'proficiency' => 'fluent']],
    ], matchRequirement('language_soft', 'language', 'English', language: 'English'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
    expect($result->findings[0]->factor)->toBe(1.0);
});

it('maps a missing language to gap when the profile has language data', function () {
    $result = matchEvaluate([
        'languages' => [['language' => 'English', 'proficiency' => 'fluent']],
    ], matchRequirement('language_soft', 'language', 'French', language: 'French'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Gap);
    expect($result->findings[0]->factor)->toBe(0.0);
});

it('marks a language requirement as unknown when the profile has no language data', function () {
    $result = matchEvaluate([], matchRequirement('language_soft', 'language', 'English', language: 'English'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Unknown);
    expect($finding->factor)->toBe(0.0);
});

it('marks an education requirement as unknown when the profile has no education items', function () {
    $result = matchEvaluate([
        'items' => [['id' => 1, 'type' => 'experience', 'title' => 'Backend Developer', 'organization' => 'Acme', 'description' => null]],
    ], matchRequirement('experience_education', 'education', "Master's degree in Computer Science", sourceType: 'job_requirement'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Unknown);
    expect($finding->factor)->toBe(0.0);
});

it('matches an education requirement against education profile items', function () {
    $result = matchEvaluate([
        'items' => [['id' => 2, 'type' => 'education', 'title' => 'Master of Science in Computer Science', 'organization' => 'University', 'description' => null]],
    ], matchRequirement('experience_education', 'education', "Master's degree in Computer Science", sourceType: 'job_requirement'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
    expect($result->findings[0]->factor)->toBe(1.0);
});

it('matches an experience requirement against experience profile items', function () {
    $result = matchEvaluate([
        'items' => [['id' => 3, 'type' => 'experience', 'title' => 'Backend Developer', 'organization' => 'Acme', 'description' => 'Backend API development using Laravel and MySQL']],
    ], matchRequirement('experience_education', 'required_experience', 'Backend API development (3 years)', sourceType: 'job_requirement'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
    expect($result->findings[0]->factor)->toBe(1.0);
});

it('matches a certification requirement against certification items', function () {
    $result = matchEvaluate([
        'items' => [['id' => 4, 'type' => 'certification', 'title' => 'AWS Certified Developer - Associate', 'organization' => 'AWS', 'description' => null]],
    ], matchRequirement('evidence', 'required_certification', 'AWS Certified Developer', sourceType: 'job_requirement'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
});

it('marks a certification requirement as unknown when the profile has no certifications', function () {
    $result = matchEvaluate([
        'items' => [['id' => 1, 'type' => 'experience', 'title' => 'Backend Developer', 'organization' => 'Acme', 'description' => null]],
    ], matchRequirement('evidence', 'required_certification', 'AWS Certified Developer', sourceType: 'job_requirement'));

    expect($result->findings[0]->matchState)->toBe(MatchState::Unknown);
});

it('defers responsibility requirements to the semantic classifier as unknown', function () {
    $result = matchEvaluate([
        'items' => [['id' => 5, 'type' => 'project', 'title' => 'E-commerce platform', 'organization' => null, 'description' => 'Built scalable checkout with Laravel']],
    ], matchRequirement('evidence', 'responsibility', 'Design scalable microservices', sourceType: 'job_requirement'));

    $finding = $result->findings[0];

    expect($finding->matchState)->toBe(MatchState::Unknown);
    expect($finding->factor)->toBe(0.0);
});

it('records candidate-data presence per category', function () {
    $result = matchEvaluate([
        'skills' => [['id' => 7, 'normalized_name' => 'php', 'state' => 'verified', 'years_experience' => 5.0, 'evidence' => []]],
        'languages' => [['language' => 'English', 'proficiency' => 'fluent']],
    ], matchRequirement('required_skills', 'required', 'PHP'));

    expect($result->categoryHasCandidateData[MatchCategory::RequiredSkills->value])->toBeTrue();
    expect($result->categoryHasCandidateData[MatchCategory::Evidence->value])->toBeFalse();
    expect($result->categoryHasCandidateData[MatchCategory::LanguageSoft->value])->toBeTrue();
});

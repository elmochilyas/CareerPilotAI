<?php

use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Data\MatchFindingResult;
use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Matching\Services\RequirementClassifierSchemaValidator;
use App\Domain\Matching\Services\RequirementSemanticClassifier;
use Tests\Support\FakeRequirementClassifier;

function fallbackRequirement(int $order, string $category, ?string $sourceCategory, string $text): MatchRequirement
{
    return new MatchRequirement(
        sourceType: RequirementSourceType::JobRequirement,
        sourceId: $order + 1,
        text: $text,
        label: $text,
        importance: MatchImportance::Required,
        category: $category,
        sourceCategory: $sourceCategory,
        language: null,
        displayOrder: $order,
    );
}

function fallbackFinding(int $order, MatchState $state, string $category, float $factor, array $overrides = []): MatchFindingResult
{
    return new MatchFindingResult(
        sourceType: RequirementSourceType::from($overrides['source_type'] ?? 'job_requirement'),
        sourceId: $order + 1,
        requirementText: $overrides['requirement_text'] ?? 'Requirement '.$order,
        requirementLabel: $overrides['requirement_label'] ?? 'Requirement '.$order,
        importance: MatchImportance::Required,
        category: $category,
        matchState: $state,
        factor: $factor,
        matchedCandidateSkillId: $overrides['matched_candidate_skill_id'] ?? null,
        evidenceRefs: $overrides['evidence_refs'] ?? [],
        justification: $overrides['justification'] ?? 'Deterministic.',
        confidence: $overrides['confidence'] ?? null,
        classifierSource: $overrides['classifier_source'] ?? null,
        displayOrder: $order,
    );
}

function fallbackClassifier(): RequirementSemanticClassifier
{
    return new RequirementSemanticClassifier(
        new FakeRequirementClassifier(FakeRequirementClassifier::MODE_PROVIDER_FAILURE),
        new RequirementClassifierSchemaValidator,
    );
}

function fallbackProfile(): ProfileSnapshot
{
    return matchProfileSnapshot(
        items: [
            ['id' => 10, 'type' => 'experience', 'title' => 'Backend Developer', 'organization' => 'ACME', 'description' => 'Built microservices.'],
        ],
    );
}

it('marks semantic-only requirements as unknown when the classifier is unavailable', function () {
    $classifier = fallbackClassifier();

    $requirements = [
        fallbackRequirement(0, 'evidence', 'responsibility', 'Lead backend microservices development.'),
        fallbackRequirement(1, 'required_skills', 'required', 'PHP'),
    ];

    $deterministicFindings = [
        fallbackFinding(0, MatchState::Gap, 'evidence', 0.0),
        fallbackFinding(
            1,
            MatchState::Matched,
            'required_skills',
            1.0,
            [
                'matched_candidate_skill_id' => 7,
                'evidence_refs' => [['type' => 'url', 'id' => null, 'label' => 'Certification']],
                'classifier_source' => null,
            ],
        ),
    ];

    $result = $classifier->classifyWithFallback(fallbackProfile(), $requirements, $deterministicFindings, 'req_fallback_1');

    expect($result->status)->toBe('unavailable');
    expect($result->provider)->toBeNull();
    expect($result->model)->toBeNull();
    expect($result->promptVersion)->toBeNull();
    expect($result->latencyMs)->toBeNull();
    expect($result->tokensPrompt)->toBeNull();
    expect($result->tokensCompletion)->toBeNull();
    expect($result->responseId)->toBeNull();
    expect($result->warnings)->toBe([]);

    expect($result->findings)->toHaveCount(2);

    $unresolved = $result->findings[0];
    expect($unresolved->matchState)->toBe(MatchState::Unknown);
    expect($unresolved->factor)->toBe(0.0);
    expect($unresolved->matchedCandidateSkillId)->toBeNull();
    expect($unresolved->evidenceRefs)->toBe([]);
    expect($unresolved->classifierSource)->toBeNull();
    expect($unresolved->confidence)->toBeNull();
    expect($unresolved->justification)->toBe(
        'Semantic comparison was unavailable, so this requirement was left unevaluated.',
    );
    expect($unresolved->displayOrder)->toBe(0);

    $resolved = $result->findings[1];
    expect($resolved->matchState)->toBe(MatchState::Matched);
    expect($resolved->factor)->toBe(1.0);
    expect($resolved->matchedCandidateSkillId)->toBe(7);
    expect($resolved->evidenceRefs)->not->toBeEmpty();
    expect($resolved->classifierSource)->toBeNull();
});

it('keeps deterministic findings unchanged when nothing needs classification', function () {
    $classifier = fallbackClassifier();

    $requirements = [
        fallbackRequirement(0, 'required_skills', 'required', 'PHP'),
        fallbackRequirement(1, 'language_soft', 'language', 'English'),
    ];

    $deterministicFindings = [
        fallbackFinding(0, MatchState::Matched, 'required_skills', 1.0, [
            'matched_candidate_skill_id' => 7,
        ]),
        fallbackFinding(1, MatchState::Partial, 'language_soft', 0.5),
    ];

    $result = $classifier->classifyWithFallback(fallbackProfile(), $requirements, $deterministicFindings, 'req_fallback_2');

    expect($result->status)->toBeNull();
    expect($result->findings)->toHaveCount(2);
    expect($result->findings[0]->matchState)->toBe(MatchState::Matched);
    expect($result->findings[1]->matchState)->toBe(MatchState::Partial);
    expect($result->findings[1]->factor)->toBe(0.5);
});

it('never calls the classifier during the fallback', function () {
    $spy = new class extends FakeRequirementClassifier
    {
        public int $calls = 0;

        public function classify(ClassifierRequest $request): ClassifierResult
        {
            $this->calls++;

            return parent::classify($request);
        }
    };

    $classifier = new RequirementSemanticClassifier(
        $spy,
        new RequirementClassifierSchemaValidator,
    );

    $requirements = [
        fallbackRequirement(0, 'evidence', 'responsibility', 'Lead backend microservices development.'),
    ];

    $deterministicFindings = [
        fallbackFinding(0, MatchState::Gap, 'evidence', 0.0),
    ];

    $result = $classifier->classifyWithFallback(fallbackProfile(), $requirements, $deterministicFindings, 'req_fallback_3');

    expect($spy->calls)->toBe(0);
    expect($result->status)->toBe('unavailable');
    expect($result->findings[0]->matchState)->toBe(MatchState::Unknown);
});

it('does not leak the provider problem code or internal message into the fallback result', function () {
    $classifier = fallbackClassifier();

    $requirements = [
        fallbackRequirement(0, 'evidence', 'responsibility', 'Lead backend microservices development.'),
    ];

    $deterministicFindings = [
        fallbackFinding(0, MatchState::Gap, 'evidence', 0.0),
    ];

    $result = $classifier->classifyWithFallback(fallbackProfile(), $requirements, $deterministicFindings, 'req_fallback_4');

    expect($result->status)->toBe('unavailable');
    expect($result->findings[0]->classifierSource)->toBeNull();
    expect($result->findings[0]->justification)->toBe(
        'Semantic comparison was unavailable, so this requirement was left unevaluated.',
    );
    expect($result->findings[0]->justification)->not->toContain('provider');
});

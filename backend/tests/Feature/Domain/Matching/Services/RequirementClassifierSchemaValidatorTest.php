<?php

use App\Domain\Matching\Data\ClassifierFinding;
use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierRequestItem;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Enums\ClassifierComparisonKind;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Exceptions\RequirementClassifierException;
use App\Domain\Matching\Services\RequirementClassifierSchemaValidator;

function schemaValidatorTestRequest(): ClassifierRequest
{
    return new ClassifierRequest(
        items: [
            new ClassifierRequestItem(
                index: 0,
                text: 'Lead backend microservices development.',
                kind: ClassifierComparisonKind::Responsibility,
                category: 'evidence',
                importance: MatchImportance::Required,
                candidateItemIds: [10],
            ),
            new ClassifierRequestItem(
                index: 1,
                text: '3+ years of PHP experience.',
                kind: ClassifierComparisonKind::Experience,
                category: 'experience_education',
                importance: MatchImportance::Required,
                candidateItemIds: [11],
            ),
        ],
        profileItems: [
            ['id' => 10, 'type' => 'experience', 'title' => 'Backend Developer', 'organization' => 'ACME', 'description' => 'Built microservices.'],
            ['id' => 11, 'type' => 'experience', 'title' => 'PHP Developer', 'organization' => 'Beta', 'description' => 'Maintained PHP applications.'],
        ],
    );
}

function schemaValidatorTestResult(array $findings): ClassifierResult
{
    return new ClassifierResult(
        schemaVersion: '1.0.0',
        findings: $findings,
        warnings: [],
        provider: 'openai',
        model: 'gpt-4o-mini',
        promptVersion: '1.0.0',
        latencyMs: 120,
        tokensPrompt: 500,
        tokensCompletion: 200,
        responseId: null,
    );
}

function schemaValidatorFinding(int $index, MatchState $state, array $overrides = []): ClassifierFinding
{
    return new ClassifierFinding(
        index: $index,
        matchState: $state,
        category: $overrides['category'] ?? null,
        confidence: $overrides['confidence'] ?? 0.8,
        justification: $overrides['justification'] ?? 'Judgement.',
        evidenceReferences: $overrides['evidence'] ?? [
            ['type' => 'experience', 'id' => $index === 0 ? 10 : 11, 'label' => 'Role'],
        ],
    );
}

function schemaValidatorValidate(ClassifierResult $result, ?ClassifierRequest $request = null): void
{
    app(RequirementClassifierSchemaValidator::class)->validate($result, $request ?? schemaValidatorTestRequest());
}

function schemaValidatorExpectFailure(Closure $callback, string $needle): void
{
    try {
        $callback();
        throw new Exception('Expected RequirementClassifierException was not thrown.');
    } catch (RequirementClassifierException $exception) {
        expect($exception->problemCode)->toBe('ai_classifier_schema_invalid')
            ->and(implode(' | ', $exception->errors))->toContain($needle);
    }
}

it('accepts valid findings covering every requested requirement', function () {
    expect(fn () => schemaValidatorValidate(schemaValidatorTestResult([
        schemaValidatorFinding(0, MatchState::Matched),
        schemaValidatorFinding(1, MatchState::Partial),
    ])))->not->toThrow(RequirementClassifierException::class);
});

it('accepts gap and unknown findings without evidence', function () {
    expect(fn () => schemaValidatorValidate(schemaValidatorTestResult([
        schemaValidatorFinding(0, MatchState::Gap, ['evidence' => []]),
        schemaValidatorFinding(1, MatchState::Unknown, ['evidence' => [], 'confidence' => null]),
    ])))->not->toThrow(RequirementClassifierException::class);
});

it('rejects an unexpected schema version', function () {
    $result = schemaValidatorTestResult([
        schemaValidatorFinding(0, MatchState::Matched),
        schemaValidatorFinding(1, MatchState::Matched),
    ]);

    schemaValidatorExpectFailure(fn () => schemaValidatorValidate(new ClassifierResult(
        schemaVersion: '9.9.9',
        findings: $result->findings,
        warnings: [],
        provider: 'openai',
        model: 'gpt-4o-mini',
        promptVersion: '1.0.0',
        latencyMs: null,
        tokensPrompt: null,
        tokensCompletion: null,
        responseId: null,
    )), 'schema_version');
});

it('rejects a finding count that does not match the request', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched),
        ])),
        'findings count',
    );
});

it('rejects a duplicate finding for the same index', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched),
            schemaValidatorFinding(0, MatchState::Matched),
        ])),
        'duplicate finding',
    );
});

it('rejects a finding for an undeclared index', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched),
            schemaValidatorFinding(2, MatchState::Matched),
        ])),
        'not a requested requirement',
    );
});

it('rejects a matched finding without any evidence reference', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['evidence' => []]),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'must reference at least one profile item',
    );
});

it('rejects an evidence reference to an unknown profile item', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['evidence' => [
                ['type' => 'experience', 'id' => 999, 'label' => 'Ghost'],
            ]]),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'unknown profile item',
    );
});

it('rejects an invalid evidence type', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['evidence' => [
                ['type' => 'invented_item', 'id' => 10, 'label' => 'Role'],
            ]]),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'invalid evidence type',
    );
});

it('rejects a confidence value out of range', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['confidence' => 1.5]),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'confidence out of range',
    );
});

it('rejects an over-length justification', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['justification' => str_repeat('x', 2001)]),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'justification exceeds maximum length',
    );
});

it('rejects a category on a non-unstructured requirement', function () {
    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['category' => 'evidence']),
            schemaValidatorFinding(1, MatchState::Matched),
        ])),
        'category only allowed for unstructured',
    );
});

it('accepts an unstructured requirement classified into a known category', function () {
    $request = new ClassifierRequest(
        items: [
            new ClassifierRequestItem(
                index: 0,
                text: 'Fluent English communication.',
                kind: ClassifierComparisonKind::Unstructured,
                category: null,
                importance: MatchImportance::Required,
                candidateItemIds: [],
            ),
        ],
        profileItems: [],
    );

    expect(fn () => schemaValidatorValidate(schemaValidatorTestResult([
        schemaValidatorFinding(0, MatchState::Matched, [
            'category' => 'language_soft',
            'evidence' => [['type' => 'profile_item', 'id' => 10, 'label' => 'Language']],
        ]),
    ]), new ClassifierRequest(
        items: $request->items,
        profileItems: [['id' => 10, 'type' => 'profile_item', 'title' => 'English', 'organization' => null, 'description' => null]],
    )))->not->toThrow(RequirementClassifierException::class);
});

it('rejects an unstructured requirement with an invalid classified category', function () {
    $request = new ClassifierRequest(
        items: [
            new ClassifierRequestItem(
                index: 0,
                text: 'Mysterious requirement.',
                kind: ClassifierComparisonKind::Unstructured,
                category: null,
                importance: MatchImportance::Required,
                candidateItemIds: [],
            ),
        ],
        profileItems: [],
    );

    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['category' => 'mystery_bucket']),
        ]), $request),
        'invalid classified category',
    );
});

it('rejects an unstructured requirement left unclassified without the unknown state', function () {
    $request = new ClassifierRequest(
        items: [
            new ClassifierRequestItem(
                index: 0,
                text: 'Mysterious requirement.',
                kind: ClassifierComparisonKind::Unstructured,
                category: null,
                importance: MatchImportance::Required,
                candidateItemIds: [],
            ),
        ],
        profileItems: [],
    );

    schemaValidatorExpectFailure(
        fn () => schemaValidatorValidate(schemaValidatorTestResult([
            schemaValidatorFinding(0, MatchState::Matched, ['category' => null, 'evidence' => []]),
        ]), $request),
        'must be marked unknown',
    );
});

it('accepts an unstructured requirement marked unknown without a category', function () {
    $request = new ClassifierRequest(
        items: [
            new ClassifierRequestItem(
                index: 0,
                text: 'Mysterious requirement.',
                kind: ClassifierComparisonKind::Unstructured,
                category: null,
                importance: MatchImportance::Required,
                candidateItemIds: [],
            ),
        ],
        profileItems: [],
    );

    expect(fn () => schemaValidatorValidate(schemaValidatorTestResult([
        schemaValidatorFinding(0, MatchState::Unknown, ['category' => null, 'evidence' => []]),
    ]), $request))->not->toThrow(RequirementClassifierException::class);
});

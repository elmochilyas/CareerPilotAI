<?php

namespace Tests\Support;

use App\Domain\Matching\Data\ClassifierFinding;
use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Enums\ClassifierComparisonKind;
use App\Domain\Matching\Enums\MatchState;
use App\Domain\Matching\Exceptions\RequirementClassifierException;
use App\Domain\Matching\Services\Contracts\RequirementClassifier;

class FakeRequirementClassifier implements RequirementClassifier
{
    public const MODE_SUCCESS = 'success';

    public const MODE_MALFORMED = 'malformed';

    public const MODE_INVALID_SCHEMA = 'invalid_schema';

    public const MODE_PROVIDER_FAILURE = 'provider_failure';

    public function __construct(
        public string $mode = self::MODE_SUCCESS,
    ) {}

    public function classify(ClassifierRequest $request): ClassifierResult
    {
        return match ($this->mode) {
            self::MODE_MALFORMED => throw new RequirementClassifierException(
                'Classifier provider returned an invalid response.',
                'ai_classifier_malformed_response',
            ),
            self::MODE_INVALID_SCHEMA => throw new RequirementClassifierException(
                'Classifier output did not pass schema and business validation.',
                'ai_classifier_schema_invalid',
                ['findings.0.index'],
            ),
            self::MODE_PROVIDER_FAILURE => throw new RequirementClassifierException(
                'Classifier provider is temporarily unavailable.',
                'ai_classifier_unavailable',
            ),
            default => new ClassifierResult(
                schemaVersion: '1.0.0',
                findings: $this->buildFindings($request),
                warnings: [],
                provider: 'fake',
                model: 'fake-classifier-v1',
                promptVersion: '1.0.0',
                latencyMs: 100,
                tokensPrompt: 300,
                tokensCompletion: 120,
                responseId: 'fake_'.uniqid(),
            ),
        };
    }

    /**
     * @return list<ClassifierFinding>
     */
    private function buildFindings(ClassifierRequest $request): array
    {
        $findings = [];

        foreach ($request->items as $item) {
            $hasEvidence = $item->candidateItemIds !== [];

            $findings[] = new ClassifierFinding(
                index: $item->index,
                matchState: $hasEvidence ? MatchState::Matched : MatchState::Unknown,
                category: $item->kind === ClassifierComparisonKind::Unstructured ? 'evidence' : null,
                confidence: $hasEvidence ? 0.9 : null,
                justification: 'Fake classifier judgement.',
                evidenceReferences: $hasEvidence
                    ? [['type' => 'experience', 'id' => $item->candidateItemIds[0], 'label' => 'Profile item']]
                    : [],
            );
        }

        return $findings;
    }
}

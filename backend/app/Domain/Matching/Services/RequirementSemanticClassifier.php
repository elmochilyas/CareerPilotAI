<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\ClassifierFinding;
use App\Domain\Matching\Data\ClassifierRequest;
use App\Domain\Matching\Data\ClassifierRequestItem;
use App\Domain\Matching\Data\ClassifierResult;
use App\Domain\Matching\Data\MatchFindingResult;
use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Data\SemanticClassificationResult;
use App\Domain\Matching\Enums\ClassifierComparisonKind;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchState;

final class RequirementSemanticClassifier
{
    public function __construct(
        private readonly Contracts\RequirementClassifier $classifier,
        private readonly RequirementClassifierSchemaValidator $validator,
    ) {}

    /**
     * Runs bounded semantic comparisons only for requirements the deterministic
     * pass could not decide and where the profile holds relevant candidate items.
     * When nothing needs classification the deterministic findings are returned
     * unchanged and no provider call happens.
     *
     * @param  list<MatchRequirement>  $requirements
     * @param  list<MatchFindingResult>  $deterministicFindings
     */
    public function classify(
        ProfileSnapshot $profile,
        array $requirements,
        array $deterministicFindings,
        ?string $requestId,
    ): SemanticClassificationResult {
        $request = $this->buildRequest($profile, $requirements, $deterministicFindings, $requestId);

        if ($request->items === []) {
            return $this->withoutClassification($deterministicFindings);
        }

        $result = $this->classifier->classify($request);
        $this->validator->validate($result, $request);

        return new SemanticClassificationResult(
            findings: $this->merge($deterministicFindings, $result, $request),
            provider: $result->provider,
            model: $result->model,
            promptVersion: $result->promptVersion,
            latencyMs: $result->latencyMs,
            tokensPrompt: $result->tokensPrompt,
            tokensCompletion: $result->tokensCompletion,
            responseId: $result->responseId,
            status: 'success',
            warnings: $result->warnings,
        );
    }

    /**
     * Completes the analysis with deterministic-only results when the semantic
     * classifier failed. Unresolved semantic-only requirements are marked as
     * `unknown` so the analysis still completes from the trustworthy results
     * instead of failing the whole analysis. No provider call is made and
     * provider internals are never leaked into findings.
     *
     * @param  list<MatchRequirement>  $requirements
     * @param  list<MatchFindingResult>  $deterministicFindings
     */
    public function classifyWithFallback(
        ProfileSnapshot $profile,
        array $requirements,
        array $deterministicFindings,
        ?string $requestId,
    ): SemanticClassificationResult {
        $request = $this->buildRequest($profile, $requirements, $deterministicFindings, $requestId);

        if ($request->items === []) {
            return $this->withoutClassification($deterministicFindings);
        }

        $indexes = [];
        foreach ($request->items as $item) {
            $indexes[$item->index] = true;
        }

        $findings = [];

        foreach ($deterministicFindings as $finding) {
            if (! isset($indexes[$finding->displayOrder])) {
                $findings[] = $finding;

                continue;
            }

            $findings[] = new MatchFindingResult(
                sourceType: $finding->sourceType,
                sourceId: $finding->sourceId,
                requirementText: $finding->requirementText,
                requirementLabel: $finding->requirementLabel,
                importance: $finding->importance,
                category: $finding->category,
                matchState: MatchState::Unknown,
                factor: 0.0,
                matchedCandidateSkillId: null,
                evidenceRefs: [],
                justification: 'Semantic comparison was unavailable, so this requirement was left unevaluated.',
                confidence: null,
                classifierSource: null,
                displayOrder: $finding->displayOrder,
            );
        }

        return new SemanticClassificationResult(
            findings: $findings,
            provider: null,
            model: null,
            promptVersion: null,
            latencyMs: null,
            tokensPrompt: null,
            tokensCompletion: null,
            responseId: null,
            status: 'unavailable',
            warnings: [],
        );
    }

    /**
     * @param  list<MatchFindingResult>  $deterministicFindings
     */
    private function withoutClassification(array $deterministicFindings): SemanticClassificationResult
    {
        return new SemanticClassificationResult(
            findings: $deterministicFindings,
            provider: null,
            model: null,
            promptVersion: null,
            latencyMs: null,
            tokensPrompt: null,
            tokensCompletion: null,
            responseId: null,
            status: null,
            warnings: [],
        );
    }

    /**
     * @param  list<MatchRequirement>  $requirements
     * @param  list<MatchFindingResult>  $deterministicFindings
     */
    private function buildRequest(
        ProfileSnapshot $profile,
        array $requirements,
        array $deterministicFindings,
        ?string $requestId,
    ): ClassifierRequest {
        $stateByOrder = [];
        foreach ($deterministicFindings as $finding) {
            $stateByOrder[$finding->displayOrder] = $finding->matchState;
        }

        $items = [];
        $referencedItemIds = [];

        foreach ($requirements as $requirement) {
            $config = $this->comparisonFor($requirement, $stateByOrder[$requirement->displayOrder] ?? null, $profile);

            if ($config === null) {
                continue;
            }

            [$kind, $category, $candidateItemIds] = $config;

            if ($candidateItemIds === [] && $kind !== ClassifierComparisonKind::Unstructured) {
                continue;
            }

            $items[] = new ClassifierRequestItem(
                index: $requirement->displayOrder,
                text: $requirement->text,
                kind: $kind,
                category: $category,
                importance: $requirement->importance,
                candidateItemIds: $candidateItemIds,
            );

            foreach ($candidateItemIds as $id) {
                $referencedItemIds[$id] = true;
            }
        }

        $profileItems = array_values(array_filter(
            $profile->items,
            static fn (array $item): bool => isset($referencedItemIds[$item['id']]),
        ));

        return new ClassifierRequest(items: $items, profileItems: $profileItems, requestId: $requestId);
    }

    /**
     * @return array{0: ClassifierComparisonKind, 1: string|null, 2: list<int>}|null
     */
    private function comparisonFor(
        MatchRequirement $requirement,
        ?MatchState $currentState,
        ProfileSnapshot $profile,
    ): ?array {
        if (in_array($currentState, [MatchState::Matched, MatchState::Partial], true)) {
            return null;
        }

        if ($requirement->category === MatchCategory::Evidence->value && $requirement->sourceCategory === 'responsibility') {
            return [
                ClassifierComparisonKind::Responsibility,
                $requirement->category,
                $this->profileItemIds($profile, ['experience', 'project']),
            ];
        }

        if ($requirement->category === MatchCategory::ExperienceEducation->value && $requirement->sourceCategory === 'experience') {
            return [
                ClassifierComparisonKind::Experience,
                $requirement->category,
                $this->profileItemIds($profile, ['experience', 'project']),
            ];
        }

        if ($requirement->category === MatchCategory::ExperienceEducation->value && $requirement->sourceCategory === 'education') {
            return [
                ClassifierComparisonKind::Education,
                $requirement->category,
                $this->profileItemIds($profile, ['education']),
            ];
        }

        if ($requirement->category === null) {
            return [
                ClassifierComparisonKind::Unstructured,
                null,
                [],
            ];
        }

        return null;
    }

    /**
     * @param  list<MatchFindingResult>  $deterministicFindings
     * @return list<MatchFindingResult>
     */
    private function merge(
        array $deterministicFindings,
        ClassifierResult $result,
        ClassifierRequest $request,
    ): array {
        $findingByIndex = [];
        foreach ($result->findings as $finding) {
            $findingByIndex[$finding->index] = $finding;
        }

        $itemByIndex = [];
        foreach ($request->items as $item) {
            $itemByIndex[$item->index] = $item;
        }

        $source = $result->provider.':'.$result->model;

        $merged = [];

        foreach ($deterministicFindings as $finding) {
            $classifierFinding = $findingByIndex[$finding->displayOrder] ?? null;
            $item = $itemByIndex[$finding->displayOrder] ?? null;

            if ($classifierFinding === null || $item === null) {
                $merged[] = $finding;

                continue;
            }

            $merged[] = $this->mergedFinding($finding, $classifierFinding, $item, $source);
        }

        return $merged;
    }

    private function mergedFinding(
        MatchFindingResult $finding,
        ClassifierFinding $classifierFinding,
        ClassifierRequestItem $item,
        string $source,
    ): MatchFindingResult {
        $category = $item->kind === ClassifierComparisonKind::Unstructured
            ? $classifierFinding->category
            : $finding->category;

        return new MatchFindingResult(
            sourceType: $finding->sourceType,
            sourceId: $finding->sourceId,
            requirementText: $finding->requirementText,
            requirementLabel: $finding->requirementLabel,
            importance: $finding->importance,
            category: $category,
            matchState: $classifierFinding->matchState,
            factor: $this->factorForState($classifierFinding->matchState),
            matchedCandidateSkillId: null,
            evidenceRefs: $classifierFinding->evidenceReferences,
            justification: $classifierFinding->justification,
            confidence: $classifierFinding->confidence !== null ? (string) $classifierFinding->confidence : null,
            classifierSource: $source,
            displayOrder: $finding->displayOrder,
        );
    }

    private function factorForState(MatchState $state): float
    {
        return match ($state) {
            MatchState::Matched => 1.0,
            MatchState::Partial => 0.5,
            default => 0.0,
        };
    }

    /**
     * @param  list<string>  $types
     * @return list<int>
     */
    private function profileItemIds(ProfileSnapshot $profile, array $types): array
    {
        $ids = [];

        foreach ($profile->items as $item) {
            if (in_array($item['type'], $types, true)) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }
}

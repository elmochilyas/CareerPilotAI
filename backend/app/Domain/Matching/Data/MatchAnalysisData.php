<?php

namespace App\Domain\Matching\Data;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\MatchState;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\MatchScore;
use Carbon\CarbonImmutable;

final readonly class MatchAnalysisData
{
    /**
     * @param  list<MatchScoreComponent>  $scoreComponents
     * @param  list<MatchFindingResult>  $findings
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $id,
        public int $candidateProfileId,
        public int $jobOpportunityId,
        public MatchAnalysisStatus $status,
        public ?int $overallScore,
        public ?int $evidenceCoverageScore,
        public int $requiredCount,
        public int $preferredCount,
        public int $matchedCount,
        public int $partialCount,
        public int $gapCount,
        public int $unknownCount,
        public string $profileFingerprint,
        public string $opportunityFingerprint,
        public ?CarbonImmutable $profileUpdatedAt,
        public ?CarbonImmutable $opportunityUpdatedAt,
        public string $algorithmVersion,
        public string $scoringVersion,
        public string $classifierSchemaVersion,
        public ?string $failureCode,
        public ?string $failureReason,
        public ?string $requestId,
        public ?CarbonImmutable $queuedAt,
        public ?CarbonImmutable $processingStartedAt,
        public ?CarbonImmutable $completedAt,
        public ?CarbonImmutable $failedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public bool $stale,
        public ?string $classifierProvider,
        public ?string $classifierModel,
        public ?string $classifierPromptVersion,
        public ?int $classifierLatencyMs,
        public ?int $classifierTokensPrompt,
        public ?int $classifierTokensCompletion,
        public ?string $classifierResponseId,
        public ?string $classifierStatus,
        public bool $latest = false,
        public array $scoreComponents = [],
        public array $findings = [],
        public array $warnings = [],
    ) {}

    public static function fromModel(MatchAnalysis $analysis, bool $stale, bool $latest = false): self
    {
        $analysis->loadMissing(['scores', 'findings']);

        return new self(
            id: $analysis->id,
            candidateProfileId: $analysis->candidate_profile_id,
            jobOpportunityId: $analysis->job_opportunity_id,
            status: $analysis->status,
            overallScore: $analysis->overall_score,
            evidenceCoverageScore: $analysis->evidence_coverage_score,
            requiredCount: $analysis->required_count ?? 0,
            preferredCount: $analysis->preferred_count ?? 0,
            matchedCount: $analysis->matched_count ?? 0,
            partialCount: $analysis->partial_count ?? 0,
            gapCount: $analysis->gap_count ?? 0,
            unknownCount: $analysis->unknown_count ?? 0,
            profileFingerprint: $analysis->profile_fingerprint,
            opportunityFingerprint: $analysis->opportunity_fingerprint,
            profileUpdatedAt: $analysis->profile_updated_at?->toImmutable(),
            opportunityUpdatedAt: $analysis->opportunity_updated_at?->toImmutable(),
            algorithmVersion: $analysis->algorithm_version,
            scoringVersion: $analysis->scoring_version,
            classifierSchemaVersion: $analysis->classifier_schema_version,
            failureCode: $analysis->failure_code,
            failureReason: $analysis->failure_reason,
            requestId: $analysis->request_id,
            queuedAt: $analysis->queued_at?->toImmutable(),
            processingStartedAt: $analysis->processing_started_at?->toImmutable(),
            completedAt: $analysis->completed_at?->toImmutable(),
            failedAt: $analysis->failed_at?->toImmutable(),
            createdAt: $analysis->created_at->toImmutable(),
            updatedAt: $analysis->updated_at->toImmutable(),
            stale: $stale,
            classifierProvider: $analysis->classifier_provider,
            classifierModel: $analysis->classifier_model,
            classifierPromptVersion: $analysis->classifier_prompt_version,
            classifierLatencyMs: $analysis->classifier_latency_ms,
            classifierTokensPrompt: $analysis->classifier_tokens_prompt,
            classifierTokensCompletion: $analysis->classifier_tokens_completion,
            classifierResponseId: $analysis->classifier_response_id,
            classifierStatus: $analysis->classifier_status,
            latest: $latest,
            scoreComponents: self::mapScores($analysis),
            findings: self::mapFindings($analysis),
            warnings: self::criticalMissingWarnings($analysis),
        );
    }

    /**
     * @return list<MatchScoreComponent>
     */
    private static function mapScores(MatchAnalysis $analysis): array
    {
        return $analysis->scores
            ->sortBy(fn (MatchScore $score): string => $score->category->value)
            ->map(fn (MatchScore $score): MatchScoreComponent => new MatchScoreComponent(
                category: $score->category,
                weight: (float) $score->weight,
                score: $score->score,
                achievedPoints: (float) $score->achieved_points,
                totalPoints: (float) $score->total_points,
                hasCandidateData: $score->has_candidate_data,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<MatchFindingResult>
     */
    private static function mapFindings(MatchAnalysis $analysis): array
    {
        return $analysis->findings
            ->sortBy(fn (MatchFinding $finding): int => $finding->display_order)
            ->map(fn (MatchFinding $finding): MatchFindingResult => new MatchFindingResult(
                sourceType: $finding->source_type,
                sourceId: $finding->source_id,
                requirementText: $finding->requirement_text,
                requirementLabel: $finding->requirement_label,
                importance: $finding->importance,
                category: $finding->category,
                matchState: $finding->match_state,
                factor: (float) $finding->factor,
                matchedCandidateSkillId: $finding->matched_candidate_skill_id,
                evidenceRefs: $finding->evidence_refs ?? [],
                justification: $finding->justification,
                confidence: $finding->confidence,
                classifierSource: $finding->classifier_source,
                displayOrder: $finding->display_order,
                tailoringRelevance: $finding->tailoring_relevance,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function criticalMissingWarnings(MatchAnalysis $analysis): array
    {
        return $analysis->findings
            ->filter(fn (MatchFinding $finding): bool => $finding->importance === MatchImportance::Required
                && $finding->match_state === MatchState::Gap)
            ->map(fn (MatchFinding $finding): string => $finding->requirement_text)
            ->values()
            ->all();
    }
}

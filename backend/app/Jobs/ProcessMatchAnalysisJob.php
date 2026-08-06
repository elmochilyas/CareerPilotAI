<?php

namespace App\Jobs;

use App\Domain\Matching\Actions\MarkMatchAnalysisFailedAction;
use App\Domain\Matching\Data\MatchAnalysisResult;
use App\Domain\Matching\Data\SemanticClassificationResult;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Exceptions\RequirementClassifierException;
use App\Domain\Matching\Services\DeterministicRequirementEvaluator;
use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\MatchScoreCalculator;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Matching\Services\RequirementCollector;
use App\Domain\Matching\Services\RequirementSemanticClassifier;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\MatchScore;
use App\Support\RequestIdContext;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMatchAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $analysisId,
    ) {
        $this->onQueue(Config::get('matching.queue', 'matching'));
    }

    public function handle(
        RequirementCollector $requirements,
        DeterministicRequirementEvaluator $evaluator,
        RequirementSemanticClassifier $semanticClassifier,
        MatchScoreCalculator $calculator,
        FingerprintService $fingerprints,
    ): void {
        $analysis = MatchAnalysis::find($this->analysisId);

        if ($analysis === null) {
            return;
        }

        if (! $analysis->status->isProcessing()) {
            return;
        }

        $requestId = $analysis->request_id;

        if ($requestId !== null) {
            RequestIdContext::set($requestId);
        }

        try {
            $analysis->update([
                'status' => MatchAnalysisStatus::Processing,
                'processing_started_at' => now(),
            ]);

            $profile = $analysis->candidateProfile()->with('candidateSkills.skill', 'items')->firstOrFail();
            $opportunity = $analysis->jobOpportunity()->with('requirements', 'skills.skill')->firstOrFail();

            $profileSnapshot = ProfileSnapshot::fromCandidateProfile($profile);
            $opportunitySnapshot = OpportunitySnapshot::fromJobOpportunity($opportunity);

            $collected = $requirements->collect($opportunity);
            $deterministic = $evaluator->evaluate($profileSnapshot, $collected);

            try {
                $semantic = $semanticClassifier->classify(
                    profile: $profileSnapshot,
                    requirements: $collected,
                    deterministicFindings: $deterministic->findings,
                    requestId: $requestId,
                );
            } catch (RequirementClassifierException $exception) {
                Log::warning('Match analysis completed with deterministic-only results after a classifier failure.', [
                    'request_id' => $requestId,
                    'problem_code' => $exception->problemCode,
                ]);

                $semantic = $semanticClassifier->classifyWithFallback(
                    profile: $profileSnapshot,
                    requirements: $collected,
                    deterministicFindings: $deterministic->findings,
                    requestId: $requestId,
                );
            }

            $result = $calculator->calculate(
                findings: $semantic->findings,
                categoryHasCandidateData: $deterministic->categoryHasCandidateData,
            );

            $this->persist(
                analysis: $analysis,
                profileSnapshot: $profileSnapshot,
                opportunitySnapshot: $opportunitySnapshot,
                profileUpdatedAt: $profile->updated_at,
                opportunityUpdatedAt: $opportunity->updated_at,
                semantic: $semantic,
                result: $result,
                fingerprints: $fingerprints,
            );
        } finally {
            if ($requestId !== null) {
                RequestIdContext::reset();
            }
        }
    }

    public function failed(Throwable $e): void
    {
        app(MarkMatchAnalysisFailedAction::class)->execute(analysisId: $this->analysisId, previous: $e);
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->analysisId)];
    }

    public function backoff(): array
    {
        return Config::array('matching.retry_backoff', [5, 15, 30]);
    }

    private function persist(
        MatchAnalysis $analysis,
        ProfileSnapshot $profileSnapshot,
        OpportunitySnapshot $opportunitySnapshot,
        Carbon $profileUpdatedAt,
        Carbon $opportunityUpdatedAt,
        SemanticClassificationResult $semantic,
        MatchAnalysisResult $result,
        FingerprintService $fingerprints,
    ): void {
        DB::transaction(function () use (
            $analysis,
            $profileSnapshot,
            $opportunitySnapshot,
            $profileUpdatedAt,
            $opportunityUpdatedAt,
            $semantic,
            $result,
            $fingerprints,
        ): void {
            foreach ($result->scoreComponents as $component) {
                MatchScore::create([
                    'match_analysis_id' => $analysis->id,
                    'category' => $component->category,
                    'weight' => $component->weight,
                    'score' => $component->score,
                    'achieved_points' => $component->achievedPoints,
                    'total_points' => $component->totalPoints,
                    'has_candidate_data' => $component->hasCandidateData,
                ]);
            }

            foreach ($result->findings as $finding) {
                MatchFinding::create([
                    'match_analysis_id' => $analysis->id,
                    'source_type' => $finding->sourceType,
                    'source_id' => $finding->sourceId,
                    'requirement_text' => $finding->requirementText,
                    'requirement_label' => $finding->requirementLabel,
                    'importance' => $finding->importance,
                    'category' => $finding->category,
                    'match_state' => $finding->matchState,
                    'factor' => $finding->factor,
                    'matched_candidate_skill_id' => $finding->matchedCandidateSkillId,
                    'evidence_refs' => $finding->evidenceRefs,
                    'justification' => $finding->justification,
                    'confidence' => $finding->confidence,
                    'classifier_source' => $finding->classifierSource,
                    'display_order' => $finding->displayOrder,
                ]);
            }

            $analysis->update([
                'status' => MatchAnalysisStatus::Completed,
                'overall_score' => $result->overallScore,
                'evidence_coverage_score' => $result->evidenceCoverageScore,
                'required_count' => $result->requiredCount,
                'preferred_count' => $result->preferredCount,
                'matched_count' => $result->matchedCount,
                'partial_count' => $result->partialCount,
                'gap_count' => $result->gapCount,
                'unknown_count' => $result->unknownCount,
                'profile_fingerprint' => $fingerprints->profile($profileSnapshot),
                'opportunity_fingerprint' => $fingerprints->opportunity($opportunitySnapshot),
                'profile_updated_at' => $profileUpdatedAt,
                'opportunity_updated_at' => $opportunityUpdatedAt,
                'algorithm_version' => config('matching.algorithm_version'),
                'scoring_version' => config('matching.scoring_version'),
                'classifier_schema_version' => config('matching.classifier_schema_version'),
                'classifier_provider' => $semantic->provider,
                'classifier_model' => $semantic->model,
                'classifier_prompt_version' => $semantic->promptVersion,
                'classifier_latency_ms' => $semantic->latencyMs,
                'classifier_tokens_prompt' => $semantic->tokensPrompt,
                'classifier_tokens_completion' => $semantic->tokensCompletion,
                'classifier_response_id' => $semantic->responseId,
                'classifier_status' => $semantic->status,
                'completed_at' => now(),
            ]);
        });
    }
}

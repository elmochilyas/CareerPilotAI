<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Jobs\ProcessMatchAnalysisJob;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Support\RequestIdContext;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateMatchAnalysisAction
{
    public function __construct(
        private readonly FingerprintService $fingerprints,
    ) {}

    /**
     * Creates a queued match analysis for a confirmed opportunity. The operation
     * is idempotent on the stable operation key derived from the owner, the
     * opportunity, and the client-supplied idempotency key.
     */
    public function execute(CandidateProfile $profile, JobOpportunity $opportunity, string $idempotencyKey): MatchAnalysis
    {
        $this->assertConfirmed($opportunity);
        $this->assertSufficientProfile($profile);

        $operationKey = hash('sha256', $profile->user_id.'|'.$opportunity->id.'|'.$idempotencyKey);

        $existing = $this->findByOperationKey($profile->id, $operationKey);

        if ($existing !== null) {
            return $existing;
        }

        $profileSnapshot = ProfileSnapshot::fromCandidateProfile($profile);
        $opportunitySnapshot = OpportunitySnapshot::fromJobOpportunity($opportunity);

        try {
            $analysis = MatchAnalysis::create([
                'candidate_profile_id' => $profile->id,
                'job_opportunity_id' => $opportunity->id,
                'status' => MatchAnalysisStatus::Queued,
                'operation_key' => $operationKey,
                'profile_fingerprint' => $this->fingerprints->profile($profileSnapshot),
                'opportunity_fingerprint' => $this->fingerprints->opportunity($opportunitySnapshot),
                'profile_updated_at' => $profile->updated_at,
                'opportunity_updated_at' => $opportunity->updated_at,
                'algorithm_version' => config('matching.algorithm_version'),
                'scoring_version' => config('matching.scoring_version'),
                'classifier_schema_version' => config('matching.classifier_schema_version'),
                'request_id' => RequestIdContext::get(),
                'queued_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByOperationKey($profile->id, $operationKey);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }

        ProcessMatchAnalysisJob::dispatch($analysis->id);

        return $analysis;
    }

    private function findByOperationKey(int $candidateProfileId, string $operationKey): ?MatchAnalysis
    {
        return MatchAnalysis::query()
            ->where('candidate_profile_id', $candidateProfileId)
            ->where('operation_key', $operationKey)
            ->first();
    }

    private function assertConfirmed(JobOpportunity $opportunity): void
    {
        $ingestion = $opportunity->ingestion()->first();

        if ($ingestion === null || $ingestion->status !== JobIngestionStatus::Confirmed) {
            throw new ConflictException(
                'Opportunity must be confirmed before it can be matched.',
                'opportunity_not_confirmed',
            );
        }
    }

    private function assertSufficientProfile(CandidateProfile $profile): void
    {
        $minCompletion = (int) config('matching.insufficient_profile.min_profile_completion', 50);
        $minTrustedSkills = (int) config('matching.insufficient_profile.min_trusted_skills', 1);

        if ((int) $profile->profile_completion < $minCompletion) {
            throw new UnprocessableEntityException(
                'Your profile is not complete enough to generate a reliable match.',
                'insufficient_profile',
            );
        }

        $trustedSkills = $profile->candidateSkills()
            ->whereIn('state', ['verified', 'claimed', 'learning'])
            ->count();

        if ($trustedSkills < $minTrustedSkills) {
            throw new UnprocessableEntityException(
                'Add at least one trusted skill before generating a match.',
                'insufficient_profile',
            );
        }
    }
}

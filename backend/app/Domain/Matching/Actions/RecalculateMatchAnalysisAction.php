<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;

class RecalculateMatchAnalysisAction
{
    public function __construct(
        private readonly CreateMatchAnalysisAction $create,
    ) {}

    public function execute(CandidateProfile $profile, JobOpportunity $opportunity, MatchAnalysis $analysis): MatchAnalysis
    {
        $this->assertNoActiveAnalysis($profile, $opportunity);

        return $this->create->execute($profile, $opportunity, 'recalculate:'.$analysis->id);
    }

    private function assertNoActiveAnalysis(CandidateProfile $profile, JobOpportunity $opportunity): void
    {
        $active = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->whereIn('status', [MatchAnalysisStatus::Queued, MatchAnalysisStatus::Processing])
            ->orderByDesc('id')
            ->first();

        if ($active !== null) {
            throw new ConflictException(
                'A match analysis is already running for this opportunity.',
                'active_match_analysis',
                ['active_match_analysis_id' => $active->id],
            );
        }
    }
}

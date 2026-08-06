<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Data\MatchAnalysisData;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Services\StalenessService;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;

class ShowMatchAnalysisAction
{
    public function __construct(
        private readonly StalenessService $staleness,
    ) {}

    public function execute(MatchAnalysis $analysis, CandidateProfile $profile, JobOpportunity $opportunity): MatchAnalysisData
    {
        return MatchAnalysisData::fromModel(
            analysis: $analysis,
            stale: $this->staleness->isStale($analysis, $profile, $opportunity),
            latest: $this->isLatestCompleted($analysis),
        );
    }

    private function isLatestCompleted(MatchAnalysis $analysis): bool
    {
        if ($analysis->status !== MatchAnalysisStatus::Completed) {
            return false;
        }

        $latestId = MatchAnalysis::query()
            ->where('candidate_profile_id', $analysis->candidate_profile_id)
            ->where('job_opportunity_id', $analysis->job_opportunity_id)
            ->where('status', MatchAnalysisStatus::Completed)
            ->max('id');

        return $latestId !== null && $analysis->id === (int) $latestId;
    }
}

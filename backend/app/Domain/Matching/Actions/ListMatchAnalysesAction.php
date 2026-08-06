<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\Data\MatchAnalysisData;
use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Domain\Matching\Services\StalenessService;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use Illuminate\Pagination\CursorPaginator;

class ListMatchAnalysesAction
{
    public function __construct(
        private readonly StalenessService $staleness,
    ) {}

    /**
     * @return CursorPaginator<int, MatchAnalysisData>
     */
    public function execute(CandidateProfile $profile, JobOpportunity $opportunity, int $perPage = 20): CursorPaginator
    {
        $analyses = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        $latestCompletedId = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->where('status', MatchAnalysisStatus::Completed)
            ->max('id');

        $analyses->setCollection(
            $analyses->getCollection()->map(
                fn (MatchAnalysis $analysis): MatchAnalysisData => MatchAnalysisData::fromModel(
                    analysis: $analysis,
                    stale: $this->staleness->isStale($analysis, $profile, $opportunity),
                    latest: $latestCompletedId !== null && $analysis->id === (int) $latestCompletedId,
                ),
            ),
        );

        return $analyses;
    }
}

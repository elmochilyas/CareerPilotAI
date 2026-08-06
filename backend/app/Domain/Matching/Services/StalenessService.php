<?php

namespace App\Domain\Matching\Services;

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;

final class StalenessService
{
    public function __construct(
        private readonly FingerprintService $fingerprints,
    ) {}

    public function isStale(MatchAnalysis $analysis, CandidateProfile $profile, JobOpportunity $opportunity): bool
    {
        $versions = $this->differingVersions($analysis, $profile, $opportunity);

        return $versions['profile'] || $versions['opportunity'];
    }

    /**
     * @return array{profile: bool, opportunity: bool}
     */
    public function differingVersions(MatchAnalysis $analysis, CandidateProfile $profile, JobOpportunity $opportunity): array
    {
        return [
            'profile' => $this->profileChanged($analysis, $profile),
            'opportunity' => $this->opportunityChanged($analysis, $opportunity),
        ];
    }

    private function profileChanged(MatchAnalysis $analysis, CandidateProfile $profile): bool
    {
        if ($analysis->profile_updated_at !== null && $analysis->profile_updated_at->equalTo($profile->updated_at)) {
            return false;
        }

        return $analysis->profile_fingerprint !== $this->fingerprints->profile(
            ProfileSnapshot::fromCandidateProfile($profile),
        );
    }

    private function opportunityChanged(MatchAnalysis $analysis, JobOpportunity $opportunity): bool
    {
        if ($analysis->opportunity_updated_at !== null && $analysis->opportunity_updated_at->equalTo($opportunity->updated_at)) {
            return false;
        }

        return $analysis->opportunity_fingerprint !== $this->fingerprints->opportunity(
            OpportunitySnapshot::fromJobOpportunity($opportunity),
        );
    }
}

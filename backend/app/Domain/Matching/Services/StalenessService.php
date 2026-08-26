<?php

namespace App\Domain\Matching\Services;

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;

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

    public function isResumeStale(Resume $resume, CandidateProfile $profile, JobOpportunity $opportunity): bool
    {
        $versions = $this->differingResumeVersions($resume, $profile, $opportunity);

        return $versions['profile'] || $versions['opportunity'];
    }

    /**
     * @return array{profile: bool, opportunity: bool}
     */
    public function differingResumeVersions(Resume $resume, CandidateProfile $profile, JobOpportunity $opportunity): array
    {
        return [
            'profile' => $this->resumeProfileChanged($resume, $profile),
            'opportunity' => $this->resumeOpportunityChanged($resume, $opportunity),
        ];
    }

    /**
     * Resume-scoped staleness label used by approval and resource presentation.
     */
    public function resumeStaleness(Resume $resume, CandidateProfile $profile, JobOpportunity $opportunity): string
    {
        $versions = $this->differingResumeVersions($resume, $profile, $opportunity);

        if (! $versions['profile'] && ! $versions['opportunity']) {
            return 'fresh';
        }

        if ($versions['profile'] && $versions['opportunity']) {
            return 'both_stale';
        }

        return $versions['profile'] ? 'profile_stale' : 'opportunity_stale';
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

    private function resumeProfileChanged(Resume $resume, CandidateProfile $profile): bool
    {
        $stored = $resume->profile_snapshot['fingerprint'] ?? null;

        if ($stored === null) {
            return true;
        }

        return $stored !== $this->fingerprints->profile(
            ProfileSnapshot::fromCandidateProfile($profile),
        );
    }

    private function resumeOpportunityChanged(Resume $resume, JobOpportunity $opportunity): bool
    {
        $stored = $resume->opportunity_snapshot['fingerprint'] ?? null;

        if ($stored === null) {
            return true;
        }

        return $stored !== $this->fingerprints->opportunity(
            OpportunitySnapshot::fromJobOpportunity($opportunity),
        );
    }
}

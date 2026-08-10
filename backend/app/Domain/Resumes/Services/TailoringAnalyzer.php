<?php

namespace App\Domain\Resumes\Services;

use App\Domain\Matching\Services\StalenessService;
use App\Domain\Resumes\Enums\TailoringRelevance;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use Illuminate\Support\Collection;

final readonly class TailoringAnalyzer
{
    /**
     * Section type mapping from match_finding category to resume section key.
     *
     * @var array<string, string>
     */
    private const SECTION_MAP = [
        'responsibility' => 'experience',
        'required_experience' => 'experience',
        'preferred_experience' => 'experience',
        'education' => 'education',
        'language' => 'languages',
        'required_certification' => 'certifications',
        'preferred_certification' => 'certifications',
    ];

    public function __construct(
        private StalenessService $stalenessService,
    ) {}

    /**
     * For each resume section type, determine which profile items are relevant
     * to the given opportunity based on match_findings with tailoring_relevance.
     *
     * Returns an array keyed by section type (skills, experience, education, projects, certifications, languages),
     * each value is a Collection of profile item IDs with their relevance data.
     *
     * @return array<string, Collection<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>
     */
    public function analyzeRelevance(CandidateProfile $profile, JobOpportunity $opportunity): array
    {
        $analysis = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($analysis === null) {
            return $this->emptyResult();
        }

        $findings = MatchFinding::query()
            ->where('match_analysis_id', $analysis->id)
            ->whereNotNull('tailoring_relevance')
            ->get();

        $grouped = $this->emptyResult();

        foreach ($findings as $finding) {
            $relevance = TailoringRelevance::tryFrom((string) $finding->tailoring_relevance);

            if ($relevance === null || ! $relevance->isRelevant()) {
                continue;
            }

            $sectionKey = $this->resolveSectionKey($finding);

            if ($sectionKey === null) {
                continue;
            }

            $profileItemId = $this->resolveProfileItemId($finding);

            if ($profileItemId === null) {
                continue;
            }

            $grouped[$sectionKey]->push([
                'profile_item_id' => $profileItemId,
                'relevance' => $relevance->value,
                'score' => (float) $finding->factor,
                'justification' => $finding->justification,
                'source_type' => $finding->source_type->value,
                'source_id' => $finding->source_id,
            ]);
        }

        return $grouped;
    }

    /**
     * Compute staleness for a profile+opportunity combination.
     */
    public function computeStaleness(CandidateProfile $profile, JobOpportunity $opportunity): string
    {
        $analysis = MatchAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_opportunity_id', $opportunity->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($analysis === null) {
            return 'unknown';
        }

        $versions = $this->stalenessService->differingVersions($analysis, $profile, $opportunity);

        if (! $versions['profile'] && ! $versions['opportunity']) {
            return 'fresh';
        }

        if ($versions['profile'] && $versions['opportunity']) {
            return 'both_stale';
        }

        return $versions['profile'] ? 'profile_stale' : 'opportunity_stale';
    }

    /**
     * Resolve the resume section key from a match finding.
     */
    private function resolveSectionKey(MatchFinding $finding): ?string
    {
        if ($finding->source_type->value === 'job_opportunity_skill') {
            return 'skills';
        }

        $category = $finding->category;

        if ($category === null) {
            return null;
        }

        return self::SECTION_MAP[$category] ?? null;
    }

    /**
     * Resolve the profile item ID from a match finding.
     */
    private function resolveProfileItemId(MatchFinding $finding): ?int
    {
        if ($finding->matched_candidate_skill_id !== null) {
            return $finding->matched_candidate_skill_id;
        }

        $evidenceRefs = $finding->evidence_refs;

        if (! is_array($evidenceRefs)) {
            return null;
        }

        foreach ($evidenceRefs as $ref) {
            if (is_array($ref) && isset($ref['type'], $ref['id']) && $ref['type'] === 'profile_item') {
                return (int) $ref['id'];
            }
        }

        return null;
    }

    /**
     * @return array<string, Collection<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>
     */
    private function emptyResult(): array
    {
        return [
            'skills' => collect(),
            'experience' => collect(),
            'education' => collect(),
            'projects' => collect(),
            'certifications' => collect(),
            'languages' => collect(),
        ];
    }
}

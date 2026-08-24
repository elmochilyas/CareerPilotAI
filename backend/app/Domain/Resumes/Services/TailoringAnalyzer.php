<?php

namespace App\Domain\Resumes\Services;

use App\Domain\Matching\Services\StalenessService;
use App\Domain\Matching\Services\TextNormalizer;
use App\Domain\Profile\Enums\ProfileItemType;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Domain\Resumes\Enums\TailoringRelevance;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;

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
     * each value is an array of profile item data with their relevance scores.
     *
     * @return array<string, array<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>
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
            return $this->deterministicFallback($profile, $opportunity);
        }

        $findings = MatchFinding::query()
            ->where('match_analysis_id', $analysis->id)
            ->whereNotNull('tailoring_relevance')
            ->get();

        $grouped = $this->emptyResult();
        $hasRelevant = false;

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

            $hasRelevant = true;
            $grouped[$sectionKey][] = [
                'profile_item_id' => $profileItemId,
                'relevance' => $relevance->value,
                'score' => (float) $finding->factor,
                'justification' => $finding->justification,
                'source_type' => $finding->source_type->value,
                'source_id' => $finding->source_id,
            ];
        }

        if (! $hasRelevant) {
            return $this->deterministicFallback($profile, $opportunity);
        }

        return $grouped;
    }

    /**
     * Deterministic fallback when tailoring_relevance is absent.
     * Uses JobOpportunity skills/requirements and TextNormalizer similarity, no AI invention.
     *
     * @return array<string, array<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>
     */
    private function deterministicFallback(CandidateProfile $profile, JobOpportunity $opportunity): array
    {
        $profile->loadMissing(['items', 'candidateSkills.skill']);
        $opportunity->loadMissing(['requirements', 'skills.skill']);

        $grouped = $this->emptyResult();

        $opportunityText = trim(collect([
            $opportunity->title,
            $opportunity->summary,
            $opportunity->requirements->pluck('content')->implode(' '),
            $opportunity->skills->map(fn ($s) => $s->skill !== null ? $s->skill->name : '')->implode(' '),
        ])->filter()->implode(' '));

        if ($opportunityText === '') {
            $opportunityText = $opportunity->title ?? 'general';
        }

        $requiredSkills = $opportunity->skills->where('classification', 'required')->map(fn ($s) => ProfileIdentityService::normalize($s->skill !== null ? $s->skill->name : ''))->filter()->values()->all();
        $preferredSkills = $opportunity->skills->where('classification', 'preferred')->map(fn ($s) => ProfileIdentityService::normalize($s->skill !== null ? $s->skill->name : ''))->filter()->values()->all();

        // Score profile items
        foreach ($profile->items as $item) {
            $itemText = trim(collect([$item->title, $item->organization, $item->description])->filter()->implode(' '));
            $score = 0.0;

            // Skill-based boost if item description contains required skill names
            $itemNorm = ProfileIdentityService::normalize($itemText);
            foreach ($requiredSkills as $req) {
                if (str_contains($itemNorm, $req)) {
                    $score += 0.35;
                }
            }
            foreach ($preferredSkills as $pref) {
                if (str_contains($itemNorm, $pref)) {
                    $score += 0.20;
                }
            }

            // Text similarity to opportunity
            $sim = TextNormalizer::similarity($opportunityText, $itemText);
            // Also use ProfileIdentityService textSimilarity for better typo tolerance
            $sim2 = ProfileIdentityService::textSimilarity($opportunityText, $itemText);
            $score += max($sim, $sim2) * 0.5;

            // Cap at 1.0
            $score = min(1.0, $score);

            $relevance = $score >= 0.60 ? 'high' : ($score >= 0.35 ? 'medium' : 'low');
            $isRelevant = $relevance === 'high' || $relevance === 'medium';

            // Ensure minimum floor: if no high/medium found, we will later include low
            $sectionKey = match ($item->type) {
                ProfileItemType::Experience => 'experience',
                ProfileItemType::Education => 'education',
                ProfileItemType::Project => 'projects',
                ProfileItemType::Certification => 'certifications',
            };

            // Only add if relevant, or keep low for fallback floor
            $grouped[$sectionKey][] = [
                'profile_item_id' => $item->id,
                'relevance' => $relevance,
                'score' => $score,
                'justification' => $isRelevant ? 'deterministic: matched opportunity keywords' : 'deterministic: low relevance',
                'source_type' => 'profile_item',
                'source_id' => $item->id,
            ];
        }

        // Filter to only relevant, but if less than 2 items total, include top low as well for floor
        $totalRelevant = collect($grouped)->flatten(1)->filter(fn ($r) => $r['relevance'] !== 'low')->count();
        if ($totalRelevant < 2) {
            // Keep low as well (already added), just ensure they have medium-ish score for ordering
            foreach ($grouped as $key => $items) {
                foreach ($items as &$it) {
                    if ($it['relevance'] === 'low' && $it['score'] < 0.30) {
                        $it['score'] = 0.30;
                        $it['relevance'] = 'medium';
                    }
                }
                $grouped[$key] = $items;
            }
        } else {
            // Remove low relevance if we have enough
            foreach ($grouped as $key => $items) {
                $grouped[$key] = array_values(array_filter($items, fn ($r) => $r['relevance'] !== 'low'));
            }
        }

        // Score skills
        foreach ($profile->candidateSkills as $cs) {
            $skillName = $cs->skill !== null ? $cs->skill->name : ($cs->custom_skill_name ?? '');
            $normSkill = ProfileIdentityService::normalize($skillName);
            $score = 0.0;
            $relevance = 'low';

            if (in_array($normSkill, $requiredSkills, true)) {
                $score = 0.95;
                $relevance = 'high';
            } elseif (in_array($normSkill, $preferredSkills, true)) {
                $score = 0.75;
                $relevance = 'medium';
            } else {
                // Check if skill appears in opportunity text
                if ($normSkill !== '' && str_contains(ProfileIdentityService::normalize($opportunityText), $normSkill)) {
                    $score = 0.60;
                    $relevance = 'medium';
                } else {
                    // Use similarity as fallback
                    $sim = TextNormalizer::similarity($opportunityText, $skillName);
                    if ($sim >= 0.5) {
                        $score = 0.55;
                        $relevance = 'medium';
                    } else {
                        $score = 0.20;
                        $relevance = 'low';
                    }
                }
            }

            if ($relevance === 'high' || $relevance === 'medium') {
                $grouped['skills'][] = [
                    'profile_item_id' => $cs->id,
                    'relevance' => $relevance,
                    'score' => $score,
                    'justification' => 'deterministic: skill match',
                    'source_type' => 'candidate_skill',
                    'source_id' => $cs->id,
                ];
            }
        }

        // Languages: if opportunity has language requirements, score, else medium
        $opportunityLangText = $opportunity->requirements->pluck('content')->filter(fn ($c) => str_contains(strtolower((string) $c), 'language') || str_contains(strtolower((string) $c), 'english') || str_contains(strtolower((string) $c), 'french'))->implode(' ');
        foreach (($profile->languages ?? []) as $idx => $lang) {
            $langName = (string) $lang['language'];
            $score = 0.50;
            $relevance = 'medium';
            if ($opportunityLangText !== '' && str_contains(ProfileIdentityService::normalize($opportunityLangText), ProfileIdentityService::normalize($langName))) {
                $score = 0.80;
                $relevance = 'high';
            }
            $grouped['languages'][] = [
                'profile_item_id' => $profile->id, // languages use profile id as source
                'relevance' => $relevance,
                'score' => $score,
                'justification' => 'deterministic: language',
                'source_type' => 'candidate_profile',
                'source_id' => $profile->id,
            ];
            // Only need one entry for languages relevance (not per language)
            break;
        }

        // Ensure at least skills/languages have something if profile has them
        if (empty($grouped['skills']) && $profile->candidateSkills->isNotEmpty()) {
            // Fallback: include top 3 skills by state
            $topSkills = $profile->candidateSkills->filter(fn ($cs) => in_array($cs->state->value, ['verified', 'claimed'], true))->take(3);
            foreach ($topSkills as $cs) {
                $grouped['skills'][] = [
                    'profile_item_id' => $cs->id,
                    'relevance' => 'medium',
                    'score' => 0.50,
                    'justification' => 'deterministic: fallback top skills',
                    'source_type' => 'candidate_skill',
                    'source_id' => $cs->id,
                ];
            }
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
     * @return array<string, array<int, array{profile_item_id: int, relevance: string, score: float, justification: string|null, source_type: string, source_id: int}>>
     */
    private function emptyResult(): array
    {
        return [
            'skills' => [],
            'experience' => [],
            'education' => [],
            'projects' => [],
            'certifications' => [],
            'languages' => [],
        ];
    }
}

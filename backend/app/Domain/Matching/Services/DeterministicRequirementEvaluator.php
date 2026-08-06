<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\MatchEvaluationResult;
use App\Domain\Matching\Data\MatchFindingResult;
use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchState;

final class DeterministicRequirementEvaluator
{
    /** Trusted candidate skill states that count as candidate data for matching. */
    private const TRUSTED_SKILL_STATES = ['verified', 'claimed', 'learning'];

    /**
     * @param  list<MatchRequirement>  $requirements
     */
    public function evaluate(ProfileSnapshot $profile, array $requirements): MatchEvaluationResult
    {
        $skillsIndex = $this->buildSkillsIndex($profile);

        $findings = [];
        foreach ($requirements as $requirement) {
            $findings[] = $this->evaluateRequirement($requirement, $profile, $skillsIndex);
        }

        return new MatchEvaluationResult(
            findings: $findings,
            categoryHasCandidateData: $this->categoryDataPresence($profile),
        );
    }

    /**
     * @param  array<string, list<array{id: int, normalized_name: string, state: string, years_experience: float|null, evidence: list<array{type: string, id: int|null, label: string|null}>}>>  $skillsIndex
     */
    private function evaluateRequirement(
        MatchRequirement $requirement,
        ProfileSnapshot $profile,
        array $skillsIndex,
    ): MatchFindingResult {
        return match ($requirement->category) {
            MatchCategory::RequiredSkills->value, MatchCategory::PreferredSkills->value => $this->evaluateSkill($requirement, $skillsIndex),
            MatchCategory::LanguageSoft->value => $this->evaluateLanguage($requirement, $profile),
            MatchCategory::ExperienceEducation->value => $this->evaluateExperienceEducation($requirement, $profile),
            MatchCategory::Evidence->value => $requirement->sourceCategory === 'responsibility'
                ? $this->finding($requirement, MatchState::Unknown, 0.0, null, [], 'Deterministic pass defers this responsibility to the semantic classifier.')
                : $this->evaluateCertification($requirement, $profile),
            default => $this->finding($requirement, MatchState::Unknown, 0.0, null, [], 'Requirement category is not recognized.'),
        };
    }

    /**
     * @param  array<string, list<array{id: int, normalized_name: string, state: string, years_experience: float|null, evidence: list<array{type: string, id: int|null, label: string|null}>}>>  $skillsIndex
     */
    private function evaluateSkill(MatchRequirement $requirement, array $skillsIndex): MatchFindingResult
    {
        $normalized = TextNormalizer::normalize($requirement->text);
        $tokens = TextNormalizer::tokens($requirement->text);

        $best = null;

        foreach ($skillsIndex as $key => $candidates) {
            $matchScore = $key === $normalized ? 3 : 0;

            if ($matchScore === 0 && $tokens !== []) {
                $keyTokens = TextNormalizer::tokens($key);

                if ($keyTokens !== [] && count(array_intersect($tokens, $keyTokens)) === count($tokens)) {
                    $matchScore = 2;
                }
            }

            if ($matchScore === 0) {
                continue;
            }

            foreach ($candidates as $candidate) {
                $factor = $this->factorForState($candidate['state']);

                if ($best === null
                    || $matchScore > $best['match']
                    || ($matchScore === $best['match'] && $factor > $best['factor'])) {
                    $best = [
                        'match' => $matchScore,
                        'factor' => $factor,
                        'candidate' => $candidate,
                    ];
                }
            }
        }

        if ($best === null) {
            return $this->finding($requirement, MatchState::Gap, 0.0, null, [], 'Skill is not present in the candidate profile.');
        }

        $state = $best['factor'] >= 1.0
            ? MatchState::Matched
            : ($best['factor'] > 0.0 ? MatchState::Partial : MatchState::Gap);

        $justification = $best['candidate']['state'] === 'verified'
            ? 'Skill matched from verified profile data.'
            : 'Skill matched but not verified ('.($best['candidate']['state']).').';

        return $this->finding(
            $requirement,
            $state,
            $best['factor'],
            $best['candidate']['id'],
            $best['candidate']['evidence'],
            $justification,
        );
    }

    private function evaluateLanguage(MatchRequirement $requirement, ProfileSnapshot $profile): MatchFindingResult
    {
        if ($profile->languages === []) {
            return $this->finding($requirement, MatchState::Unknown, 0.0, null, [], 'Profile has no language data to evaluate this requirement.');
        }

        $target = TextNormalizer::normalize($requirement->text);
        $best = null;

        foreach ($profile->languages as $entry) {
            if (TextNormalizer::normalize($entry['language']) !== $target) {
                continue;
            }

            $factor = $this->languageFactor($entry['proficiency']);

            if ($best === null || $factor > $best['factor']) {
                $best = [
                    'factor' => $factor,
                    'proficiency' => $entry['proficiency'],
                ];
            }
        }

        if ($best === null) {
            return $this->finding($requirement, MatchState::Gap, 0.0, null, [], 'Language is not listed in the candidate profile.');
        }

        $state = $best['factor'] >= 1.0 ? MatchState::Matched : MatchState::Partial;

        return $this->finding(
            $requirement,
            $state,
            $best['factor'],
            null,
            [],
            'Language listed in profile at proficiency "'.($best['proficiency'] ?? 'unknown').'".',
        );
    }

    private function evaluateExperienceEducation(MatchRequirement $requirement, ProfileSnapshot $profile): MatchFindingResult
    {
        $itemType = $requirement->sourceCategory === 'education' ? 'education' : 'experience';
        $items = array_values(array_filter(
            $profile->items,
            static fn (array $item): bool => $item['type'] === $itemType,
        ));

        if ($items === []) {
            return $this->finding($requirement, MatchState::Unknown, 0.0, null, [], "Profile has no {$itemType} items to evaluate this requirement.");
        }

        return $this->evaluateByTextOverlap($requirement, $items);
    }

    private function evaluateCertification(MatchRequirement $requirement, ProfileSnapshot $profile): MatchFindingResult
    {
        $items = array_values(array_filter(
            $profile->items,
            static fn (array $item): bool => $item['type'] === 'certification',
        ));

        if ($items === []) {
            return $this->finding($requirement, MatchState::Unknown, 0.0, null, [], 'Profile has no certification items to evaluate this requirement.');
        }

        return $this->evaluateByTextOverlap($requirement, $items);
    }

    /**
     * @param  list<array{id: int, type: string, title: string, organization: string|null, description: string|null}>  $items
     */
    private function evaluateByTextOverlap(MatchRequirement $requirement, array $items): MatchFindingResult
    {
        $requirementText = preg_replace('/\s*\(\d+\s*years?\)\s*$/', '', $requirement->text) ?? $requirement->text;

        $best = 0.0;
        foreach ($items as $item) {
            $candidateText = trim($item['title'].' '.($item['organization'] ?? '').' '.($item['description'] ?? ''));
            $best = max($best, TextNormalizer::similarity($requirementText, $candidateText));
        }

        $thresholds = config('matching.text_overlap');
        $matchedThreshold = (float) $thresholds['matched'];
        $partialThreshold = (float) $thresholds['partial'];

        if ($best >= $matchedThreshold) {
            return $this->finding($requirement, MatchState::Matched, 1.0, null, [], 'Requirement satisfied by a matching profile item.');
        }

        if ($best >= $partialThreshold) {
            return $this->finding($requirement, MatchState::Partial, 0.5, null, [], 'Requirement partially supported by profile items.');
        }

        return $this->finding($requirement, MatchState::Gap, 0.0, null, [], 'No profile item supports this requirement.');
    }

    private function factorForState(string $state): float
    {
        return match ($state) {
            'verified' => (float) config('matching.factors.verified'),
            'claimed' => (float) config('matching.factors.claimed'),
            'learning' => (float) config('matching.factors.learning'),
            default => (float) config('matching.factors.missing'),
        };
    }

    private function languageFactor(?string $proficiency): float
    {
        $factors = config('matching.language_factors');

        return isset($proficiency) && array_key_exists($proficiency, $factors)
            ? (float) $factors[$proficiency]
            : (float) $factors['unknown'];
    }

    /**
     * @return array<string, list<array{id: int, normalized_name: string, state: string, years_experience: float|null, evidence: list<array{type: string, id: int|null, label: string|null}>}>>
     */
    private function buildSkillsIndex(ProfileSnapshot $profile): array
    {
        $index = [];

        foreach ($profile->skills as $skill) {
            $index[TextNormalizer::normalize($skill['normalized_name'])][] = $skill;
        }

        return $index;
    }

    /**
     * @return array<string, bool>
     */
    private function categoryDataPresence(ProfileSnapshot $profile): array
    {
        $hasTrustedSkill = false;
        foreach ($profile->skills as $skill) {
            if (in_array($skill['state'], self::TRUSTED_SKILL_STATES, true)) {
                $hasTrustedSkill = true;
                break;
            }
        }

        $itemTypes = array_column($profile->items, 'type');

        return [
            MatchCategory::RequiredSkills->value => $hasTrustedSkill,
            MatchCategory::PreferredSkills->value => $hasTrustedSkill,
            MatchCategory::Evidence->value => in_array('project', $itemTypes, true)
                || in_array('certification', $itemTypes, true),
            MatchCategory::ExperienceEducation->value => in_array('experience', $itemTypes, true)
                || in_array('education', $itemTypes, true),
            MatchCategory::LanguageSoft->value => $profile->languages !== [],
        ];
    }

    /**
     * @param  array<int, array{type: string, id: int|null, label: string|null}>  $evidenceRefs
     */
    private function finding(
        MatchRequirement $requirement,
        MatchState $state,
        float $factor,
        ?int $matchedCandidateSkillId,
        array $evidenceRefs,
        string $justification,
    ): MatchFindingResult {
        return new MatchFindingResult(
            sourceType: $requirement->sourceType,
            sourceId: $requirement->sourceId,
            requirementText: $requirement->text,
            requirementLabel: $requirement->label,
            importance: $requirement->importance,
            category: $requirement->category,
            matchState: $state,
            factor: $factor,
            matchedCandidateSkillId: $matchedCandidateSkillId,
            evidenceRefs: $evidenceRefs,
            justification: $justification,
            confidence: null,
            classifierSource: null,
            displayOrder: $requirement->displayOrder,
        );
    }
}

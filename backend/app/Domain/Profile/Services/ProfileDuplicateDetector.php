<?php

namespace App\Domain\Profile\Services;

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Collection;

final class ProfileDuplicateDetector
{
    /**
     * Detect all duplicate groups for a single profile.
     *
     * @return array{
     *   profile_id: int,
     *   user_id: int,
     *   groups: list<array{
     *     entity_type: string,
     *     subtype: string,
     *     classification: string,
     *     normalized_identity: string,
     *     record_ids: list<int>,
     *     records: list<array>,
     *     reason: string,
     *     similarity: float|null,
     *     recommended_action: string
     *   }>,
     *   summary: array{exact: int, possible: int, legitimate: int}
     * }
     */
    public function detectForProfile(CandidateProfile $profile): array
    {
        $profile->loadMissing(['items', 'candidateSkills.skill']);

        $groups = [];

        // Profile items: per type
        $byType = $profile->items->groupBy(fn (ProfileItem $item) => $item->type->value);

        foreach ($byType as $type => $items) {
            $exactGroups = $this->detectExactItemGroups($items, $type);
            foreach ($exactGroups as $g) {
                $groups[] = $g;
            }

            $possibleGroups = $this->detectPossibleItemGroups($items, $type);
            foreach ($possibleGroups as $g) {
                // Avoid duplicating groups already reported as exact (same ids)
                $already = false;
                foreach ($groups as $eg) {
                    if ($eg['entity_type'] === 'profile_items' && $eg['subtype'] === $type && $eg['classification'] === 'exact_duplicate' && count(array_intersect($eg['record_ids'], $g['record_ids'])) > 1) {
                        $already = true;
                        break;
                    }
                }
                if (! $already) {
                    $groups[] = $g;
                }
            }

            // Legitimate rehire groups: same key but non-overlapping dates
            $legitGroups = $this->detectLegitimateSeparateGroups($items, $type);
            foreach ($legitGroups as $g) {
                $groups[] = $g;
            }
        }

        // Candidate skills
        $skillGroups = $this->detectSkillGroups($profile->candidateSkills);
        foreach ($skillGroups as $g) {
            $groups[] = $g;
        }

        // Languages
        $languageGroups = $this->detectLanguageGroups($profile->languages);
        foreach ($languageGroups as $g) {
            $groups[] = $g;
        }

        $summary = [
            'exact' => count(array_filter($groups, fn ($g) => $g['classification'] === 'exact_duplicate')),
            'possible' => count(array_filter($groups, fn ($g) => $g['classification'] === 'possible_duplicate')),
            'legitimate' => count(array_filter($groups, fn ($g) => $g['classification'] === 'legitimate_separate')),
        ];

        return [
            'profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'groups' => $groups,
            'summary' => $summary,
        ];
    }

    /**
     * @param  Collection<int, ProfileItem>  $items
     * @return list<array>
     */
    private function detectExactItemGroups(Collection $items, string $type): array
    {
        // Group by normalized identity key
        $byKey = [];
        foreach ($items as $item) {
            $key = ProfileIdentityService::normalize($item->type->value).'|'.ProfileIdentityService::normalize($item->title).'|'.ProfileIdentityService::normalize($item->organization ?? '');
            $byKey[$key][] = $item;
        }

        $groups = [];
        foreach ($byKey as $key => $cluster) {
            if (count($cluster) < 2) {
                continue;
            }

            // Split by date compatibility: if gap >180, treat as separate clusters
            $subClusters = $this->clusterByDateCompatibility($cluster, $type);

            foreach ($subClusters as $sub) {
                if (count($sub) < 2) {
                    continue;
                }

                $ids = array_map(fn (ProfileItem $it) => $it->id, $sub);
                sort($ids);
                $groups[] = [
                    'entity_type' => 'profile_items',
                    'subtype' => $type,
                    'classification' => 'exact_duplicate',
                    'normalized_identity' => $key,
                    'record_ids' => $ids,
                    'records' => array_map(fn (ProfileItem $it) => $this->serializeItem($it), $sub),
                    'reason' => 'identical normalized title and organization with compatible dates',
                    'similarity' => 1.0,
                    'recommended_action' => 'merge_keep_one',
                ];
            }
        }

        return $groups;
    }

    /**
     * @param  list<ProfileItem>  $cluster
     * @return list<list<ProfileItem>>
     */
    private function clusterByDateCompatibility(array $cluster, string $type): array
    {
        // Sort by start_date
        usort($cluster, function (ProfileItem $a, ProfileItem $b) {
            $aStart = $a->start_date !== null ? $a->start_date->timestamp : 0;
            $bStart = $b->start_date !== null ? $b->start_date->timestamp : 0;

            return $aStart <=> $bStart;
        });

        $clusters = [];
        $current = [];

        foreach ($cluster as $item) {
            if ($current === []) {
                $current[] = $item;

                continue;
            }

            $last = end($current);
            $lastInterval = ProfileIdentityService::extractIntervalForProfileItem($last);
            $curInterval = ProfileIdentityService::extractIntervalForProfileItem($item);

            if (ProfileIdentityService::areDateIntervalsCompatible(
                $lastInterval['start'],
                $lastInterval['end'],
                $curInterval['start'],
                $curInterval['end']
            )) {
                $current[] = $item;
            } else {
                $clusters[] = $current;
                $current = [$item];
            }
        }

        if ($current !== []) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    /**
     * @param  Collection<int, ProfileItem>  $items
     * @return list<array>
     */
    private function detectPossibleItemGroups(Collection $items, string $type): array
    {
        // For possible, we need to find pairs where textSimilarity strong and dates compatible, but not exact
        $candidates = $items->values();
        $visited = [];
        $groups = [];

        for ($i = 0; $i < $candidates->count(); $i++) {
            $a = $candidates[$i];
            if (isset($visited[$a->id])) {
                continue;
            }

            $group = [$a];
            $reasons = [];
            $similarities = [];

            for ($j = $i + 1; $j < $candidates->count(); $j++) {
                $b = $candidates[$j];
                if (isset($visited[$b->id])) {
                    continue;
                }

                // Skip if already exact duplicate (same key)
                $keyA = ProfileIdentityService::normalize($a->type->value).'|'.ProfileIdentityService::normalize($a->title).'|'.ProfileIdentityService::normalize($a->organization ?? '');
                $keyB = ProfileIdentityService::normalize($b->type->value).'|'.ProfileIdentityService::normalize($b->title).'|'.ProfileIdentityService::normalize($b->organization ?? '');
                if ($keyA === $keyB) {
                    continue;
                }

                // Use service to check possible duplicate: create a synthetic value array for b
                $valueForB = [
                    'title' => $b->title,
                    'name' => $b->title,
                    'degree' => $b->title,
                    'organization' => $b->organization,
                    'institution' => $b->organization,
                    'issuer' => $b->organization,
                    'start_date' => $b->start_date?->toDateString(),
                    'end_date' => $b->end_date?->toDateString(),
                    'is_current' => $b->end_date === null && $b->start_date !== null,
                ];

                // Check if b would be considered possible duplicate of a's collection
                $singleCollection = new Collection([$a]);
                $possible = ProfileIdentityService::findPossibleDuplicateProfileItem($singleCollection, $type, $valueForB);

                if ($possible !== null) {
                    $group[] = $b;
                    $visited[$b->id] = true;
                    $reasons[] = $possible['reason'];
                    $similarities[] = $possible['similarity'];
                }
            }

            if (count($group) > 1) {
                $visited[$a->id] = true;
                $ids = array_map(fn (ProfileItem $it) => $it->id, $group);
                sort($ids);
                $avgSim = count($similarities) > 0 ? array_sum($similarities) / count($similarities) : null;
                $groups[] = [
                    'entity_type' => 'profile_items',
                    'subtype' => $type,
                    'classification' => 'possible_duplicate',
                    'normalized_identity' => 'possible:'.$type.':'.implode(',', $ids),
                    'record_ids' => $ids,
                    'records' => array_map(fn (ProfileItem $it) => $this->serializeItem($it), $group),
                    'reason' => implode('; ', array_unique($reasons)) ?: 'similar title/organization with compatible dates',
                    'similarity' => $avgSim !== null ? round($avgSim, 2) : null,
                    'recommended_action' => 'review_manually',
                ];
            }
        }

        return $groups;
    }

    /**
     * Detect legitimate separate groups (same key but non-overlapping dates).
     *
     * @param  Collection<int, ProfileItem>  $items
     * @return list<array>
     */
    private function detectLegitimateSeparateGroups(Collection $items, string $type): array
    {
        $byKey = [];
        foreach ($items as $item) {
            $key = ProfileIdentityService::normalize($item->type->value).'|'.ProfileIdentityService::normalize($item->title).'|'.ProfileIdentityService::normalize($item->organization ?? '');
            $byKey[$key][] = $item;
        }

        $groups = [];
        foreach ($byKey as $key => $cluster) {
            if (count($cluster) < 2) {
                continue;
            }

            // If cluster was split due to date incompatibility, those are legitimate separate
            $subClusters = $this->clusterByDateCompatibility($cluster, $type);
            if (count($subClusters) > 1) {
                // There are multiple subclusters for same key -> legitimate rehire case
                foreach ($subClusters as $sub) {
                    if (count($sub) < 1) {
                        continue;
                    }
                }
                // Report as legitimate_separate with all ids, but mark as not duplicate
                $allIds = array_map(fn (ProfileItem $it) => $it->id, $cluster);
                sort($allIds);
                $groups[] = [
                    'entity_type' => 'profile_items',
                    'subtype' => $type,
                    'classification' => 'legitimate_separate',
                    'normalized_identity' => $key,
                    'record_ids' => $allIds,
                    'records' => array_map(fn (ProfileItem $it) => $this->serializeItem($it), $cluster),
                    'reason' => 'same title/organization but clearly non-overlapping dates (rehire) — keep separate',
                    'similarity' => 1.0,
                    'recommended_action' => 'keep_separate',
                ];
            }
        }

        return $groups;
    }

    private function serializeItem(ProfileItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'title' => $item->title,
            'organization' => $item->organization,
            'location' => $item->location,
            'start_date' => $item->start_date?->toDateString(),
            'end_date' => $item->end_date?->toDateString(),
            'description' => $item->description ? mb_substr($item->description, 0, 120) : null,
        ];
    }

    /**
     * @param  Collection<int, CandidateSkill>  $skills
     * @return list<array>
     */
    private function detectSkillGroups(Collection $skills): array
    {
        if ($skills->isEmpty()) {
            return [];
        }

        $allSkills = Skill::all();
        $allAliases = SkillAlias::all();
        $maps = ProfileIdentityService::buildSkillLookupMaps($allSkills, $allAliases);

        // Group by normalized identity (alias-aware)
        $byNorm = [];
        foreach ($skills as $cs) {
            $norm = $this->skillNormalizedIdentity($cs, $maps);
            if ($norm === '') {
                continue;
            }
            $byNorm[$norm][] = $cs;
        }

        $groups = [];
        foreach ($byNorm as $norm => $cluster) {
            if (count($cluster) < 2) {
                continue;
            }

            $ids = array_map(fn (CandidateSkill $s) => $s->id, $cluster);
            sort($ids);

            // Determine if cross-type (canonical vs custom)
            $hasCanonical = count(array_filter($cluster, fn (CandidateSkill $s) => $s->skill_id !== null)) > 0;
            $hasCustom = count(array_filter($cluster, fn (CandidateSkill $s) => $s->skill_id === null)) > 0;
            $reason = 'identical normalized skill name';
            if ($hasCanonical && $hasCustom) {
                $reason = 'canonical and custom equivalent (e.g., JavaScript + JS)';
            } elseif ($hasCustom) {
                $reason = 'custom/custom normalized equivalent (case/whitespace/accent)';
            }

            $groups[] = [
                'entity_type' => 'candidate_skills',
                'subtype' => 'skill',
                'classification' => 'exact_duplicate',
                'normalized_identity' => $norm,
                'record_ids' => $ids,
                'records' => array_map(fn (CandidateSkill $s) => $this->serializeSkill($s), $cluster),
                'reason' => $reason,
                'similarity' => 1.0,
                'recommended_action' => 'merge_keep_canonical',
            ];
        }

        return $groups;
    }

    private function skillNormalizedIdentity(CandidateSkill $cs, array $maps): string
    {
        $skillsByNorm = $maps['skills'];
        $aliasesByNorm = $maps['aliases'];

        if ($cs->skill !== null) {
            return ProfileIdentityService::normalize($cs->skill->normalized_name);
        }

        if ($cs->skill_id !== null) {
            $skill = $skillsByNorm->first(fn (Skill $s) => $s->id === $cs->skill_id);
            if ($skill) {
                return ProfileIdentityService::normalize($skill->normalized_name);
            }
        }

        $customNorm = ProfileIdentityService::normalize($cs->custom_skill_name ?? '');
        $alias = $aliasesByNorm->get($customNorm);
        if ($alias !== null) {
            $canonical = $skillsByNorm->first(fn (Skill $s) => $s->id === $alias->skill_id);
            if ($canonical) {
                return ProfileIdentityService::normalize($canonical->normalized_name);
            }
        }

        return $customNorm;
    }

    private function serializeSkill(CandidateSkill $cs): array
    {
        return [
            'id' => $cs->id,
            'skill_id' => $cs->skill_id,
            'skill_name' => $cs->skill?->name,
            'custom_skill_name' => $cs->custom_skill_name,
            'normalized' => $this->skillNormalizedIdentity($cs, ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all())),
            'state' => $cs->state->value,
            'proficiency_level' => $cs->proficiency_level?->value,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $languages
     * @return list<array>
     */
    private function detectLanguageGroups(?array $languages): array
    {
        if (empty($languages)) {
            return [];
        }

        $byNorm = [];
        foreach ($languages as $idx => $entry) {
            $lang = isset($entry['language']) && is_string($entry['language']) ? $entry['language'] : '';
            $norm = ProfileIdentityService::languageKey($lang);
            if ($norm === '') {
                continue;
            }
            $byNorm[$norm][] = ['entry' => $entry, 'index' => $idx];
        }

        $groups = [];
        foreach ($byNorm as $norm => $cluster) {
            if (count($cluster) < 2) {
                continue;
            }

            $indices = array_map(fn ($c) => $c['index'], $cluster);
            $records = array_map(fn ($c) => $c['entry'], $cluster);

            $groups[] = [
                'entity_type' => 'languages',
                'subtype' => 'language',
                'classification' => 'exact_duplicate',
                'normalized_identity' => $norm,
                'record_ids' => $indices, // for JSON languages, we use indices as ids
                'records' => $records,
                'reason' => 'identical normalized language (case/whitespace/accent)',
                'similarity' => 1.0,
                'recommended_action' => 'deduplicate_keep_best_proficiency',
            ];
        }

        return $groups;
    }
}

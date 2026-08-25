<?php

namespace App\Domain\Profile\Services;

use App\Models\CandidateSkill;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ProfileIdentityService
{
    private const FRENCH_MONTH_NAMES = [
        'janvier' => 'January',
        'janv' => 'January',
        'jan' => 'January',
        'février' => 'February',
        'fevrier' => 'February',
        'févr' => 'February',
        'fevr' => 'February',
        'mars' => 'March',
        'mar' => 'March',
        'avril' => 'April',
        'avr' => 'April',
        'mai' => 'May',
        'juin' => 'June',
        'juillet' => 'July',
        'juil' => 'July',
        'août' => 'August',
        'aout' => 'August',
        'aoû' => 'August',
        'septembre' => 'September',
        'sept' => 'September',
        'sep' => 'September',
        'octobre' => 'October',
        'oct' => 'October',
        'novembre' => 'November',
        'nov' => 'November',
        'décembre' => 'December',
        'decembre' => 'December',
        'déc' => 'December',
        'dec' => 'December',
    ];

    private const SIMILARITY_THRESHOLD_STRONG = 0.85;

    private const SIMILARITY_THRESHOLD_VERY_STRONG = 0.90;

    private const ORG_THRESHOLD_STRONG = 0.85;

    private const ORG_THRESHOLD_VERY_STRONG = 0.92;

    private const DATE_GAP_THRESHOLD_DAYS = 180;

    /**
     * Canonical normalizer: lowercase, trim, collapse repeated whitespace,
     * and safe Unicode/accent folding where appropriate.
     *
     * This is the single source of truth for profile entity identity.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Safe accent folding via transliteration if available.
        // Prefer transliterator_transliterate when intl is present; fallback to Str::ascii.
        if (function_exists('transliterator_transliterate')) {
            $transliterated = \transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC', $value);

            if ($transliterated !== false && $transliterated !== '') {
                $value = $transliterated;
            }
        } else {
            $value = Str::ascii($value);
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Build normalized key for profile items (experience/project/education/certification).
     *
     * Identity = type | normalized(title|name|degree) | normalized(organization|institution|issuer)
     * Dates are intentionally excluded here; they remain available for later ambiguity handling.
     */
    public static function profileItemKey(string $type, array $value): string
    {
        $normalizedType = self::normalize($type);
        $normalizedTitle = self::normalize(self::extractTitle($type, $value));
        $normalizedOrg = self::normalize(self::extractOrganization($type, $value));

        return $normalizedType.'|'.$normalizedTitle.'|'.$normalizedOrg;
    }

    /**
     * Extract canonical title for a given type from raw entity array.
     */
    public static function extractTitle(string $type, array $value): string
    {
        return match ($type) {
            'experience' => (string) ($value['title'] ?? ''),
            'project' => (string) ($value['name'] ?? $value['title'] ?? ''),
            'education' => (string) ($value['degree'] ?? $value['title'] ?? ''),
            'certification' => (string) ($value['name'] ?? $value['title'] ?? ''),
            default => (string) ($value['title'] ?? $value['name'] ?? $value['degree'] ?? ''),
        };
    }

    /**
     * Extract canonical organization for a given type.
     * For certification, issuer is the organization and was previously ignored (bug).
     */
    public static function extractOrganization(string $type, array $value): string
    {
        return match ($type) {
            'experience' => (string) ($value['organization'] ?? ''),
            'education' => (string) ($value['institution'] ?? $value['organization'] ?? ''),
            'project' => (string) ($value['organization'] ?? ''),
            'certification' => (string) ($value['issuer'] ?? $value['organization'] ?? $value['institution'] ?? ''),
            default => (string) ($value['organization'] ?? $value['institution'] ?? $value['issuer'] ?? ''),
        };
    }

    public static function languageKey(?string $language): string
    {
        return self::normalize($language);
    }

    /**
     * Find existing ProfileItem using normalized identity.
     * Considers rehire: same title/org but clearly non-overlapping dates (>180 days) are not exact duplicates.
     *
     * @param  Collection<int, ProfileItem>  $items
     */
    public static function findExistingProfileItem(Collection $items, string $type, array $value): ?ProfileItem
    {
        $targetKey = self::profileItemKey($type, $value);
        $normalizedType = self::normalize($type);

        $incomingInterval = self::extractIntervalForIncoming($type, $value);

        return $items->first(function (ProfileItem $item) use ($normalizedType, $targetKey, $incomingInterval): bool {
            $itemKey = self::normalize($item->type->value).'|'.self::normalize($item->title).'|'.self::normalize($item->organization ?? '');

            if ($itemKey !== $targetKey || self::normalize($item->type->value) !== $normalizedType) {
                return false;
            }

            // Date-aware exact: if same title/org but dates are clearly separate (gap >180), treat as not duplicate (rehire)
            $existingInterval = self::extractIntervalForProfileItem($item);
            if (! self::areDateIntervalsCompatible(
                $incomingInterval['start'],
                $incomingInterval['end'],
                $existingInterval['start'],
                $existingInterval['end']
            )) {
                return false;
            }

            return true;
        });
    }

    /**
     * Find existing language entry (case/whitespace/accent insensitive).
     *
     * @param  array<int, array<string, mixed>>|null  $languages
     */
    public static function findExistingLanguage(?array $languages, string $language): ?array
    {
        $target = self::languageKey($language);

        if ($target === '') {
            return null;
        }

        foreach ($languages ?? [] as $entry) {
            $langValue = isset($entry['language']) && is_string($entry['language']) ? $entry['language'] : '';
            if (self::languageKey($langValue) === $target) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Build lookup maps keyed by canonical normalized values.
     *
     * @param  Collection<int, Skill>  $skills
     * @param  Collection<int, SkillAlias>  $aliases
     * @return array{skills: Collection<string, Skill>, aliases: Collection<string, SkillAlias>}
     */
    public static function buildSkillLookupMaps(Collection $skills, Collection $aliases): array
    {
        $skillMap = $skills->keyBy(fn (Skill $s) => self::normalize($s->normalized_name));
        $aliasMap = $aliases->keyBy(fn (SkillAlias $a) => self::normalize($a->alias));

        return ['skills' => $skillMap, 'aliases' => $aliasMap];
    }

    /**
     * Resolve a skill name via canonical normalized name → alias → custom fallback.
     *
     * Order: normalized Skill name → SkillAlias → custom (null skill).
     *
     * @param  Collection<string, Skill>  $skillsByNormalized  keyed by normalize(normalized_name)
     * @param  Collection<string, SkillAlias>  $aliasesByNormalized  keyed by normalize(alias)
     * @return array{skill: ?Skill, normalized: string, isCustom: bool}
     */
    public static function resolveSkill(string $skillName, Collection $skillsByNormalized, Collection $aliasesByNormalized): array
    {
        $normalized = self::normalize($skillName);

        if ($normalized === '') {
            return ['skill' => null, 'normalized' => '', 'isCustom' => true];
        }

        $skill = $skillsByNormalized->get($normalized);

        if ($skill !== null) {
            return ['skill' => $skill, 'normalized' => self::normalize($skill->normalized_name), 'isCustom' => false];
        }

        $alias = $aliasesByNormalized->get($normalized);

        if ($alias !== null) {
            $aliasedSkill = $alias->relationLoaded('skill') ? $alias->skill : Skill::find($alias->skill_id);
            if ($aliasedSkill) {
                return ['skill' => $aliasedSkill, 'normalized' => self::normalize($aliasedSkill->normalized_name), 'isCustom' => false];
            }
            // Fallback via map if DB fetch fails
            $mapSkill = $skillsByNormalized->first(fn (Skill $s) => $s->id === $alias->skill_id);
            if ($mapSkill) {
                return ['skill' => $mapSkill, 'normalized' => self::normalize($mapSkill->normalized_name), 'isCustom' => false];
            }
        }

        return ['skill' => null, 'normalized' => $normalized, 'isCustom' => true];
    }

    /**
     * Find existing CandidateSkill using canonical identity (prevents canonical/custom duplicates).
     *
     * Compares via normalized canonical skill name OR normalized custom name.
     * Also handles alias → canonical via build maps.
     *
     * @param  Collection<int, CandidateSkill>  $candidateSkills
     * @param  Collection<string, Skill>  $skillsByNormalized
     * @param  Collection<string, SkillAlias>  $aliasesByNormalized
     */
    public static function findExistingCandidateSkill(
        Collection $candidateSkills,
        string $skillName,
        Collection $skillsByNormalized,
        Collection $aliasesByNormalized,
    ): ?CandidateSkill {
        $incoming = self::resolveSkill($skillName, $skillsByNormalized, $aliasesByNormalized);
        $incomingNormalized = $incoming['normalized'];
        $incomingSkillId = $incoming['skill']?->id;

        if ($incomingNormalized === '') {
            return null;
        }

        // Pre-compute candidate normalized identities and alias-resolved ids
        foreach ($candidateSkills as $candidateSkill) {
            $candidateNormalized = null;
            $candidateSkillId = null;

            if ($candidateSkill->skill !== null) {
                $candidateSkillId = $candidateSkill->skill->id;
                $candidateNormalized = self::normalize($candidateSkill->skill->normalized_name);
            } elseif ($candidateSkill->skill_id !== null) {
                // skill relation not loaded but id present
                $candidateSkillId = $candidateSkill->skill_id;
                $skillForId = $skillsByNormalized->first(fn (Skill $s) => $s->id === $candidateSkillId);
                $candidateNormalized = $skillForId ? self::normalize($skillForId->normalized_name) : null;
            }

            if ($candidateSkill->skill === null && $candidateSkill->custom_skill_name !== null) {
                $customNormalized = self::normalize($candidateSkill->custom_skill_name);
                $aliasForCustom = $aliasesByNormalized->get($customNormalized);
                if ($aliasForCustom !== null) {
                    $canonical = $skillsByNormalized->first(fn (Skill $s) => $s->id === $aliasForCustom->skill_id);
                    if ($canonical === null) {
                        $canonical = Skill::find($aliasForCustom->skill_id);
                    }
                    if ($canonical !== null) {
                        $candidateNormalized = self::normalize($canonical->normalized_name);
                        $candidateSkillId = $canonical->id;
                    } else {
                        $candidateNormalized = $customNormalized;
                    }
                } else {
                    $candidateNormalized = $customNormalized;
                }
            }

            // Match by canonical skill id if both resolved to canonical
            if ($incomingSkillId !== null && $candidateSkillId !== null && $incomingSkillId === $candidateSkillId) {
                return $candidateSkill;
            }

            // Otherwise match by normalized string equality (covers canonical/custom cross duplicates)
            if ($candidateNormalized !== null && $candidateNormalized === $incomingNormalized) {
                return $candidateSkill;
            }
        }

        return null;
    }

    /**
     * Deduplicate a collection of ProfileItems keeping first occurrence per normalized identity.
     *
     * @param  Collection<int, ProfileItem>  $items
     * @return Collection<int, ProfileItem>
     */
    public static function deduplicateProfileItems(Collection $items): Collection
    {
        $seen = [];

        return $items->filter(function (ProfileItem $item) use (&$seen): bool {
            $key = self::normalize($item->type->value).'|'.self::normalize($item->title).'|'.self::normalize($item->organization ?? '');
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->values();
    }

    /**
     * Deduplicate languages array keeping first per normalized language.
     *
     * @param  array<int, array<string, mixed>>|null  $languages
     * @return array<int, array<string, mixed>>
     */
    public static function deduplicateLanguages(?array $languages): array
    {
        if ($languages === null) {
            return [];
        }

        $seen = [];
        $result = [];

        foreach ($languages as $entry) {
            $langVal = isset($entry['language']) && is_string($entry['language']) ? $entry['language'] : '';
            $key = self::languageKey($langVal);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Deduplicate candidate skills keeping first per normalized identity (alias-aware).
     *
     * @param  Collection<int, CandidateSkill>  $skills
     * @param  Collection<string, Skill>  $skillsByNormalized
     * @param  Collection<string, SkillAlias>  $aliasesByNormalized
     * @return Collection<int, CandidateSkill>
     */
    public static function deduplicateCandidateSkills(Collection $skills, Collection $skillsByNormalized, Collection $aliasesByNormalized): Collection
    {
        $seen = [];

        return $skills->filter(function (CandidateSkill $cs) use (&$seen, $skillsByNormalized, $aliasesByNormalized): bool {
            // Compute normalized identity as in findExistingCandidateSkill
            $normalized = null;
            if ($cs->skill !== null) {
                $normalized = self::normalize($cs->skill->normalized_name);
            } elseif ($cs->skill_id !== null) {
                $skill = $skillsByNormalized->first(fn (Skill $s) => $s->id === $cs->skill_id);
                $normalized = $skill ? self::normalize($skill->normalized_name) : self::normalize($cs->custom_skill_name ?? '');
            } else {
                $customNorm = self::normalize($cs->custom_skill_name ?? '');
                $alias = $aliasesByNormalized->get($customNorm);
                if ($alias !== null) {
                    $canonical = $skillsByNormalized->first(fn (Skill $s) => $s->id === $alias->skill_id);
                    if ($canonical === null) {
                        $canonical = Skill::find($alias->skill_id);
                    }
                    $normalized = $canonical !== null ? self::normalize($canonical->normalized_name) : $customNorm;
                } else {
                    $normalized = $customNorm;
                }
            }

            if ($normalized === '') {
                return true; // keep malformed but don't dedup
            }

            if (isset($seen[$normalized])) {
                return false;
            }
            $seen[$normalized] = true;

            return true;
        })->values();
    }

    /**
     * Tokenize normalized string for Jaccard/containment.
     *
     * @return list<string>
     */
    public static function tokens(string $value): array
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^a-z0-9]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts;
    }

    /**
     * Bounded similarity 0-1: max of similar_text, lev ratio, Jaccard, containment.
     * Used for possible-duplicate detection, not for scoring.
     */
    public static function textSimilarity(string $a, string $b): float
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === '' && $nb === '') {
            return 1.0;
        }

        if ($na === '' || $nb === '') {
            return 0.0;
        }

        if ($na === $nb) {
            return 1.0;
        }

        similar_text($na, $nb, $percent);
        $simPercent = $percent / 100;

        $lev = levenshtein($na, $nb);
        $maxLen = max(mb_strlen($na, 'UTF-8'), mb_strlen($nb, 'UTF-8'));
        $levRatio = 1 - ($lev / $maxLen);

        $tokensA = self::tokens($na);
        $tokensB = self::tokens($nb);

        $jaccard = 0.0;
        $containment = 0.0;

        if ($tokensA !== [] || $tokensB !== []) {
            $inter = count(array_intersect($tokensA, $tokensB));
            $union = count(array_unique(array_merge($tokensA, $tokensB)));
            $jaccard = $union > 0 ? $inter / $union : 0.0;

            $short = count($tokensA) < count($tokensB) ? $tokensA : $tokensB;
            $long = count($tokensA) < count($tokensB) ? $tokensB : $tokensA;
            $interShort = count(array_intersect($short, $long));
            $containment = count($short) > 0 ? $interShort / count($short) : 0.0;
        }

        // Substring containment via normalized string
        $substringContainment = 0.0;
        if (str_contains($na, $nb) || str_contains($nb, $na)) {
            $shortLen = min(mb_strlen($na, 'UTF-8'), mb_strlen($nb, 'UTF-8'));
            $longLen = max(mb_strlen($na, 'UTF-8'), mb_strlen($nb, 'UTF-8'));
            $substringContainment = $shortLen / $longLen;
            // Boost if shorter is at least 60% of longer and contained
            if ($substringContainment >= 0.6) {
                $substringContainment = max($substringContainment, 0.85);
            }
        }

        return max($simPercent, $levRatio, $jaccard, $containment, $substringContainment);
    }

    /**
     * Parse a date value (string/numeric) to Carbon or null.
     * Handles bare year, French months, and "present"/ongoing.
     */
    public static function parseDateValue(mixed $value): ?Carbon
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $lower = mb_strtolower($raw, 'UTF-8');

        if (str_contains($lower, 'présent')
            || str_contains($lower, 'present')
            || str_contains($lower, 'now')
            || str_contains($lower, 'ongoing')
            || str_contains($lower, 'current')
            || str_contains($lower, 'actuel')
            || $lower === 'en cours'
            || $lower === 'à ce jour') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $raw)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $raw.'-01-01')?->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $words = preg_split('/\s+/u', $raw) ?: [];
        $words = array_map(fn (string $w): string => self::FRENCH_MONTH_NAMES[mb_strtolower(rtrim($w, '.'), 'UTF-8')] ?? $w, $words);
        $raw = trim(implode(' ', $words));
        $raw = trim($raw, "., \t\n\r\0\x0B");

        $formats = [
            'Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'Y.m.d',
            'Y-m', 'Y/m', 'm/Y', 'n/Y',
            'F Y', 'M Y', 'F y', 'M y',
            'j F Y', 'd F Y', 'j M Y', 'd M Y', 'F j, Y', 'M j, Y', 'F d, Y', 'M d, Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed === null || mb_strtolower($parsed->format($format), 'UTF-8') !== mb_strtolower($raw, 'UTF-8')) {
                continue;
            }

            if (! str_contains($format, 'd') && ! str_contains($format, 'j')) {
                $parsed = $parsed->day(1);
            }

            return $parsed->startOfDay();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    public static function extractIntervalForIncoming(string $type, array $value): array
    {
        // Certification: single date
        if ($type === 'certification') {
            $date = self::parseDateValue($value['date'] ?? null);

            return ['start' => $date, 'end' => $date];
        }

        $start = self::parseDateValue($value['start_date'] ?? null);
        $isCurrent = ! empty($value['is_current']);
        $end = $isCurrent ? null : self::parseDateValue($value['end_date'] ?? null);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    public static function extractIntervalForProfileItem(ProfileItem $item): array
    {
        $start = $item->start_date instanceof Carbon ? $item->start_date->copy()->startOfDay() : null;
        $end = $item->end_date instanceof Carbon ? $item->end_date->copy()->startOfDay() : null;

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Whether two intervals are compatible (overlap or close).
     * Returns true if missing dates (cannot determine) or gap <= threshold.
     * Returns false if clearly separate (gap > threshold) — likely rehire.
     */
    public static function areDateIntervalsCompatible(?Carbon $aStart, ?Carbon $aEnd, ?Carbon $bStart, ?Carbon $bEnd): bool
    {
        if ($aStart === null || $bStart === null) {
            return true;
        }

        $aEndEff = $aEnd ?? Carbon::now()->addYears(10);
        $bEndEff = $bEnd ?? Carbon::now()->addYears(10);

        $maxStart = $aStart->greaterThan($bStart) ? $aStart : $bStart;
        $minEnd = $aEndEff->lessThan($bEndEff) ? $aEndEff : $bEndEff;

        if ($maxStart->lessThanOrEqualTo($minEnd)) {
            return true;
        }

        // No overlap: compute gap
        $gap = 0;
        if ($aEnd !== null && $aEnd->lessThan($bStart)) {
            $gap = $aEnd->diffInDays($bStart);
        } elseif ($bEnd !== null && $bEnd->lessThan($aStart)) {
            $gap = $bEnd->diffInDays($aStart);
        } else {
            // One is ongoing but no overlap due to far future? Already handled
            $gap = $maxStart->diffInDays($minEnd);
        }

        return $gap <= self::DATE_GAP_THRESHOLD_DAYS;
    }

    /**
     * Find possible duplicate (similar but not exact) for profile item.
     * Returns null if exact duplicate or no strong similarity or dates clearly separate.
     *
     * @param  Collection<int, ProfileItem>  $items
     * @return array{existing: ProfileItem, titleScore: float, orgScore: float, similarity: float, reason: string}|null
     */
    public static function findPossibleDuplicateProfileItem(Collection $items, string $type, array $value): ?array
    {
        // Exact already handled elsewhere; if exact exists, not possible
        if (self::findExistingProfileItem($items, $type, $value) !== null) {
            return null;
        }

        $candidates = $items->filter(fn (ProfileItem $it) => $it->type->value === $type);
        if ($candidates->isEmpty()) {
            return null;
        }

        $incomingTitle = self::extractTitle($type, $value);
        $incomingOrg = self::extractOrganization($type, $value);
        $incomingTitleNorm = self::normalize($incomingTitle);
        $incomingOrgNorm = self::normalize($incomingOrg);

        if ($incomingTitleNorm === '' && $incomingOrgNorm === '') {
            return null;
        }

        $incomingInterval = self::extractIntervalForIncoming($type, $value);

        $best = null;
        $bestCombined = 0.0;
        $bestReason = '';
        $bestTitleScore = 0.0;
        $bestOrgScore = 0.0;

        foreach ($candidates as $item) {
            $titleSim = self::textSimilarity($incomingTitle, $item->title ?? '');
            $orgSim = self::textSimilarity($incomingOrg, $item->organization ?? '');

            $bothOrgEmpty = $incomingOrgNorm === '' && self::normalize($item->organization ?? '') === '';
            $oneOrgEmpty = ($incomingOrgNorm === '') !== (self::normalize($item->organization ?? '') === '');

            $isPossible = false;
            $reason = '';

            if ($bothOrgEmpty) {
                if ($titleSim >= self::SIMILARITY_THRESHOLD_STRONG) {
                    $isPossible = true;
                    $reason = 'title similarity '.round($titleSim, 2);
                }
            } elseif ($oneOrgEmpty) {
                if ($titleSim >= self::SIMILARITY_THRESHOLD_VERY_STRONG) {
                    $isPossible = true;
                    $reason = 'title very similar ('.round($titleSim, 2).') but organization missing';
                }
            } else {
                if ($titleSim >= self::SIMILARITY_THRESHOLD_STRONG && $orgSim >= self::ORG_THRESHOLD_STRONG) {
                    $isPossible = true;
                    $reason = 'title '.round($titleSim, 2).' org '.round($orgSim, 2);
                } elseif ($titleSim >= self::SIMILARITY_THRESHOLD_VERY_STRONG && $orgSim >= 0.75) {
                    $isPossible = true;
                    $reason = 'title very similar '.round($titleSim, 2).' org '.round($orgSim, 2);
                } elseif ($orgSim >= self::ORG_THRESHOLD_VERY_STRONG && $titleSim >= 0.80) {
                    $isPossible = true;
                    $reason = 'org very similar '.round($orgSim, 2).' title '.round($titleSim, 2);
                } elseif ($titleSim >= 0.80 && $orgSim >= 0.75) {
                    $isPossible = true;
                    $reason = 'title '.round($titleSim, 2).' org '.round($orgSim, 2);
                }
            }

            if (! $isPossible) {
                continue;
            }

            $existingInterval = self::extractIntervalForProfileItem($item);
            $compatible = self::areDateIntervalsCompatible(
                $incomingInterval['start'],
                $incomingInterval['end'],
                $existingInterval['start'],
                $existingInterval['end'],
            );

            if (! $compatible) {
                continue;
            }

            $combined = 0.0;
            if ($bothOrgEmpty) {
                $combined = $titleSim;
            } elseif ($oneOrgEmpty) {
                $combined = $titleSim * 0.9;
            } else {
                $combined = $titleSim * 0.6 + $orgSim * 0.4;
            }

            if ($best === null || $combined > $bestCombined) {
                $best = $item;
                $bestCombined = $combined;
                $bestReason = $reason;
                $bestTitleScore = $titleSim;
                $bestOrgScore = $orgSim;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'existing' => $best,
            'titleScore' => $bestTitleScore,
            'orgScore' => $bestOrgScore,
            'similarity' => $bestCombined,
            'reason' => $bestReason,
        ];
    }
}

<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Models\CandidateProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeduplicateLanguagesAction
{
    public function __construct(
        private ProfileCompletionService $completionService,
    ) {}

    /**
     * Deduplicate languages for a profile. Keeps best proficiency per normalized language.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(CandidateProfile $profile): array
    {
        return DB::transaction(function () use ($profile) {
            $languages = $profile->languages ?? [];

            if (empty($languages)) {
                return [];
            }

            $byNorm = [];
            foreach ($languages as $entry) {
                $norm = ProfileIdentityService::languageKey((string) $entry['language']);
                if ($norm === '') {
                    continue;
                }
                $byNorm[$norm][] = $entry;
            }

            $deduped = [];
            $changed = false;

            foreach ($byNorm as $norm => $cluster) {
                if (count($cluster) === 1) {
                    $deduped[] = $cluster[0];

                    continue;
                }

                $changed = true;
                // Keep best proficiency
                $best = $this->pickBestProficiency($cluster);
                $deduped[] = $best;
            }

            // Preserve languages that had empty norm (malformed) as is
            foreach ($languages as $entry) {
                $norm = ProfileIdentityService::languageKey((string) $entry['language']);
                if ($norm === '') {
                    $deduped[] = $entry;
                }
            }

            if ($changed) {
                $profile->update(['languages' => $deduped]);
                $profile->touch();
                $this->completionService->persist($profile->fresh());
            }

            return $deduped;
        });
    }

    /**
     * For merging via explicit keep/duplicate UI, we also support targeted merge.
     *
     * @param  string  $keepLanguage  The language string to keep (original case)
     * @param  list<string>  $duplicateLanguages  Normalized duplicates to remove (raw strings)
     */
    public function mergeSpecific(CandidateProfile $profile, string $keepLanguage, array $duplicateLanguages): array
    {
        return DB::transaction(function () use ($profile, $keepLanguage, $duplicateLanguages) {
            $languages = $profile->languages ?? [];
            $keepNorm = ProfileIdentityService::languageKey($keepLanguage);

            if ($keepNorm === '') {
                throw ValidationException::withMessages(['keepLanguage' => 'Invalid keep language.']);
            }

            $keepEntry = null;
            $toKeep = [];

            foreach ($languages as $entry) {
                $norm = ProfileIdentityService::languageKey((string) $entry['language']);
                if ($norm === $keepNorm) {
                    if ($keepEntry === null) {
                        $keepEntry = $entry;
                    } else {
                        // Choose best proficiency among keep group
                        $keepEntry = $this->pickBestProficiency([$keepEntry, $entry]);
                    }
                }
            }

            if ($keepEntry === null) {
                throw ValidationException::withMessages(['keepLanguage' => 'Keep language not found.']);
            }

            // Validate duplicates are indeed same norm
            foreach ($duplicateLanguages as $dupLang) {
                $dupNorm = ProfileIdentityService::languageKey($dupLang);
                if ($dupNorm !== $keepNorm) {
                    throw ValidationException::withMessages(['duplicateLanguages' => "Duplicate [$dupLang] not equivalent to keep [$keepLanguage]."]);
                }
            }

            // Rebuild deduped list: keep one best, remove others with same norm
            $byNorm = [];
            foreach ($languages as $entry) {
                $norm = ProfileIdentityService::languageKey((string) $entry['language']);
                $byNorm[$norm][] = $entry;
            }

            $newLanguages = [];
            foreach ($byNorm as $norm => $cluster) {
                if ($norm === $keepNorm) {
                    $newLanguages[] = $keepEntry;
                } else {
                    // Keep as is (should be single)
                    foreach ($cluster as $c) {
                        $newLanguages[] = $c;
                    }
                }
            }

            // If keepLanguage's original case differs from keepEntry, preserve keepLanguage case
            // But we already chose best proficiency, which may have different language case; we should keep the keepLanguage's case
            foreach ($newLanguages as &$entry) {
                if (ProfileIdentityService::languageKey((string) $entry['language']) === $keepNorm) {
                    $entry['language'] = $keepLanguage;
                    break;
                }
            }

            $profile->update(['languages' => $newLanguages]);
            $profile->touch();
            $this->completionService->persist($profile->fresh());

            return $newLanguages;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $cluster
     * @return array<string, mixed>
     */
    private function pickBestProficiency(array $cluster): array
    {
        $rank = [
            'native' => 5,
            'fluent' => 4,
            'advanced' => 3,
            'intermediate' => 2,
            'basic' => 1,
            'beginner' => 1,
        ];

        $best = $cluster[0];
        $bestRank = $this->proficiencyRank((string) ($best['proficiency'] ?? ''), $rank);

        foreach (array_slice($cluster, 1) as $entry) {
            $r = $this->proficiencyRank((string) ($entry['proficiency'] ?? ''), $rank);
            if ($r > $bestRank) {
                $bestRank = $r;
                $best = $entry;
            }
        }

        // Preserve the language string with best case? Keep first encountered language case with best proficiency
        return $best;
    }

    private function proficiencyRank(string $proficiency, array $rank): int
    {
        $norm = strtolower(trim($proficiency));

        return isset($rank[$norm]) ? $rank[$norm] : 0;
    }
}

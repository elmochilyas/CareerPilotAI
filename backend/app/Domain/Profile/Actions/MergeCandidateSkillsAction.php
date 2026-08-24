<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeCandidateSkillsAction
{
    public function __construct(
        private ProfileCompletionService $completionService,
    ) {}

    /**
     * @param  list<int>  $duplicateIds  IDs to delete (keep is not in this list)
     */
    public function execute(CandidateProfile $profile, int $keepId, array $duplicateIds): CandidateSkill
    {
        if (empty($duplicateIds)) {
            throw ValidationException::withMessages(['duplicate_ids' => 'No duplicates provided.']);
        }

        if (in_array($keepId, $duplicateIds, true)) {
            throw ValidationException::withMessages(['keep_id' => 'Keep ID cannot be in duplicate list.']);
        }

        return DB::transaction(function () use ($profile, $keepId, $duplicateIds) {
            $keep = CandidateSkill::where('id', $keepId)->lockForUpdate()->with('skill')->firstOrFail();

            if ((int) $keep->candidate_profile_id !== (int) $profile->id) {
                throw ValidationException::withMessages(['keep_id' => 'Keep skill does not belong to this profile.']);
            }

            $duplicates = CandidateSkill::whereIn('id', $duplicateIds)->lockForUpdate()->with('skill')->get();

            if ($duplicates->count() !== count($duplicateIds)) {
                throw ValidationException::withMessages(['duplicate_ids' => 'Some duplicate skills not found.']);
            }

            $allSkills = Skill::all();
            $allAliases = SkillAlias::all();
            $maps = ProfileIdentityService::buildSkillLookupMaps($allSkills, $allAliases);

            // Validate each duplicate is exact equivalent of keep (same normalized identity)
            $keepNorm = $this->skillIdentity($keep, $maps);

            foreach ($duplicates as $dup) {
                if ((int) $dup->candidate_profile_id !== (int) $profile->id) {
                    throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} does not belong to this profile."]);
                }

                $dupNorm = $this->skillIdentity($dup, $maps);

                if ($keepNorm !== $dupNorm) {
                    throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} is not equivalent to keep (keep norm [$keepNorm] vs dup norm [$dupNorm])."]);
                }
            }

            // Prefer canonical over custom: if keep is custom but duplicate is canonical, swap
            $keepIsCanonical = $keep->skill_id !== null;
            $canonicalDup = $duplicates->first(fn (CandidateSkill $s) => $s->skill_id !== null);

            if (! $keepIsCanonical && $canonicalDup) {
                // Swap: keep the canonical one, delete the custom keep
                $newKeep = $canonicalDup;
                $newDupIds = array_filter(array_merge([$keepId], $duplicateIds), fn ($id) => $id !== $newKeep->id);
                // Re-fetch to keep transaction consistent
                $keep = CandidateSkill::where('id', $newKeep->id)->lockForUpdate()->with('skill')->firstOrFail();
                $duplicates = CandidateSkill::whereIn('id', $newDupIds)->lockForUpdate()->with('skill')->get();
                $duplicateIds = array_values($newDupIds);
                $keepId = $newKeep->id;
            }

            // Merge
            $merged = $this->mergeSkills($keep, $duplicates, $maps);

            $keep->update($merged);

            CandidateSkill::whereIn('id', $duplicateIds)->delete();

            $profile->touch();
            $this->completionService->persist($profile->fresh());

            return $keep->fresh();
        });
    }

    private function skillIdentity(CandidateSkill $cs, array $maps): string
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

    /**
     * @param  Collection<int, CandidateSkill>  $duplicates
     * @return array<string, mixed>
     */
    private function mergeSkills(CandidateSkill $keep, $duplicates, array $maps): array
    {
        $all = $duplicates->prepend($keep);

        // Merge evidence
        $evidence = [];
        foreach ($all as $cs) {
            $ev = $cs->evidence ?? [];
            foreach ($ev as $e) {
                $evidence[] = $e;
            }
        }
        // Deduplicate evidence by json
        $seen = [];
        $dedupedEvidence = [];
        foreach ($evidence as $e) {
            $key = json_encode($e);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $dedupedEvidence[] = $e;
            }
        }

        // Proficiency: strongest (expert > advanced > intermediate > elementary > beginner)
        $rank = ['beginner' => 1, 'elementary' => 2, 'intermediate' => 3, 'advanced' => 4, 'expert' => 5];
        $bestLevel = $keep->proficiency_level?->value;
        $bestRank = $bestLevel !== null ? $rank[$bestLevel] : 0;

        foreach ($duplicates as $dup) {
            $lvl = $dup->proficiency_level?->value;
            $r = $lvl !== null ? $rank[$lvl] : 0;
            if ($r > $bestRank) {
                $bestRank = $r;
                $bestLevel = $lvl;
            }
        }

        // Years experience: max if safe (both have values)
        $years = $keep->years_experience;
        foreach ($duplicates as $dup) {
            if ($dup->years_experience !== null) {
                if ($years === null || (float) $dup->years_experience > (float) $years) {
                    $years = $dup->years_experience;
                }
            }
        }

        // Last used at: most recent
        $lastUsed = $keep->last_used_at;
        foreach ($duplicates as $dup) {
            if ($dup->last_used_at !== null) {
                if ($lastUsed === null || $dup->last_used_at->greaterThan($lastUsed)) {
                    $lastUsed = $dup->last_used_at;
                }
            }
        }

        // State: prefer verified > claimed > learning > archived > rejected
        $stateRank = ['verified' => 5, 'claimed' => 4, 'learning' => 3, 'archived' => 2, 'rejected' => 1];
        $bestState = $keep->state->value;
        $bestStateRank = $stateRank[$bestState];
        foreach ($duplicates as $dup) {
            $s = $dup->state->value;
            $r = $stateRank[$s];
            if ($r > $bestStateRank) {
                $bestStateRank = $r;
                $bestState = $s;
            }
        }

        return [
            'evidence' => $dedupedEvidence,
            'proficiency_level' => $bestLevel,
            'years_experience' => $years,
            'last_used_at' => $lastUsed?->toDateString(),
            'state' => $bestState,
        ];
    }
}

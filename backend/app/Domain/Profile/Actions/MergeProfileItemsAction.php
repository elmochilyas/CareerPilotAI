<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MergeProfileItemsAction
{
    public function __construct(
        private ProfileCompletionService $completionService,
    ) {}

    /**
     * Merge duplicate profile items, keeping one and deleting the rest.
     *
     * @param  CandidateProfile  $profile  Owner profile (must match items)
     * @param  int  $keepId  ID of item to keep
     * @param  list<int>  $duplicateIds  IDs to merge and delete
     * @param  bool  $allowPossible  Whether to allow merging possible (fuzzy) duplicates
     * @return ProfileItem The kept item (fresh)
     *
     * @throws ValidationException
     */
    public function execute(CandidateProfile $profile, int $keepId, array $duplicateIds, bool $allowPossible = false): ProfileItem
    {
        if (empty($duplicateIds)) {
            throw ValidationException::withMessages(['duplicate_ids' => 'No duplicates provided.']);
        }

        if (in_array($keepId, $duplicateIds, true)) {
            throw ValidationException::withMessages(['keep_id' => 'Keep ID cannot be in duplicate list.']);
        }

        return DB::transaction(function () use ($profile, $keepId, $duplicateIds, $allowPossible) {
            $keep = ProfileItem::where('id', $keepId)->lockForUpdate()->firstOrFail();

            if ((int) $keep->candidate_profile_id !== (int) $profile->id) {
                throw ValidationException::withMessages(['keep_id' => 'Keep item does not belong to this profile.']);
            }

            $duplicates = ProfileItem::whereIn('id', $duplicateIds)->lockForUpdate()->get();

            if ($duplicates->count() !== count($duplicateIds)) {
                throw ValidationException::withMessages(['duplicate_ids' => 'Some duplicate items not found.']);
            }

            foreach ($duplicates as $dup) {
                if ((int) $dup->candidate_profile_id !== (int) $profile->id) {
                    throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} does not belong to this profile."]);
                }

                if ($dup->type->value !== $keep->type->value) {
                    throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} type mismatch."]);
                }

                // Verify compatibility via ProfileIdentityService
                $valueForDup = [
                    'title' => $dup->title,
                    'name' => $dup->title,
                    'degree' => $dup->title,
                    'organization' => $dup->organization,
                    'institution' => $dup->organization,
                    'issuer' => $dup->organization,
                    'start_date' => $dup->start_date?->toDateString(),
                    'end_date' => $dup->end_date?->toDateString(),
                    'is_current' => $dup->end_date === null && $dup->start_date !== null,
                ];

                $isExact = ProfileIdentityService::findExistingProfileItem(new Collection([$keep]), $keep->type->value, $valueForDup) !== null;

                if (! $isExact) {
                    $possible = ProfileIdentityService::findPossibleDuplicateProfileItem(new Collection([$keep]), $keep->type->value, $valueForDup);
                    if ($possible !== null && ! $allowPossible) {
                        throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} is only a possible duplicate (similarity {$possible['similarity']}, {$possible['reason']}). Explicit confirmation required."]);
                    }

                    if ($possible === null) {
                        throw ValidationException::withMessages(['duplicate_ids' => "Duplicate {$dup->id} is not compatible with keep item (different identity or rehire)."]);
                    }
                }
            }

            // Merge conservatively
            $merged = $this->mergeItems($keep, $duplicates);

            $keep->update($merged);

            // Delete duplicates
            ProfileItem::whereIn('id', $duplicateIds)->delete();

            // Profile consistency
            $profile->touch();
            $this->completionService->persist($profile->fresh());

            return $keep->fresh();
        });
    }

    /**
     * @param  Collection<int, ProfileItem>  $duplicates
     * @return array<string, mixed>
     */
    private function mergeItems(ProfileItem $keep, $duplicates): array
    {
        $keepData = $keep->toArray();
        $all = $duplicates->prepend($keep);

        // Title/organization/location: preserve non-empty from keep, else first non-empty from duplicates
        $title = $keep->title;
        $organization = $keep->organization;
        $location = $keep->location;

        foreach ($duplicates as $dup) {
            if (empty(trim((string) $title)) && ! empty(trim((string) $dup->title))) {
                $title = $dup->title;
            }
            if (empty(trim((string) $organization)) && ! empty(trim((string) $dup->organization))) {
                $organization = $dup->organization;
            }
            if (empty(trim((string) $location)) && ! empty(trim((string) $dup->location))) {
                $location = $dup->location;
            }
        }

        // Earliest start_date
        $startDates = $all->pluck('start_date')->filter()->map(fn ($d) => $d instanceof Carbon ? $d : Carbon::parse($d));
        $earliestStart = $startDates->isNotEmpty() ? $startDates->sortBy(fn ($d) => $d->timestamp)->first()?->toDateString() : $keep->start_date?->toDateString();

        // Latest end / current
        $hasCurrent = $all->contains(fn (ProfileItem $it) => $it->end_date === null && $it->start_date !== null);
        $endDate = null;
        if (! $hasCurrent) {
            $endDates = $all->pluck('end_date')->filter()->map(fn ($d) => $d instanceof Carbon ? $d : Carbon::parse($d));
            $latestEnd = $endDates->isNotEmpty() ? $endDates->sortByDesc(fn ($d) => $d->timestamp)->first()?->toDateString() : null;
            $endDate = $latestEnd;
        }

        // Richest description (longest non-empty)
        $descriptions = $all->pluck('description')->filter(fn ($d) => ! empty(trim((string) $d)));
        $richestDesc = $descriptions->sortByDesc(fn ($d) => mb_strlen((string) $d))->first() ?? $keep->description;

        // Metadata merge
        $mergedMetadata = $keep->metadata ?? [];
        foreach ($duplicates as $dup) {
            $dupMeta = $dup->metadata ?? [];
            foreach ($dupMeta as $k => $v) {
                if (! isset($mergedMetadata[$k]) || empty($mergedMetadata[$k])) {
                    $mergedMetadata[$k] = $v;
                } elseif (is_array($v) && is_array($mergedMetadata[$k])) {
                    $mergedMetadata[$k] = array_values(array_unique(array_merge($mergedMetadata[$k], $v)));
                }
            }
        }

        if (empty($mergedMetadata)) {
            $mergedMetadata = null;
        }

        return [
            'title' => $title,
            'organization' => $organization,
            'location' => $location,
            'start_date' => $earliestStart,
            'end_date' => $endDate,
            'description' => $richestDesc,
            'metadata' => $mergedMetadata,
        ];
    }
}

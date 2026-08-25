<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Data\ProfileItemData;
use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Support\ProblemDetails\ProblemDetailsException;
use Illuminate\Support\Facades\DB;

class UpdateProfileItemAction
{
    public function __construct(private ProfileCompletionService $completion) {}

    public function execute(ProfileItem $item, ProfileItemData $data): ProfileItem
    {
        return DB::transaction(function () use ($item, $data): ProfileItem {
            $item->refresh();
            if ($data->updatedAt !== null && ! $item->updated_at->equalTo($data->updatedAt)) {
                throw new ProblemDetailsException(409, 'The profile item changed since it was loaded.', 'profile_conflict');
            }

            // Lock profile to prevent concurrent duplicate creation
            $profile = CandidateProfile::where('id', $item->candidate_profile_id)->lockForUpdate()->firstOrFail();
            $profile->load('items');

            // Check if updated values would create an exact duplicate with another item
            $type = $data->attributes['type'] ?? $item->type->value;
            $value = [
                'title' => $data->attributes['title'] ?? $item->title,
                'name' => $data->attributes['title'] ?? $item->title,
                'degree' => $data->attributes['title'] ?? $item->title,
                'organization' => $data->attributes['organization'] ?? $item->organization,
                'institution' => $data->attributes['organization'] ?? $item->organization,
                'issuer' => $data->attributes['organization'] ?? $item->organization,
                'start_date' => $data->attributes['start_date'] ?? ($item->start_date?->toDateString()),
                'end_date' => $data->attributes['end_date'] ?? ($item->end_date?->toDateString()),
                'is_current' => $data->attributes['is_current'] ?? ($item->end_date === null && $item->start_date !== null),
            ];

            $others = $profile->items->filter(fn (ProfileItem $it) => $it->id !== $item->id);
            $existing = ProfileIdentityService::findExistingProfileItem($others, $type, $value);

            if ($existing) {
                throw new ConflictException(
                    'This entry already exists in your profile.',
                    'profile_item_duplicate',
                );
            }

            $attrs = $data->attributes;
            unset($attrs['is_current']);
            if (! empty($data->attributes['is_current'])) {
                $attrs['end_date'] = null;
            }
            $item->fill($attrs)->save();
            $profile->load('items');
            $this->completion->persist($profile);

            return $item->fresh();
        });
    }
}

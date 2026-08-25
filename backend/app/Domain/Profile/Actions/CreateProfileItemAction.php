<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Data\ProfileItemData;
use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProfileItemAction
{
    public function __construct(private ProfileCompletionService $completion) {}

    public function execute(User $user, ProfileItemData $data): ProfileItem
    {
        return DB::transaction(function () use ($user, $data): ProfileItem {
            // Lock profile row to prevent concurrent duplicate creation
            $profile = CandidateProfile::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $profile) {
                $profile = new CandidateProfile;
                $profile->user_id = $user->id;
                $profile->profile_completion = '0';
                $profile->save();
                // Re-lock the newly created row
                $profile = CandidateProfile::where('id', $profile->id)->lockForUpdate()->first();
            }

            $profile->loadMissing('items');

            // Check for exact normalized duplicate against fresh locked state
            $type = $data->attributes['type'];
            $value = [
                'title' => $data->attributes['title'] ?? null,
                'name' => $data->attributes['title'] ?? null,
                'degree' => $data->attributes['title'] ?? null,
                'organization' => $data->attributes['organization'] ?? null,
                'institution' => $data->attributes['organization'] ?? null,
                'issuer' => $data->attributes['organization'] ?? null,
                'start_date' => $data->attributes['start_date'] ?? null,
                'end_date' => $data->attributes['end_date'] ?? null,
                'is_current' => $data->attributes['is_current'] ?? false,
            ];

            $existing = ProfileIdentityService::findExistingProfileItem($profile->items, $type, $value);

            if ($existing) {
                throw new ConflictException(
                    'This entry already exists in your profile.',
                    'profile_item_duplicate',
                );
            }

            $order = (int) $profile->items()->where('type', $type)->max('display_order') + 1;
            $attrs = $data->attributes;
            unset($attrs['is_current']);
            if (! empty($data->attributes['is_current'])) {
                $attrs['end_date'] = null;
            }
            $item = new ProfileItem($attrs + ['display_order' => $order]);
            $profile->items()->save($item);
            $profile->load('items');
            $this->completion->persist($profile);

            return $item;
        });
    }
}

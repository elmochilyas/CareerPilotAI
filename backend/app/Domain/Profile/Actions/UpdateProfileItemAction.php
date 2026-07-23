<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Data\ProfileItemData;
use App\Domain\Profile\Services\ProfileCompletionService;
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
            $attrs = $data->attributes;
            unset($attrs['is_current']);
            if (! empty($data->attributes['is_current'])) {
                $attrs['end_date'] = null;
            }
            $item->fill($attrs)->save();
            $profile = $item->candidateProfile;
            $profile->load('items');
            $this->completion->persist($profile);

            return $item->fresh();
        });
    }
}

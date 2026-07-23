<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Data\ProfileItemData;
use App\Domain\Profile\Services\ProfileCompletionService;
use App\Models\ProfileItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProfileItemAction
{
    public function __construct(private ProfileCompletionService $completion) {}

    public function execute(User $user, ProfileItemData $data): ProfileItem
    {
        return DB::transaction(function () use ($user, $data): ProfileItem {
            $profile = $user->candidateProfile()->firstOrCreate([], ['profile_completion' => 0]);
            $order = (int) $profile->items()->where('type', $data->attributes['type'])->max('display_order') + 1;
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

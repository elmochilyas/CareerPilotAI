<?php

namespace App\Domain\Profile\Actions;

use App\Models\ProfileItem;
use App\Models\User;
use App\Support\ProblemDetails\ProblemDetailsException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReorderProfileItemsAction
{
    /** @param list<int> $itemIds @return Collection<int, ProfileItem> */
    public function execute(User $user, string $type, array $itemIds): Collection
    {
        return DB::transaction(function () use ($user, $type, $itemIds): Collection {
            $profile = $user->candidateProfile;
            $items = $profile?->items()->where('type', $type)->lockForUpdate()->get() ?? collect();
            $ownedIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $requestedIds = collect($itemIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($ownedIds !== $requestedIds) {
                throw new ProblemDetailsException(422, 'The reorder list must contain every owned item of the selected type exactly once.', 'profile_item_reorder_invalid', ['item_ids' => ['Invalid reorder list.']]);
            }
            foreach ($itemIds as $order => $id) {
                $items->firstWhere('id', $id)?->update(['display_order' => $order]);
            }

            return $profile->items()->where('type', $type)->orderBy('display_order')->get();
        });
    }
}

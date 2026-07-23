<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Services\ProfileCompletionService;
use App\Models\ProfileItem;
use Illuminate\Support\Facades\DB;

class DeleteProfileItemAction
{
    public function __construct(private ProfileCompletionService $completion) {}

    public function execute(ProfileItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $profile = $item->candidateProfile;
            $type = $item->type;
            $item->delete();
            $profile->items()->where('type', $type)->orderBy('display_order')->get()->each(fn (ProfileItem $remaining, int $index) => $remaining->update(['display_order' => $index]));
            $profile->load('items');
            $this->completion->persist($profile);
        });
    }
}

<?php

namespace App\Domain\Profile\Actions;

use App\Domain\Profile\Data\ProfileData;
use App\Domain\Profile\Services\ProfileCompletionService;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Models\CandidateProfile;
use App\Models\User;
use App\Support\ProblemDetails\ProblemDetailsException;
use Illuminate\Support\Facades\DB;

class UpdateProfileAction
{
    public function __construct(private ProfileCompletionService $completion) {}

    public function execute(User $user, ProfileData $data): CandidateProfile
    {
        return DB::transaction(function () use ($user, $data): CandidateProfile {
            $profile = $user->candidateProfile()->lockForUpdate()->first();
            if ($profile && $data->updatedAt !== null && ! $profile->updated_at->equalTo($data->updatedAt)) {
                throw new ProblemDetailsException(409, 'The profile changed since it was loaded.', 'profile_conflict');
            }
            $profile ??= new CandidateProfile;
            $profile->user_id = $user->id;

            $attributes = $data->attributes;

            // Deduplicate languages via normalized identity, keep best proficiency
            if (array_key_exists('languages', $attributes) && is_array($attributes['languages'])) {
                $attributes['languages'] = ProfileIdentityService::deduplicateLanguages($attributes['languages']);
                // Re-index to ensure JSON array
                $attributes['languages'] = array_values($attributes['languages']);
            }

            $profile->fill($attributes)->save();
            $profile->load('items');
            $this->completion->persist($profile);

            return $profile->fresh('items');
        });
    }
}

<?php

namespace App\Domain\Profile\Actions;

use App\Models\CandidateProfile;
use App\Models\User;

class ShowProfileAction
{
    public function execute(User $user): CandidateProfile
    {
        return $user->candidateProfile()->with('items')->first()
            ?? tap(new CandidateProfile, function ($profile) use ($user) {
                $profile->user_id = $user->id;
                $profile->profile_completion = '0';
            });
    }
}

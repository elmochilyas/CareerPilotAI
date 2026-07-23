<?php

namespace App\Domain\Profile\Actions;

use App\Models\CandidateProfile;
use App\Models\User;

class ShowProfileAction
{
    public function execute(User $user): CandidateProfile
    {
        return $user->candidateProfile()->with('items')->first()
            ?? new CandidateProfile(['user_id' => $user->id, 'profile_completion' => 0]);
    }
}

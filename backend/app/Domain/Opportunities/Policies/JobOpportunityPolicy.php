<?php

namespace App\Domain\Opportunities\Policies;

use App\Models\JobOpportunity;
use App\Models\User;

class JobOpportunityPolicy
{
    public function view(User $user, JobOpportunity $opportunity): bool
    {
        return $opportunity->candidateProfile->user_id === $user->id;
    }
}

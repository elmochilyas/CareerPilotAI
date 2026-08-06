<?php

namespace App\Domain\Matching\Policies;

use App\Models\MatchAnalysis;
use App\Models\User;

class MatchAnalysisPolicy
{
    public function view(User $user, MatchAnalysis $analysis): bool
    {
        $analysis->loadMissing('candidateProfile');

        return $analysis->candidateProfile->user_id === $user->id;
    }

    public function recalculate(User $user, MatchAnalysis $analysis): bool
    {
        return $this->view($user, $analysis);
    }
}

<?php

namespace App\Domain\Clarification\Policies;

use App\Models\ClarificationAnswer;
use App\Models\User;

class ClarificationAnswerPolicy
{
    public function view(User $user, ClarificationAnswer $answer): bool
    {
        $answer->loadMissing('question.matchAnalysis.candidateProfile');

        return $answer->question->matchAnalysis->candidateProfile->user_id === $user->id;
    }
}

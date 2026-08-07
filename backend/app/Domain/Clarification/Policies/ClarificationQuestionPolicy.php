<?php

namespace App\Domain\Clarification\Policies;

use App\Models\ClarificationQuestion;
use App\Models\User;

class ClarificationQuestionPolicy
{
    public function view(User $user, ClarificationQuestion $question): bool
    {
        return $this->ownedBy($user, $question);
    }

    public function answer(User $user, ClarificationQuestion $question): bool
    {
        return $this->ownedBy($user, $question);
    }

    public function review(User $user, ClarificationQuestion $question): bool
    {
        return $this->ownedBy($user, $question);
    }

    public function skip(User $user, ClarificationQuestion $question): bool
    {
        return $this->ownedBy($user, $question);
    }

    private function ownedBy(User $user, ClarificationQuestion $question): bool
    {
        $question->loadMissing('matchAnalysis.candidateProfile');

        return $question->matchAnalysis->candidateProfile->user_id === $user->id;
    }
}

<?php

namespace App\Domain\Clarification\Policies;

use App\Models\ClarificationProposal;
use App\Models\User;

class ClarificationProposalPolicy
{
    public function view(User $user, ClarificationProposal $proposal): bool
    {
        $proposal->loadMissing('answer.question.matchAnalysis.candidateProfile');

        return $proposal->answer->question->matchAnalysis->candidateProfile->user_id === $user->id;
    }
}

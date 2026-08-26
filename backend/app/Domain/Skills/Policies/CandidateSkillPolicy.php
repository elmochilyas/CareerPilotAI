<?php

namespace App\Domain\Skills\Policies;

use App\Models\CandidateSkill;
use App\Models\User;

class CandidateSkillPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function update(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function archive(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function restore(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function delete(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function addEvidence(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function updateEvidence(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    public function removeEvidence(User $user, CandidateSkill $candidateSkill): bool
    {
        return $this->ownsProfile($user, $candidateSkill);
    }

    private function ownsProfile(User $user, CandidateSkill $candidateSkill): bool
    {
        return $candidateSkill->candidateProfile->user_id === $user->id;
    }
}

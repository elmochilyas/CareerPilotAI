<?php

namespace App\Domain\Skills\Actions;

use App\Models\CandidateProfile;
use App\Models\CandidateSkill;

class ShowCandidateSkillAction
{
    public function execute(CandidateProfile $profile, int $id): CandidateSkill
    {
        return CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($id);
    }
}

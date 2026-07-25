<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;

class DeleteCandidateSkillAction
{
    public function execute(CandidateProfile $profile, int $id): void
    {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)->findOrFail($id);

        if (! in_array($skill->state, [SkillState::Claimed, SkillState::Learning], true)) {
            throw new UnprocessableEntityException(
                'Only skills in claimed or learning state can be removed.',
                'candidate_skill_removal_forbidden'
            );
        }

        $skill->delete();
    }
}

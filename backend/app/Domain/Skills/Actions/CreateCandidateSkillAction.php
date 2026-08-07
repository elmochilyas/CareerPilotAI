<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Data\CandidateSkillData;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;

class CreateCandidateSkillAction
{
    public function execute(CandidateProfile $profile, CandidateSkillData $data): CandidateSkill
    {
        if ($data->skillId !== null) {
            $existing = CandidateSkill::where('candidate_profile_id', $profile->id)
                ->where('skill_id', $data->skillId)
                ->exists();

            if ($existing) {
                throw new ConflictException(
                    'This skill is already in your profile.',
                    'candidate_skill_duplicate'
                );
            }
        } elseif ($data->customSkillName !== null) {
            $existing = CandidateSkill::where('candidate_profile_id', $profile->id)
                ->whereNull('skill_id')
                ->where('custom_skill_name', $data->customSkillName)
                ->exists();

            if ($existing) {
                throw new ConflictException(
                    'This custom skill is already in your profile.',
                    'candidate_skill_duplicate'
                );
            }
        }

        $candidateSkill = CandidateSkill::create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $data->skillId,
            'custom_skill_name' => $data->customSkillName,
            'state' => $data->state,
            'proficiency_level' => $data->proficiencyLevel,
            'years_experience' => $data->yearsExperience,
            'last_used_at' => $data->lastUsedAt,
            'evidence' => $data->evidence,
        ]);

        $profile->touch();

        return $candidateSkill->load('skill.aliases');
    }
}

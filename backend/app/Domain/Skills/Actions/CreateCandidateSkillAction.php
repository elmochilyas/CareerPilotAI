<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Profile\Services\ProfileIdentityService;
use App\Domain\Skills\Data\CandidateSkillData;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Facades\DB;

class CreateCandidateSkillAction
{
    public function execute(CandidateProfile $profile, CandidateSkillData $data): CandidateSkill
    {
        return DB::transaction(function () use ($profile, $data): CandidateSkill {
            // Lock profile to prevent concurrent duplicate skill creation
            $lockedProfile = CandidateProfile::where('id', $profile->id)->lockForUpdate()->firstOrFail();
            $lockedProfile->load('candidateSkills.skill');

            $skillName = '';
            if ($data->skillId !== null) {
                $skill = Skill::find($data->skillId);
                $skillName = $skill !== null ? $skill->name : '';
            } else {
                $skillName = $data->customSkillName ?? '';
            }

            if ($skillName === '' && $data->skillId !== null) {
                $skill = Skill::find($data->skillId);
                $skillName = $skill !== null ? $skill->name : '';
            } elseif ($skillName === '' && $data->customSkillName !== null) {
                $skillName = $data->customSkillName;
            }

            if ($skillName !== '') {
                $maps = ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all());
                $existing = ProfileIdentityService::findExistingCandidateSkill(
                    $lockedProfile->candidateSkills,
                    $skillName,
                    $maps['skills'],
                    $maps['aliases']
                );

                if ($existing) {
                    throw new ConflictException(
                        'This skill already exists in your profile.',
                        'candidate_skill_duplicate'
                    );
                }
            }

            $candidateSkill = CandidateSkill::create([
                'candidate_profile_id' => $lockedProfile->id,
                'skill_id' => $data->skillId,
                'custom_skill_name' => $data->customSkillName,
                'state' => $data->state,
                'proficiency_level' => $data->proficiencyLevel,
                'years_experience' => $data->yearsExperience,
                'last_used_at' => $data->lastUsedAt,
                'evidence' => $data->evidence,
            ]);

            $lockedProfile->touch();

            return $candidateSkill->load('skill.aliases');
        });
    }
}

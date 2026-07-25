<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Enums\SkillState;
use App\Domain\Skills\Services\SkillStateTransitionService;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Carbon\CarbonImmutable;

class ArchiveCandidateSkillAction
{
    public function __construct(
        private readonly SkillStateTransitionService $transitionService,
    ) {}

    public function execute(CandidateProfile $profile, int $id, ?string $expectedUpdatedAt): CandidateSkill
    {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($id);

        if (! $this->transitionService->isTransitionAllowed($skill->state, SkillState::Archived)) {
            throw new UnprocessableEntityException(
                "Cannot archive skill in {$skill->state->value} state.",
                'skill_state_transition_invalid'
            );
        }

        $updateData = ['state' => SkillState::Archived];

        if ($expectedUpdatedAt !== null) {
            $updateData['updated_at'] = CarbonImmutable::now();
            $updated = CandidateSkill::where('id', $skill->id)
                ->where('candidate_profile_id', $profile->id)
                ->where('updated_at', CarbonImmutable::parse($expectedUpdatedAt))
                ->update($updateData);

            if ($updated === 0) {
                throw new ConflictException(
                    'This skill was modified by another request. Please refresh and try again.',
                    'candidate_skill_conflict'
                );
            }
        } else {
            $skill->update($updateData);
        }

        $skill->refresh();

        return $skill;
    }
}

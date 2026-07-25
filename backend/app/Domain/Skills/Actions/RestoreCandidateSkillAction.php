<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Enums\SkillState;
use App\Domain\Skills\Services\SkillStateTransitionService;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Carbon\CarbonImmutable;

class RestoreCandidateSkillAction
{
    public function __construct(
        private readonly SkillStateTransitionService $transitionService,
    ) {}

    public function execute(CandidateProfile $profile, int $id, SkillState $targetState, ?string $expectedUpdatedAt): CandidateSkill
    {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($id);

        if ($skill->state !== SkillState::Archived) {
            throw new UnprocessableEntityException(
                "Only archived skills can be restored. Current state: {$skill->state->value}.",
                'skill_state_transition_invalid'
            );
        }

        if (! $this->transitionService->isTransitionAllowed(SkillState::Archived, $targetState)) {
            throw new UnprocessableEntityException(
                "Cannot restore to {$targetState->value}.",
                'skill_state_transition_invalid'
            );
        }

        if ($this->transitionService->requiresEvidence(SkillState::Archived, $targetState)) {
            if (empty($skill->evidence)) {
                throw new UnprocessableEntityException(
                    'Evidence is required to restore to verified state.',
                    'skill_verification_requirements_not_met'
                );
            }
        }

        $updateData = ['state' => $targetState];

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

<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Enums\ProficiencyLevel;
use App\Domain\Skills\Enums\SkillState;
use App\Domain\Skills\Services\SkillStateTransitionService;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Carbon\CarbonImmutable;

class UpdateCandidateSkillAction
{
    public function __construct(
        private readonly SkillStateTransitionService $transitionService,
    ) {}

    public function execute(
        CandidateProfile $profile,
        int $id,
        ?SkillState $newState,
        ?ProficiencyLevel $proficiencyLevel,
        ?float $yearsExperience,
        ?string $lastUsedAt,
        ?string $expectedUpdatedAt,
    ): CandidateSkill {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($id);

        if ($newState !== null && $newState !== $skill->state) {
            if (! $this->transitionService->isTransitionAllowed($skill->state, $newState)) {
                throw new UnprocessableEntityException(
                    "Cannot transition from {$skill->state->value} to {$newState->value}.",
                    'skill_state_transition_invalid'
                );
            }

            if ($this->transitionService->requiresEvidence($skill->state, $newState)) {
                if (empty($skill->evidence)) {
                    throw new UnprocessableEntityException(
                        'Evidence is required to transition to verified state.',
                        'skill_verification_requirements_not_met'
                    );
                }
            }
        }

        $updateData = [];

        if ($newState !== null) {
            $updateData['state'] = $newState;
        }

        if ($proficiencyLevel !== null) {
            $updateData['proficiency_level'] = $proficiencyLevel;
        }

        if ($yearsExperience !== null) {
            $updateData['years_experience'] = $yearsExperience;
        }

        if ($lastUsedAt !== null) {
            $updateData['last_used_at'] = $lastUsedAt;
        }

        if (empty($updateData)) {
            return $skill;
        }

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

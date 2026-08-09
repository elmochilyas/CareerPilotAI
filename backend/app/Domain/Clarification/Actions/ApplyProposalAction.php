<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Clarification\Events\ProposalAccepted;
use App\Domain\Skills\Actions\AddEvidenceAction;
use App\Domain\Skills\Actions\CreateCandidateSkillAction;
use App\Domain\Skills\Actions\UpdateCandidateSkillAction;
use App\Domain\Skills\Data\CandidateSkillData;
use App\Domain\Skills\Data\EvidenceData;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Jobs\ObserveClarificationStalenessJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Support\RequestIdContext;
use Illuminate\Support\Facades\DB;

/**
 * Applies an explicitly accepted proposal as a trusted profile mutation.
 *
 * The whole mutation runs inside one database transaction through the trusted
 * `Skills` actions (never by direct model writes that bypass invariants). An
 * accepted proposal is immutable, so a second apply returns the accepted
 * proposal unchanged. The owning analysis is never re-scored here: a queued
 * observability job is dispatched after commit so staleness is observed without
 * recomputing inside the mutation transaction.
 */
final class ApplyProposalAction
{
    public function __construct(
        private readonly CreateCandidateSkillAction $createSkill,
        private readonly UpdateCandidateSkillAction $updateSkill,
        private readonly AddEvidenceAction $addEvidence,
    ) {}

    public function execute(ClarificationProposal $proposal): ClarificationProposal
    {
        return DB::transaction(function () use ($proposal): ClarificationProposal {
            $locked = ClarificationProposal::query()
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->firstOrFail();

            $answer = $locked->answer()->lockForUpdate()->firstOrFail();

            if ($locked->status === ClarificationProposalStatus::Accepted) {
                return $locked->load('answer');
            }

            if ($locked->status !== ClarificationProposalStatus::Proposed) {
                throw new ConflictException(
                    'This proposal can no longer be reviewed.',
                    'proposal_not_reviewable'
                );
            }

            if (! $answer->status->isOpen()) {
                throw new ConflictException(
                    'This answer is no longer reviewable.',
                    'answer_not_reviewable'
                );
            }

            $this->apply($answer, $locked);

            $locked->update(['status' => ClarificationProposalStatus::Accepted]);
            $answer->update([
                'status' => ClarificationAnswerStatus::Accepted,
                'proposal_id' => $locked->id,
            ]);

            DB::afterCommit(function () use ($locked, $answer): void {
                event(new ProposalAccepted(
                    proposal: $locked,
                    answer: $answer,
                    targetType: $locked->target_type,
                    targetId: $locked->target_id,
                    field: $locked->field,
                    beforeValue: $locked->before_value,
                    afterValue: $locked->after_value,
                    metadata: $this->metadataFor($locked, $answer),
                ));

                ObserveClarificationStalenessJob::dispatch(
                    $answer->question->match_analysis_id,
                    RequestIdContext::get(),
                )->afterCommit();
            });

            return $locked->load('answer');
        }, attempts: 3);
    }

    private function apply(ClarificationAnswer $answer, ClarificationProposal $proposal): void
    {
        if ($proposal->target_type !== ClarificationTargetType::CandidateSkill) {
            throw new UnprocessableEntityException(
                'This proposal does not support a trusted profile change.',
                'proposal_not_supported'
            );
        }

        $after = $proposal->after_value ?? [];

        $state = isset($after['state']) ? SkillState::tryFrom($after['state']) : null;

        if (isset($after['state']) && $state === null) {
            throw new UnprocessableEntityException(
                'This proposal has an unsupported target state.',
                'proposal_not_supported'
            );
        }

        $yearsExperience = array_key_exists('years_experience', $after)
            ? (float) $after['years_experience']
            : null;

        $answer->loadMissing('question.matchAnalysis');

        $profile = $answer->question->matchAnalysis->candidateProfile()->firstOrFail();

        if ($proposal->target_id !== null) {
            $this->applyToExistingSkill($profile, $answer, $proposal->target_id, $after, $state, $yearsExperience);

            return;
        }

        $this->applyToNewSkill($profile, $answer, $after, $state, $yearsExperience);
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function applyToExistingSkill(
        CandidateProfile $profile,
        ClarificationAnswer $answer,
        int $targetId,
        array $after,
        ?SkillState $state,
        ?float $yearsExperience,
    ): void {
        $skill = CandidateSkill::query()
            ->where('candidate_profile_id', $profile->id)
            ->whereKey($targetId)
            ->first();

        if ($skill === null) {
            throw new ConflictException(
                'The target skill no longer exists for this profile.',
                'proposal_target_missing'
            );
        }

        if ($state === SkillState::Verified) {
            $this->addEvidence($profile, $answer, $skill->id, $after);
        }

        if ($state !== null && $skill->state !== $state) {
            $this->updateSkill->execute(
                profile: $profile,
                id: $skill->id,
                newState: $state,
                proficiencyLevel: null,
                yearsExperience: $yearsExperience,
                lastUsedAt: null,
                expectedUpdatedAt: null,
            );

            return;
        }

        if ($yearsExperience !== null && ! $this->matchesYears($skill, $yearsExperience)) {
            $this->updateSkill->execute(
                profile: $profile,
                id: $skill->id,
                newState: null,
                proficiencyLevel: null,
                yearsExperience: $yearsExperience,
                lastUsedAt: null,
                expectedUpdatedAt: null,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function applyToNewSkill(
        CandidateProfile $profile,
        ClarificationAnswer $answer,
        array $after,
        ?SkillState $state,
        ?float $yearsExperience,
    ): void {
        if ($state === null || ! isset($after['skill_id'])) {
            throw new UnprocessableEntityException(
                'This proposal does not support creating a trusted skill.',
                'proposal_not_supported'
            );
        }

        $skillId = (int) $after['skill_id'];

        if ($state === SkillState::Verified) {
            $created = $this->createSkill->execute($profile, new CandidateSkillData(
                skillId: $skillId,
                customSkillName: null,
                state: SkillState::Claimed,
                proficiencyLevel: null,
                yearsExperience: null,
                lastUsedAt: null,
                evidence: null,
            ));

            $this->addEvidence($profile, $answer, $created->id, $after);

            $this->updateSkill->execute(
                profile: $profile,
                id: $created->id,
                newState: SkillState::Verified,
                proficiencyLevel: null,
                yearsExperience: null,
                lastUsedAt: null,
                expectedUpdatedAt: null,
            );

            return;
        }

        $this->createSkill->execute($profile, new CandidateSkillData(
            skillId: $skillId,
            customSkillName: null,
            state: $state,
            proficiencyLevel: null,
            yearsExperience: $yearsExperience,
            lastUsedAt: null,
            evidence: null,
        ));
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function addEvidence(CandidateProfile $profile, ClarificationAnswer $answer, int $skillId, array $after): void
    {
        $evidence = $after['evidence'][0] ?? null;

        if (! is_array($evidence) || ! isset($evidence['type'], $evidence['value'])) {
            throw new UnprocessableEntityException(
                'A verified skill requires evidence.',
                'invalid_evidence_url'
            );
        }

        $this->addEvidence->execute(
            $profile,
            $skillId,
            new EvidenceData(
                type: (string) $evidence['type'],
                value: (string) $evidence['value'],
                label: isset($evidence['label']) ? (string) $evidence['label'] : null,
                originAnswerId: $answer->id,
                originQuestionId: $answer->question_id,
            ),
        );
    }

    private function matchesYears(CandidateSkill $skill, float $yearsExperience): bool
    {
        return $skill->years_experience !== null
            && abs((float) $skill->years_experience - $yearsExperience) < 0.05;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(ClarificationProposal $proposal, ClarificationAnswer $answer): array
    {
        return [
            'answer_type' => $answer->answer_type->value,
            'acknowledged_no_evidence' => $answer->acknowledged_no_evidence,
        ];
    }
}

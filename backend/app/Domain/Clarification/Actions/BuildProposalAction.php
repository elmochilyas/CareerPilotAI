<?php

namespace App\Domain\Clarification\Actions;

use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationProposalField;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Skills\Enums\SkillState;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateSkill;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Models\JobOpportunitySkill;
use App\Models\MatchFinding;
use Illuminate\Support\Facades\DB;

/**
 * Derives the concrete proposal (target, field, before, after) from a pending
 * answer and its acknowledged-no-evidence state. The proposal is candidate
 * input, never trusted profile data: it only describes a proposed mutation and
 * nothing is applied until the candidate explicitly accepts it.
 *
 * Only skill findings produce proposals in this change. A finding that maps to
 * a candidate skill yields a state/evidence/years-experience proposal against
 * that skill (or a new skill when none exists). Findings with no skill target
 * fail safely and never produce a fabricated mutation.
 */
final class BuildProposalAction
{
    public function execute(ClarificationAnswer $answer): ClarificationProposal
    {
        if ($answer->proposal_id !== null) {
            return $answer->proposal;
        }

        if (! $answer->status->isOpen()) {
            throw new UnprocessableEntityException(
                'This answer can no longer produce a proposal.',
                'answer_not_reviewable',
            );
        }

        $answer->loadMissing('question.matchFinding');

        $finding = $answer->question->matchFinding;

        if ($finding === null || ! $this->isSkillFinding($finding)) {
            throw new UnprocessableEntityException(
                'This clarification question does not support a profile change.',
                'proposal_not_supported',
            );
        }

        return DB::transaction(function () use ($answer, $finding): ClarificationProposal {
            $locked = ClarificationAnswer::query()
                ->whereKey($answer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->proposal_id !== null) {
                return $locked->proposal;
            }

            $skill = $finding->matchedCandidateSkill;

            $targetId = $skill?->id;

            if ($skill === null) {
                $skillId = $this->resolveSkillId($finding);

                if ($skillId === null) {
                    throw new UnprocessableEntityException(
                        'This clarification question does not support a profile change.',
                        'proposal_not_supported',
                    );
                }
            } else {
                $skillId = null;
            }

            $before = $skill === null
                ? null
                : ['state' => $skill->state->value];

            $after = $this->deriveAfter($answer, $skillId, $skill);

            $proposal = ClarificationProposal::create([
                'answer_id' => $answer->id,
                'target_type' => ClarificationTargetType::CandidateSkill,
                'target_id' => $targetId,
                'field' => ClarificationProposalField::STATE,
                'before_value' => $before,
                'after_value' => $after,
                'status' => ClarificationProposalStatus::Proposed,
            ]);

            $locked->update(['proposal_id' => $proposal->id]);

            return $proposal;
        }, attempts: 3);
    }

    private function isSkillFinding(MatchFinding $finding): bool
    {
        return in_array($finding->category, [
            MatchCategory::RequiredSkills->value,
            MatchCategory::PreferredSkills->value,
        ], true);
    }

    /**
     * Resolves the normalized skill id behind a requirement sourced from the
     * opportunity skill list. Requirement text with no skill reference yields
     * null so no skill target is invented.
     */
    private function resolveSkillId(MatchFinding $finding): ?int
    {
        if ($finding->source_type !== RequirementSourceType::JobOpportunitySkill) {
            return null;
        }

        return JobOpportunitySkill::query()
            ->whereKey($finding->source_id)
            ->value('skill_id');
    }

    /**
     * Builds the deterministic after-value from the answer type and its
     * no-evidence acknowledgement. A plain "yes" without evidence and without
     * acknowledgement never verifies: it fails safely instead.
     *
     * @return array<string, mixed>
     */
    private function deriveAfter(ClarificationAnswer $answer, ?int $skillId, ?CandidateSkill $skill): array
    {
        if ($answer->answer_type === ClarificationAnswerType::Yes) {
            if ($answer->acknowledged_no_evidence) {
                return $this->withSkillReference([
                    'state' => SkillState::Claimed->value,
                    'acknowledged_no_evidence' => true,
                ], $skillId);
            }

            $evidence = trim((string) $answer->value);

            if ($evidence === '' || $evidence === 'yes') {
                throw new UnprocessableEntityException(
                    'A verified skill requires evidence or an explicit no-evidence acknowledgement.',
                    'invalid_evidence_url',
                );
            }

            return $this->withSkillReference([
                'state' => SkillState::Verified->value,
                'evidence' => [[
                    'type' => 'url',
                    'value' => $evidence,
                ]],
            ], $skillId);
        }

        if (in_array($answer->answer_type, [ClarificationAnswerType::No, ClarificationAnswerType::NoWithAck], true)) {
            return $this->withSkillReference([
                'state' => SkillState::Rejected->value,
                'acknowledged_no_evidence' => $answer->acknowledged_no_evidence,
            ], $skillId);
        }

        if ($answer->answer_type === ClarificationAnswerType::Number) {
            return $this->withSkillReference([
                'years_experience' => (float) $answer->value,
            ], $skillId);
        }

        throw new UnprocessableEntityException(
            'This answer type does not produce a supported profile change.',
            'proposal_not_supported',
        );
    }

    /**
     * Carries the normalized skill reference for a new skill so the apply step
     * can create it without inventing a skill.
     *
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function withSkillReference(array $after, ?int $skillId): array
    {
        if ($skillId !== null) {
            $after['skill_id'] = $skillId;
        }

        return $after;
    }
}

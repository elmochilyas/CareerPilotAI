<?php

namespace App\Domain\Skills\Actions;

use App\Domain\Skills\Data\EvidenceData;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ProfileItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class AddEvidenceAction
{
    public function execute(CandidateProfile $profile, int $candidateSkillId, EvidenceData $evidenceData, ?string $expectedUpdatedAt = null): CandidateSkill
    {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($candidateSkillId);

        $evidence = $skill->evidence ?? [];

        $entry = [
            'key' => (string) Str::uuid(),
            'type' => $evidenceData->type,
            'value' => $evidenceData->value,
            'label' => $evidenceData->label,
        ];

        if ($evidenceData->originAnswerId !== null) {
            $entry['origin_answer_id'] = $evidenceData->originAnswerId;
        }

        if ($evidenceData->originQuestionId !== null) {
            $entry['origin_question_id'] = $evidenceData->originQuestionId;
        }

        if ($entry['type'] === 'profile_item') {
            $profileItem = ProfileItem::where('candidate_profile_id', $profile->id)
                ->where('id', $entry['value'])
                ->first();

            if ($profileItem === null) {
                throw new UnprocessableEntityException(
                    'Profile item not found or does not belong to you.',
                    'profile_item_evidence_not_owned'
                );
            }

            $entry['value'] = (string) $profileItem->id;
            $entry['profile_item_snapshot'] = [
                'title' => $profileItem->title,
                'type' => $profileItem->type,
                'organization' => $profileItem->organization,
            ];
        }

        if ($entry['type'] === 'url') {
            $this->validateUrl($entry['value']);
        }

        $evidence[] = $entry;

        $updateData = ['evidence' => $evidence];

        if ($expectedUpdatedAt !== null) {
            $updateData['updated_at'] = CarbonImmutable::now();
            $updated = CandidateSkill::where('id', $candidateSkillId)
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

        $profile->touch();

        return $skill;
    }

    public static function validateUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null || in_array(strtolower($scheme), ['javascript', 'data', 'file', 'vbscript'], true)) {
            throw new UnprocessableEntityException(
                'URL scheme is not allowed. Use HTTPS URLs only.',
                'skill_evidence_invalid'
            );
        }

        if (strtolower($scheme) !== 'https') {
            throw new UnprocessableEntityException(
                'Only HTTPS URLs are allowed as evidence.',
                'skill_evidence_invalid'
            );
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new UnprocessableEntityException(
                'Invalid URL format.',
                'skill_evidence_invalid'
            );
        }
    }
}

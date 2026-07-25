<?php

namespace App\Domain\Skills\Actions;

use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use Carbon\CarbonImmutable;

class UpdateEvidenceAction
{
    public function execute(CandidateProfile $profile, int $candidateSkillId, string $evidenceKey, array $data, ?string $expectedUpdatedAt = null): CandidateSkill
    {
        $skill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->with('skill.aliases')
            ->findOrFail($candidateSkillId);

        $evidence = $skill->evidence ?? [];
        $found = false;

        foreach ($evidence as &$entry) {
            if (($entry['key'] ?? null) === $evidenceKey) {
                if (array_key_exists('label', $data)) {
                    $entry['label'] = $data['label'];
                }
                if (array_key_exists('value', $data) && ($entry['type'] ?? null) === 'url') {
                    AddEvidenceAction::validateUrl($data['value']);
                    $entry['value'] = $data['value'];
                }
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new ConflictException(
                'Evidence entry not found.',
                'skill_evidence_not_found'
            );
        }

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

        return $skill;
    }
}

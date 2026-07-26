<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\ProfileItem;
use App\Models\Skill;

class PreviewImportAction
{
    public function execute(CvDocument $document): array
    {
        if ($document->status !== CvDocumentStatus::ReadyForReview) {
            throw new ConflictException(
                'Document is not ready for import.',
                'document_not_ready_for_import',
            );
        }

        $suggestions = $document->suggestions;

        $pendingCount = $suggestions->where('review_status', CvSuggestionReviewStatus::Pending->value)->count();

        if ($pendingCount > 0) {
            throw new ConflictException(
                "{$pendingCount} suggestion(s) have not been reviewed.",
                'not_all_reviewed',
            );
        }

        $countByDecision = [
            CvSuggestionReviewStatus::Accepted->value => 0,
            CvSuggestionReviewStatus::Rejected->value => 0,
            CvSuggestionReviewStatus::KeepExisting->value => 0,
            CvSuggestionReviewStatus::Edited->value => 0,
        ];

        foreach ($suggestions as $s) {
            $status = $s->review_status->value;
            if (isset($countByDecision[$status])) {
                $countByDecision[$status]++;
            }
        }

        $conflicts = [];

        $profile = CandidateProfile::where('user_id', $document->user_id)->first();

        if ($profile) {
            $acceptedSuggestions = $suggestions->reject(
                fn ($s) => $s->review_status->value === CvSuggestionReviewStatus::Rejected->value
            );

            foreach ($acceptedSuggestions as $suggestion) {
                $type = $suggestion->type->value;
                $value = $suggestion->reviewed_decision ?? $suggestion->suggested_value;

                if (in_array($type, [
                    CvSuggestionType::Experience->value,
                    CvSuggestionType::Education->value,
                    CvSuggestionType::Project->value,
                    CvSuggestionType::Certification->value,
                ], true)) {
                    $title = (string) ($value['title'] ?? $value['name'] ?? $value['degree'] ?? '');
                    $organization = (string) ($value['organization'] ?? $value['institution'] ?? '');

                    $existing = ProfileItem::where('candidate_profile_id', $profile->id)
                        ->where('type', $type)
                        ->where('title', $title)
                        ->where('organization', $organization)
                        ->first();

                    if ($existing) {
                        $conflicts[] = [
                            'type' => 'duplicate_item',
                            'field' => $type,
                            'current' => [
                                'id' => $existing->id,
                                'title' => $existing->title,
                                'organization' => $existing->organization,
                                'start_date' => $existing->start_date?->toIso8601String(),
                                'end_date' => $existing->end_date?->toIso8601String(),
                            ],
                            'suggested' => $value,
                            'message' => "{$type} \"{$title}\" at {$organization} already exists in your profile.",
                        ];
                    }
                }

                if ($type === CvSuggestionType::Skill->value) {
                    $skillName = (string) ($value['name'] ?? $value['value'] ?? '');
                    $normalizedName = mb_strtolower(trim($skillName));
                    $skill = Skill::where('normalized_name', $normalizedName)->first();

                    $existing = CandidateSkill::where('candidate_profile_id', $profile->id)
                        ->where(function ($q) use ($skill, $skillName) {
                            if ($skill) {
                                $q->where('skill_id', $skill->id);
                            } else {
                                $q->where('custom_skill_name', $skillName);
                            }
                        })
                        ->first();

                    if ($existing) {
                        $conflicts[] = [
                            'type' => 'duplicate_skill',
                            'field' => 'skill',
                            'current' => [
                                'id' => $existing->id,
                                'name' => $existing->skill->name ?? $existing->custom_skill_name,
                                'proficiency_level' => $existing->proficiency_level->value,
                            ],
                            'suggested' => $value,
                            'message' => "Skill \"{$skillName}\" already exists in your profile.",
                        ];
                    }
                }
            }
        }

        return [
            'summary' => [
                'total' => $suggestions->count(),
                'accepted' => $countByDecision[CvSuggestionReviewStatus::Accepted->value],
                'rejected' => $countByDecision[CvSuggestionReviewStatus::Rejected->value],
                'keep_existing' => $countByDecision[CvSuggestionReviewStatus::KeepExisting->value],
                'edited' => $countByDecision[CvSuggestionReviewStatus::Edited->value],
            ],
            'conflicts' => $conflicts,
            'ready' => true,
            'profile_updated_at' => $profile?->updated_at?->toIso8601String(),
        ];
    }
}

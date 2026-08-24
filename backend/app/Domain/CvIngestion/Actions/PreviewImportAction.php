<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\SkillAlias;

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
            $allProfileItems = ProfileItem::where('candidate_profile_id', $profile->id)->get();
            $existingProfileItems = $allProfileItems
                ->keyBy(fn (ProfileItem $item) => ProfileIdentityService::normalize($item->type->value).'|'.ProfileIdentityService::normalize($item->title).'|'.ProfileIdentityService::normalize($item->organization ?? ''));

            $skillMaps = ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all());
            $allSkills = $skillMaps['skills'];
            $allAliases = $skillMaps['aliases'];

            $existingCandidateSkills = CandidateSkill::where('candidate_profile_id', $profile->id)->with('skill')->get();

            $acceptedSuggestions = $suggestions->reject(
                fn ($s) => $s->review_status->value === CvSuggestionReviewStatus::Rejected->value
            );

            foreach ($acceptedSuggestions as $suggestion) {
                $type = $suggestion->type->value;
                $value = $suggestion->reviewed_decision ?? $suggestion->suggested_value;
                $decision = $suggestion->review_status->value;

                if (in_array($type, [
                    CvSuggestionType::Experience->value,
                    CvSuggestionType::Education->value,
                    CvSuggestionType::Project->value,
                    CvSuggestionType::Certification->value,
                ], true)) {
                    $existing = ProfileIdentityService::findExistingProfileItem($allProfileItems, $type, $value);

                    if ($existing) {
                        // Exact duplicate already has explicit handling via Apply; don't report as conflict if already decided
                        if (in_array($decision, [CvSuggestionReviewStatus::KeepExisting->value, CvSuggestionReviewStatus::UpdateExisting->value], true)) {
                            continue;
                        }

                        $titleForMsg = ProfileIdentityService::extractTitle($type, $value);
                        $orgForMsg = ProfileIdentityService::extractOrganization($type, $value);
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
                            'message' => "{$type} \"{$titleForMsg}\" at {$orgForMsg} already exists in your profile.",
                        ];
                    } else {
                        // Check for possible duplicate (fuzzy) — same type, similar title/org, compatible dates
                        $possible = ProfileIdentityService::findPossibleDuplicateProfileItem($allProfileItems, $type, $value);
                        if ($possible !== null) {
                            // If user already made explicit choice for possible duplicate, consider resolved
                            if (in_array($decision, [CvSuggestionReviewStatus::KeepExisting->value, CvSuggestionReviewStatus::UpdateExisting->value, CvSuggestionReviewStatus::CreateNew->value], true)) {
                                continue;
                            }

                            $existingPossible = $possible['existing'];
                            $titleForMsg = ProfileIdentityService::extractTitle($type, $value);
                            $orgForMsg = ProfileIdentityService::extractOrganization($type, $value);
                            $conflicts[] = [
                                'type' => 'possible_duplicate',
                                'field' => $type,
                                'current' => [
                                    'id' => $existingPossible->id,
                                    'title' => $existingPossible->title,
                                    'organization' => $existingPossible->organization,
                                    'start_date' => $existingPossible->start_date?->toIso8601String(),
                                    'end_date' => $existingPossible->end_date?->toIso8601String(),
                                    'similarity' => round($possible['similarity'], 2),
                                    'reason' => $possible['reason'],
                                ],
                                'suggested' => $value,
                                'suggestion_id' => $suggestion->id,
                                'similarity' => round($possible['similarity'], 2),
                                'reason' => $possible['reason'],
                                'existing_id' => $existingPossible->id,
                                'suggested_action' => 'review_required',
                                'message' => "Possible duplicate: \"{$titleForMsg}\" at {$orgForMsg} looks similar to existing \"{$existingPossible->title}\" at {$existingPossible->organization} ({$possible['reason']}).",
                            ];
                        }
                    }
                }

                if ($type === CvSuggestionType::Language->value) {
                    if (in_array($decision, [CvSuggestionReviewStatus::KeepExisting->value], true)) {
                        // already resolved to keep
                    } else {
                        $langName = (string) ($value['language'] ?? '');
                        $existingLang = ProfileIdentityService::findExistingLanguage($profile->languages ?? [], $langName);

                        if ($existingLang !== null) {
                            $conflicts[] = [
                                'type' => 'duplicate_language',
                                'field' => 'language',
                                'current' => $existingLang,
                                'suggested' => $value,
                                'message' => "Language \"{$langName}\" already exists in your profile.",
                            ];
                        }
                    }
                }

                if ($type === CvSuggestionType::Skill->value) {
                    if (in_array($decision, [CvSuggestionReviewStatus::KeepExisting->value], true)) {
                        // resolved
                    } else {
                        $skillName = (string) ($value['name'] ?? $value['value'] ?? '');

                        $existing = ProfileIdentityService::findExistingCandidateSkill(
                            $existingCandidateSkills,
                            $skillName,
                            $allSkills,
                            $allAliases,
                        );

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

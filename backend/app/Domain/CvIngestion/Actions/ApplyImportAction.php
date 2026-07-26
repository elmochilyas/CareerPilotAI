<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvImportBatchStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Exceptions\Api\ConflictException;
use App\Jobs\RecalculateProfileCompletionJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

class ApplyImportAction
{
    public function execute(CvDocument $document, string $idempotencyKey, ?string $profileUpdatedAt = null): CvImportBatch
    {
        $existing = CvImportBatch::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            throw new ConflictException(
                'This import has already been applied.',
                'import_already_applied',
            );
        }

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

        $document->update(['status' => CvDocumentStatus::Importing]);
        $batch = CvImportBatch::create([
            'cv_document_id' => $document->id,
            'user_id' => $document->user_id,
            'status' => CvImportBatchStatus::Pending,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            return DB::transaction(function () use ($document, $suggestions, $batch, $profileUpdatedAt) {
                $profile = CandidateProfile::where('user_id', $document->user_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($profileUpdatedAt !== null) {
                    $stored = $profile->updated_at?->toIso8601String();
                    if ($stored !== $profileUpdatedAt) {
                        throw new ConflictException(
                            'Profile has changed since the review was generated. Please re-review.',
                            'profile_changed',
                        );
                    }
                }

                $accepted = $suggestions->reject(
                    fn (CvSuggestion $s) => $s->review_status->value === CvSuggestionReviewStatus::Rejected->value
                );

                $fieldsUpdated = 0;
                $itemsCreated = 0;
                $itemsUpdated = 0;
                $skillsAdded = 0;
                $skipped = 0;

                foreach ($accepted as $suggestion) {
                    $type = $suggestion->type->value;
                    $value = $suggestion->reviewed_decision ?? $suggestion->suggested_value;

                    $result = match (true) {
                        $type === CvSuggestionType::BasicInformation->value => $this->applyBasicInformation($profile, $value, $suggestion),
                        in_array($type, [
                            CvSuggestionType::Headline->value,
                            CvSuggestionType::Summary->value,
                            CvSuggestionType::SocialLink->value,
                            CvSuggestionType::Language->value,
                        ], true) => match ($type) {
                            CvSuggestionType::Headline->value => $this->applyHeadline($profile, $value, $suggestion),
                            CvSuggestionType::Summary->value => $this->applySummary($profile, $value, $suggestion),
                            CvSuggestionType::SocialLink->value => $this->applySocialLink($profile, $value, $suggestion),
                            CvSuggestionType::Language->value => $this->applyLanguage($profile, $value, $suggestion),
                        },
                        in_array($type, [
                            CvSuggestionType::Experience->value,
                            CvSuggestionType::Education->value,
                            CvSuggestionType::Project->value,
                            CvSuggestionType::Certification->value,
                        ], true) => $this->applyProfileItem($profile, $value, $suggestion, $type),
                        default => $this->applySkill($profile, $value, $suggestion),
                    };

                    if ($result !== null) {
                        match ($result['type']) {
                            'field' => $fieldsUpdated++,
                            'item_created' => $itemsCreated++,
                            'item_updated' => $itemsUpdated++,
                            'skill_added' => $skillsAdded++,
                            default => null,
                        };
                    } else {
                        $skipped++;
                    }
                }

                $document->update(['status' => CvDocumentStatus::Imported]);

                $summary = [
                    'total_accepted' => $accepted->count(),
                    'fields_updated' => $fieldsUpdated,
                    'items_created' => $itemsCreated,
                    'items_updated' => $itemsUpdated,
                    'skills_added' => $skillsAdded,
                    'skipped' => $skipped,
                    'errors' => 0,
                ];

                $batch->update([
                    'status' => CvImportBatchStatus::Applied,
                    'imported_at' => now(),
                    'summary' => $summary,
                ]);

                RecalculateProfileCompletionJob::dispatch($profile->id)
                    ->onQueue(config('cv-ingestion.queue', 'cv-ingestion'));

                return $batch;
            });
        } catch (\Throwable $e) {
            $document->update(['status' => CvDocumentStatus::ReadyForReview]);
            $batch->update([
                'status' => CvImportBatchStatus::Failed,
                'failure_reason' => $e->getMessage(),
                'failure_code' => 'import_failed',
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applyBasicInformation(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $fieldName = $suggestion->field_name;
        $val = $value['value'] ?? null;

        if ($val === null || $fieldName === null) {
            return null;
        }

        $profileField = match ($fieldName) {
            'full_name', 'email' => null,
            'phone' => 'phone',
            'city' => 'city',
            'country' => 'country',
            default => null,
        };

        if ($profileField === null) {
            return null;
        }

        $profile->update([$profileField => mb_substr((string) $val, 0, 255)]);
        $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

        return ['type' => 'field', 'id' => $profile->id];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applyHeadline(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $headline = $value['value'] ?? null;
        if ($headline) {
            $profile->update(['headline' => mb_substr((string) $headline, 0, 255)]);
            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

            return ['type' => 'field', 'id' => $profile->id];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applySummary(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $summary = $value['value'] ?? null;
        if ($summary) {
            $profile->update(['professional_summary' => mb_substr((string) $summary, 0, 5000)]);
            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

            return ['type' => 'field', 'id' => $profile->id];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applySocialLink(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $category = $suggestion->category;
        $url = $value['url'] ?? null;

        if ($url === null) {
            return null;
        }

        $field = match ($category) {
            'linkedin' => 'linkedin_url',
            'github' => 'github_url',
            'portfolio' => 'portfolio_url',
            default => null,
        };

        if ($field) {
            $profile->update([$field => mb_substr((string) $url, 0, 500)]);
            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

            return ['type' => 'field', 'id' => $profile->id];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}
     */
    private function applyLanguage(CandidateProfile $profile, array $value, CvSuggestion $suggestion): array
    {
        $languages = $profile->languages ?? [];

        $newLang = [
            'language' => mb_substr((string) ($value['language'] ?? 'unknown'), 0, 50),
            'proficiency' => mb_substr((string) ($value['proficiency'] ?? 'intermediate'), 0, 30),
        ];

        $exists = false;
        foreach ($languages as $existing) {
            if ($existing['language'] === $newLang['language']) {
                $exists = true;
                break;
            }
        }

        if (! $exists) {
            $languages[] = $newLang;
            $profile->update(['languages' => $languages]);
        }

        $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

        return ['type' => 'field', 'id' => $profile->id];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applyProfileItem(CandidateProfile $profile, array $value, CvSuggestion $suggestion, string $type): ?array
    {
        $maxOrder = ProfileItem::where('candidate_profile_id', $profile->id)->max('display_order') ?? 0;

        $title = (string) ($value['title'] ?? $value['name'] ?? $value['degree'] ?? 'Untitled');
        $organization = (string) ($value['organization'] ?? $value['institution'] ?? '');

        $existing = ProfileItem::where('candidate_profile_id', $profile->id)
            ->where('type', $type)
            ->where('title', $title)
            ->where('organization', $organization)
            ->first();

        if ($existing && $suggestion->review_status->value === CvSuggestionReviewStatus::UpdateExisting->value) {
            $existing->update([
                'description' => $value['description'] ?? $existing->description,
                'start_date' => $value['start_date'] ?? $existing->start_date,
                'end_date' => $value['end_date'] ?? $existing->end_date,
                'location' => $value['location'] ?? $existing->location,
            ]);
            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $existing->id]);

            return ['type' => 'item_updated', 'id' => $existing->id];
        }

        if (! $existing) {
            $item = ProfileItem::create([
                'candidate_profile_id' => $profile->id,
                'type' => $type,
                'title' => mb_substr($title, 0, 255),
                'organization' => $organization !== '' ? mb_substr($organization, 0, 255) : null,
                'location' => isset($value['location']) ? mb_substr((string) $value['location'], 0, 255) : null,
                'start_date' => $value['start_date'] ?? null,
                'end_date' => $value['end_date'] ?? null,
                'description' => isset($value['description']) ? mb_substr((string) $value['description'], 0, 5000) : null,
                'display_order' => $maxOrder + 1,
            ]);

            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $item->id]);

            return ['type' => 'item_created', 'id' => $item->id];
        }

        $suggestion->update(['import_status' => 'skipped']);

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applySkill(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $skillName = (string) ($value['name'] ?? $value['value'] ?? 'Unknown Skill');
        $normalizedName = mb_strtolower(trim($skillName));

        $skill = Skill::where('normalized_name', $normalizedName)->first();

        $existingSkill = CandidateSkill::where('candidate_profile_id', $profile->id)
            ->where(function ($q) use ($skill, $skillName) {
                if ($skill) {
                    $q->where('skill_id', $skill->id);
                } else {
                    $q->where('custom_skill_name', $skillName);
                }
            })
            ->first();

        if ($existingSkill) {
            $suggestion->update(['import_status' => 'skipped']);

            return null;
        }

        $candidateSkill = CandidateSkill::create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill?->id,
            'custom_skill_name' => $skill ? null : mb_substr($skillName, 0, 150),
            'state' => 'claimed',
            'proficiency_level' => 'intermediate',
            'years_experience' => null,
            'evidence' => [
                ['type' => 'cv_import', 'cv_document_id' => $suggestion->cv_document_id, 'source_text' => $suggestion->source_text],
            ],
        ]);

        $suggestion->update([
            'import_status' => 'included',
            'applied_skill_id' => $candidateSkill->id,
        ]);

        return ['type' => 'skill_added', 'id' => $candidateSkill->id];
    }
}

<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvImportBatchStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionType;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Exceptions\Api\ConflictException;
use App\Jobs\RecalculateProfileCompletionJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplyImportAction
{
    /**
     * French month names (with and without accents, with and without the
     * trailing period used in abbreviations) mapped to their English forms.
     *
     * @var array<string, string>
     */
    private const FRENCH_MONTH_NAMES = [
        'janvier' => 'January',
        'janv' => 'January',
        'jan' => 'January',
        'février' => 'February',
        'fevrier' => 'February',
        'févr' => 'February',
        'fevr' => 'February',
        'mars' => 'March',
        'mar' => 'March',
        'avril' => 'April',
        'avr' => 'April',
        'mai' => 'May',
        'juin' => 'June',
        'juillet' => 'July',
        'juil' => 'July',
        'août' => 'August',
        'aout' => 'August',
        'aoû' => 'August',
        'septembre' => 'September',
        'sept' => 'September',
        'sep' => 'September',
        'octobre' => 'October',
        'oct' => 'October',
        'novembre' => 'November',
        'nov' => 'November',
        'décembre' => 'December',
        'decembre' => 'December',
        'déc' => 'December',
        'dec' => 'December',
    ];

    public function execute(CvDocument $document, string $idempotencyKey, ?string $profileUpdatedAt = null): CvImportBatch
    {
        $profileUpdatedAt = $this->normalizeProfileUpdatedAt($profileUpdatedAt);

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
                $mode = (string) ($document->metadata['mode'] ?? 'create_new');

                $profile = CandidateProfile::where('user_id', $document->user_id)
                    ->lockForUpdate()
                    ->first();

                if ($profile === null) {
                    if ($mode === 'update_existing') {
                        throw new ConflictException(
                            'This CV is set to update an existing profile, but no candidate profile exists yet. Create a profile first or re-upload this CV in "Create new" mode.',
                            'profile_required_for_update',
                        );
                    }

                    $profile = new CandidateProfile;
                    $profile->user_id = $document->user_id;
                    $profile->save();
                }

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

                $profileItems = ProfileItem::where('candidate_profile_id', $profile->id)->get();
                $skillMaps = ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all());
                $allSkills = $skillMaps['skills'];
                $allAliases = $skillMaps['aliases'];
                $candidateSkills = CandidateSkill::where('candidate_profile_id', $profile->id)->with('skill')->get();
                $maxDisplayOrder = $profileItems->max('display_order') ?? 0;

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
                        ], true) => $this->applyProfileItem($profile, $value, $suggestion, $type, $profileItems, $maxDisplayOrder),
                        default => $this->applySkill($profile, $value, $suggestion, $allSkills, $allAliases, $candidateSkills),
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
            $normalized = str_starts_with($url, 'http') ? $url : "https://{$url}";
            $profile->update([$field => mb_substr($normalized, 0, 500)]);
            $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

            return ['type' => 'field', 'id' => $profile->id];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applyLanguage(CandidateProfile $profile, array $value, CvSuggestion $suggestion): ?array
    {
        $languages = $profile->languages ?? [];
        $incomingName = (string) ($value['language'] ?? 'unknown');
        $trimmedName = trim($incomingName) === '' ? 'unknown' : trim($incomingName);

        $existing = ProfileIdentityService::findExistingLanguage($languages, $trimmedName);

        if ($existing !== null) {
            $suggestion->update(['import_status' => 'skipped', 'applied_profile_id' => $profile->id]);

            return null;
        }

        $newLang = [
            'language' => mb_substr($trimmedName, 0, 50),
            'proficiency' => mb_substr((string) ($value['proficiency'] ?? 'intermediate'), 0, 30),
        ];

        $languages[] = $newLang;
        $profile->update(['languages' => $languages]);

        $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $profile->id]);

        return ['type' => 'field', 'id' => $profile->id];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applyProfileItem(
        CandidateProfile $profile,
        array $value,
        CvSuggestion $suggestion,
        string $type,
        Collection $profileItems,
        int &$maxDisplayOrder,
    ): ?array {
        $title = ProfileIdentityService::extractTitle($type, $value);
        if (trim($title) === '') {
            $title = 'Untitled';
        }
        $organization = ProfileIdentityService::extractOrganization($type, $value);

        $exact = ProfileIdentityService::findExistingProfileItem($profileItems, $type, $value);

        if ($exact) {
            if ($suggestion->review_status->value === CvSuggestionReviewStatus::UpdateExisting->value) {
                $exact->update([
                    'description' => $value['description'] ?? $exact->description,
                    'start_date' => $this->normalizeDate($value['start_date'] ?? null) ?? $exact->start_date,
                    'end_date' => ! empty($value['is_current'])
                        ? null
                        : ($this->normalizeDate($value['end_date'] ?? null) ?? $exact->end_date),
                    'location' => $value['location'] ?? $exact->location,
                ]);
                $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $exact->id]);

                return ['type' => 'item_updated', 'id' => $exact->id];
            }

            $suggestion->update(['import_status' => 'skipped']);

            return null;
        }

        $possible = ProfileIdentityService::findPossibleDuplicateProfileItem($profileItems, $type, $value);

        if ($possible !== null) {
            $decision = $suggestion->review_status->value;
            $existingPossible = $possible['existing'];

            if ($decision === CvSuggestionReviewStatus::KeepExisting->value) {
                $suggestion->update(['import_status' => 'skipped', 'applied_profile_id' => $existingPossible->id]);

                return null;
            }

            if ($decision === CvSuggestionReviewStatus::UpdateExisting->value) {
                $existingPossible->update([
                    'description' => $value['description'] ?? $existingPossible->description,
                    'start_date' => $this->normalizeDate($value['start_date'] ?? null) ?? $existingPossible->start_date,
                    'end_date' => ! empty($value['is_current'])
                        ? null
                        : ($this->normalizeDate($value['end_date'] ?? null) ?? $existingPossible->end_date),
                    'location' => $value['location'] ?? $existingPossible->location,
                ]);
                $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $existingPossible->id]);

                return ['type' => 'item_updated', 'id' => $existingPossible->id];
            }

            if (in_array($decision, [CvSuggestionReviewStatus::CreateNew->value, CvSuggestionReviewStatus::Accepted->value, CvSuggestionReviewStatus::Edited->value], true)) {
                $maxDisplayOrder++;
                $item = ProfileItem::create([
                    'candidate_profile_id' => $profile->id,
                    'type' => $type,
                    'title' => mb_substr($title, 0, 255),
                    'organization' => $organization !== '' ? mb_substr($organization, 0, 255) : null,
                    'location' => isset($value['location']) ? mb_substr((string) $value['location'], 0, 255) : null,
                    'start_date' => $this->normalizeDate($value['start_date'] ?? null),
                    'end_date' => ! empty($value['is_current']) ? null : $this->normalizeDate($value['end_date'] ?? null),
                    'description' => isset($value['description']) ? mb_substr((string) $value['description'], 0, 5000) : null,
                    'display_order' => $maxDisplayOrder,
                ]);
                $profileItems->push($item);
                $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $item->id]);

                return ['type' => 'item_created', 'id' => $item->id];
            }

            // For any other decision on possible duplicate, default to keep existing (require explicit)
            $suggestion->update(['import_status' => 'skipped', 'applied_profile_id' => $existingPossible->id]);

            return null;
        }

        $maxDisplayOrder++;
        $item = ProfileItem::create([
            'candidate_profile_id' => $profile->id,
            'type' => $type,
            'title' => mb_substr($title, 0, 255),
            'organization' => $organization !== '' ? mb_substr($organization, 0, 255) : null,
            'location' => isset($value['location']) ? mb_substr((string) $value['location'], 0, 255) : null,
            'start_date' => $this->normalizeDate($value['start_date'] ?? null),
            'end_date' => ! empty($value['is_current']) ? null : $this->normalizeDate($value['end_date'] ?? null),
            'description' => isset($value['description']) ? mb_substr((string) $value['description'], 0, 5000) : null,
            'display_order' => $maxDisplayOrder,
        ]);
        $profileItems->push($item);

        $suggestion->update(['import_status' => 'included', 'applied_profile_id' => $item->id]);

        return ['type' => 'item_created', 'id' => $item->id];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{type: string, id: mixed}|null
     */
    private function applySkill(
        CandidateProfile $profile,
        array $value,
        CvSuggestion $suggestion,
        Collection $allSkills,
        Collection $allAliases,
        Collection $candidateSkills,
    ): ?array {
        $skillName = (string) ($value['name'] ?? $value['value'] ?? 'Unknown Skill');

        $resolved = ProfileIdentityService::resolveSkill($skillName, $allSkills, $allAliases);
        $skill = $resolved['skill'];

        $existingSkill = ProfileIdentityService::findExistingCandidateSkill(
            $candidateSkills,
            $skillName,
            $allSkills,
            $allAliases,
        );

        if ($existingSkill) {
            $suggestion->update(['import_status' => 'skipped']);

            return null;
        }

        $candidateSkill = CandidateSkill::create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill?->id,
            'custom_skill_name' => $skill ? null : mb_substr(trim($skillName), 0, 150),
            'state' => 'claimed',
            'proficiency_level' => 'intermediate',
            'years_experience' => null,
            'evidence' => [
                ['type' => 'cv_import', 'cv_document_id' => $suggestion->cv_document_id, 'source_text' => $suggestion->source_text],
            ],
        ]);

        // Keep in-memory collection in sync to prevent duplicate within same import
        $candidateSkill->loadMissing('skill');
        $candidateSkills->push($candidateSkill);

        $suggestion->update([
            'import_status' => 'included',
            'applied_skill_id' => $candidateSkill->id,
        ]);

        return ['type' => 'skill_added', 'id' => $candidateSkill->id];
    }

    private function normalizeProfileUpdatedAt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalize an AI-extracted date to a Y-m-d string.
     *
     * CV text is untrusted data, so a value that cannot be interpreted must
     * never crash the import. French month names (full or abbreviated, with or
     * without accents) are translated first, then the value is matched against
     * a strict allowlist of formats. Bare years default to January 1st and
     * month-only values default to the first of the month. Unparseable values
     * return null so the field is simply left empty.
     */
    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'présent')
            || str_contains($lower, 'present')
            || str_contains($lower, 'now')
            || str_contains($lower, 'ongoing')
            || str_contains($lower, 'current')
            || str_contains($lower, 'actuel')
            || $lower === 'en cours'
            || $lower === 'à ce jour') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $raw)) {
            return $raw.'-01-01';
        }

        $words = preg_split('/\s+/', $raw) ?: [];
        $words = array_map(fn (string $word): string => self::FRENCH_MONTH_NAMES[mb_strtolower(rtrim($word, '.'))] ?? $word, $words);
        $raw = trim(implode(' ', $words));
        $raw = trim($raw, "., \t\n\r\0\x0B");

        $formats = [
            'Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'Y.m.d',
            'Y-m', 'Y/m', 'm/Y', 'n/Y',
            'F Y', 'M Y', 'F y', 'M y',
            'j F Y', 'd F Y', 'j M Y', 'd M Y', 'F j, Y', 'M j, Y', 'F d, Y', 'M d, Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
            } catch (\Throwable) {
                continue;
            }

            if ($parsed === null || mb_strtolower($parsed->format($format)) !== mb_strtolower($raw)) {
                continue;
            }

            if (! str_contains($format, 'd') && ! str_contains($format, 'j')) {
                $parsed->day(1);
            }

            return $parsed->toDateString();
        }

        return null;
    }
}

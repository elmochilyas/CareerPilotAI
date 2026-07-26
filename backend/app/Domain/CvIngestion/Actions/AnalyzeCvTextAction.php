<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;
use App\Domain\CvIngestion\Services\CvAnalysisSchemaValidator;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use App\Models\Skill;
use Illuminate\Support\Facades\Log;

class AnalyzeCvTextAction
{
    public function __construct(
        private CvAnalyzer $analyzer,
        private CvAnalysisSchemaValidator $schemaValidator,
    ) {}

    public function execute(CvDocument $document, CvProcessingRun $run, string $extractedText): void
    {
        $result = $this->analyzer->analyze($extractedText);

        $run->update([
            'ai_provider' => $result->provider,
            'ai_model' => $result->model,
            'ai_prompt_version' => $result->promptVersion,
            'ai_latency_ms' => $result->latencyMs,
            'ai_tokens_prompt' => $result->tokensPrompt,
            'ai_tokens_completion' => $result->tokensCompletion,
            'ai_response_id' => $result->responseId,
        ]);

        $validation = $this->schemaValidator->validate([
            'schema_version' => $result->schemaVersion ?? '1.1.0',
            'basic_information' => $result->basicInformation,
            'headline' => $result->headline,
            'professional_summary' => $result->professionalSummary,
            'professional_links' => $result->professionalLinks,
            'experiences' => $result->experiences,
            'projects' => $result->projects,
            'education' => $result->education,
            'certifications' => $result->certifications,
            'languages' => $result->languages,
            'skills' => $result->skills,
            'warnings' => $result->warnings,
        ]);

        if (! $validation['valid']) {
            $schemaVersion = config('cv-ingestion.analysis_schema_version', '1.1.0');

            Log::warning('CV analysis schema validation failed', [
                'document_id' => $document->id,
                'run_id' => $run->id,
                'provider' => $result->provider,
                'model' => $result->model,
                'schema_version' => $schemaVersion,
                'error_paths' => $validation['errors'],
                'processing_stage' => 'analysis_validation',
            ]);

            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'AI output failed schema validation: '.implode('; ', $validation['errors']),
                'failure_code' => 'ai_schema_validation_failed',
            ]);
            $run->update([
                'status' => 'failed',
                'failure_reason' => 'Schema validation failed',
                'failure_code' => 'ai_schema_validation_failed',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('AI output failed schema validation.');
        }

        $validated = $validation['result'];
        $userId = $document->user_id;
        $schemaVersion = config('cv-ingestion.analysis_schema_version', '1.1.0');
        $created = 0;

        $created += $this->createBasicInformationSuggestions($document, $run, $validated->basicInformation, $userId, $schemaVersion);
        $created += $this->createFieldSuggestion($document, $run, 'headline', null, 'headline', $validated->headline, $userId, $schemaVersion);
        $created += $this->createFieldSuggestion($document, $run, 'summary', null, 'professional_summary', $validated->professionalSummary, $userId, $schemaVersion);

        foreach ($validated->professionalLinks as $link) {
            $category = $link['type'] ?? 'other';
            $created += $this->createEntitySuggestion($document, $run, 'social_link', $category, $link, $userId, $schemaVersion);
        }

        foreach ($validated->experiences as $exp) {
            $created += $this->createEntitySuggestion($document, $run, 'experience', null, $exp, $userId, $schemaVersion);
        }

        foreach ($validated->projects as $proj) {
            $created += $this->createEntitySuggestion($document, $run, 'project', null, $proj, $userId, $schemaVersion);
        }

        foreach ($validated->education as $edu) {
            $created += $this->createEntitySuggestion($document, $run, 'education', null, $edu, $userId, $schemaVersion);
        }

        foreach ($validated->certifications as $cert) {
            $created += $this->createEntitySuggestion($document, $run, 'certification', null, $cert, $userId, $schemaVersion);
        }

        foreach ($validated->languages as $lang) {
            $created += $this->createEntitySuggestion($document, $run, 'language', null, $lang, $userId, $schemaVersion);
        }

        foreach ($validated->skills as $skill) {
            $created += $this->createEntitySuggestion($document, $run, 'skill', $skill['category'] ?? null, $skill, $userId, $schemaVersion);
        }

        if ($created === 0) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'AI analysis returned no valid suggestions.',
                'failure_code' => 'ai_no_suggestions',
            ]);
            $run->update([
                'status' => 'failed',
                'failure_reason' => 'No valid suggestions generated',
                'failure_code' => 'ai_no_suggestions',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('AI analysis returned no valid suggestions.');
        }

        $document->update(['status' => CvDocumentStatus::ReadyForReview]);
        $run->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $basicInfo
     */
    private function createBasicInformationSuggestions(CvDocument $document, CvProcessingRun $run, array $basicInfo, int $userId, string $schemaVersion): int
    {
        $count = 0;
        $fieldMap = [
            'full_name' => 'full_name',
            'email' => 'email',
            'phone' => 'phone',
            'city' => 'city',
            'country' => 'country',
        ];

        foreach ($fieldMap as $key => $fieldName) {
            $value = $basicInfo[$key] ?? null;
            if ($value === null || ! is_string($value) || $value === '') {
                continue;
            }

            $currentValue = $this->getCurrentProfileField($userId, $fieldName);
            $existing = CvSuggestion::where('cv_document_id', $document->id)
                ->where('type', 'basic_information')
                ->where('field_name', $fieldName)
                ->exists();

            if ($existing) {
                continue;
            }

            CvSuggestion::create([
                'cv_document_id' => $document->id,
                'cv_processing_run_id' => $run->id,
                'type' => 'basic_information',
                'category' => null,
                'field_name' => $fieldName,
                'current_value' => $currentValue,
                'suggested_value' => ['value' => $value],
                'source_page' => null,
                'source_text' => null,
                'extraction_method' => 'ai_extraction',
                'schema_version' => $schemaVersion,
                'confidence' => null,
                'review_status' => CvSuggestionReviewStatus::Pending,
            ]);

            $count++;
        }

        return $count;
    }

    private function createFieldSuggestion(CvDocument $document, CvProcessingRun $run, string $type, ?string $category, string $fieldName, ?string $value, int $userId, string $schemaVersion): int
    {
        if ($value === null) {
            return 0;
        }

        $currentValue = $this->getCurrentProfileField($userId, $fieldName);
        $existing = CvSuggestion::where('cv_document_id', $document->id)
            ->where('type', $type)
            ->where('field_name', $fieldName)
            ->exists();

        if ($existing) {
            return 0;
        }

        CvSuggestion::create([
            'cv_document_id' => $document->id,
            'cv_processing_run_id' => $run->id,
            'type' => $type,
            'category' => $category,
            'field_name' => $fieldName,
            'current_value' => $currentValue,
            'suggested_value' => ['value' => $value],
            'source_page' => null,
            'source_text' => null,
            'extraction_method' => 'ai_extraction',
            'schema_version' => $schemaVersion,
            'confidence' => null,
            'review_status' => CvSuggestionReviewStatus::Pending,
        ]);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function createEntitySuggestion(CvDocument $document, CvProcessingRun $run, string $type, ?string $category, array $entity, int $userId, string $schemaVersion): int
    {
        $entityWithoutSource = $entity;
        unset($entityWithoutSource['source']);

        $sourceText = $entity['source']['text'] ?? null;
        $sourcePage = $entity['source']['page'] ?? null;

        $currentValue = $this->getCurrentEntityValue($userId, $type, $entityWithoutSource);
        $dedupKey = $this->getDedupKey($type, $entityWithoutSource);

        $existing = CvSuggestion::where('cv_document_id', $document->id)
            ->where('type', $type)
            ->where(function ($q) use ($dedupKey) {
                if ($dedupKey !== null) {
                    $q->where('field_name', $dedupKey);
                }
            })
            ->exists();

        if ($existing) {
            return 0;
        }

        CvSuggestion::create([
            'cv_document_id' => $document->id,
            'cv_processing_run_id' => $run->id,
            'type' => $type,
            'category' => $category,
            'field_name' => $dedupKey,
            'current_value' => $currentValue,
            'suggested_value' => $entityWithoutSource,
            'source_page' => $sourcePage,
            'source_text' => $sourceText,
            'extraction_method' => 'ai_extraction',
            'schema_version' => $schemaVersion,
            'confidence' => null,
            'review_status' => CvSuggestionReviewStatus::Pending,
        ]);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function getDedupKey(string $type, array $entity): ?string
    {
        return match ($type) {
            'social_link' => $entity['type'] ?? null,
            'experience' => ($entity['title'] ?? '').'|'.($entity['organization'] ?? ''),
            'project' => $entity['name'] ?? null,
            'education' => ($entity['degree'] ?? '').'|'.($entity['institution'] ?? ''),
            'certification' => $entity['name'] ?? null,
            'language' => $entity['language'] ?? null,
            'skill' => $entity['name'] ?? null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentEntityValue(int $userId, string $type, array $entity): ?array
    {
        $profile = CandidateProfile::where('user_id', $userId)->first();

        if (! $profile) {
            return null;
        }

        return match ($type) {
            'social_link' => $this->getCurrentSocialLink($profile, $entity),
            'language' => $this->getCurrentLanguage($profile, $entity),
            'experience', 'education', 'project', 'certification' => $this->getCurrentProfileItem($profile, $entity, $type),
            'skill' => $this->getCurrentSkill($profile, $entity),
            default => null,
        };
    }

    private function getCurrentProfileField(int $userId, string $field): ?array
    {
        $profile = CandidateProfile::where('user_id', $userId)->first();

        if (! $profile) {
            return null;
        }

        $value = match ($field) {
            'headline' => $profile->headline,
            'professional_summary' => $profile->professional_summary,
            'full_name' => $profile->user->full_name,
            'email' => $profile->user->email,
            'phone' => $profile->phone,
            'city' => $profile->city,
            'country' => $profile->country,
            default => null,
        };

        return $value !== null ? ['value' => $value] : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentSocialLink(CandidateProfile $profile, array $entity): ?array
    {
        $field = match ($entity['type'] ?? '') {
            'linkedin' => $profile->linkedin_url,
            'github' => $profile->github_url,
            'portfolio' => $profile->portfolio_url,
            default => null,
        };

        return $field ? ['url' => $field] : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentLanguage(CandidateProfile $profile, array $entity): ?array
    {
        $languages = $profile->languages ?? [];
        $targetLang = mb_strtolower(trim((string) ($entity['language'] ?? '')));

        foreach ($languages as $lang) {
            if (mb_strtolower(trim((string) $lang['language'])) === $targetLang) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentProfileItem(CandidateProfile $profile, array $entity, string $type): ?array
    {
        $title = $entity['title'] ?? $entity['name'] ?? $entity['degree'] ?? '';
        $organization = $entity['organization'] ?? $entity['institution'] ?? '';

        $existing = ProfileItem::where('candidate_profile_id', $profile->id)
            ->where('type', $type)
            ->where('title', $title)
            ->where('organization', $organization)
            ->first();

        if (! $existing) {
            return null;
        }

        return [
            'id' => $existing->id,
            'title' => $existing->title,
            'organization' => $existing->organization,
            'location' => $existing->location,
            'start_date' => $existing->start_date?->toIso8601String(),
            'end_date' => $existing->end_date?->toIso8601String(),
            'description' => $existing->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentSkill(CandidateProfile $profile, array $entity): ?array
    {
        $skillName = (string) ($entity['name'] ?? '');
        if ($skillName === '') {
            return null;
        }

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

        if (! $existing) {
            return null;
        }

        return [
            'id' => $existing->id,
            'name' => $existing->skill->name ?? $existing->custom_skill_name,
            'proficiency_level' => $existing->proficiency_level->value,
            'years_experience' => $existing->years_experience,
            'state' => $existing->state->value,
        ];
    }
}

<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvProcessingRunStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;
use App\Domain\CvIngestion\Services\CvAnalysisSchemaValidator;
use App\Domain\Profile\Services\ProfileIdentityService;
use App\Models\CandidateProfile;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AnalyzeCvTextAction
{
    /**
     * @var array<string, true> Pre-fetched dedup keys for the current document.
     */
    private array $existingDedupKeys = [];

    /** @var array{skills: Collection, aliases: Collection}|null */
    private ?array $cachedSkillMaps = null;

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
                'status' => CvProcessingRunStatus::Failed,
                'failure_reason' => 'Schema validation failed',
                'failure_code' => 'ai_schema_validation_failed',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('AI output failed schema validation.');
        }

        $validated = $validation['result'];
        $userId = $document->user_id;
        $schemaVersion = config('cv-ingestion.analysis_schema_version', '1.1.0');

        // Load the profile once and preload relationships needed for entity lookups.
        $profile = CandidateProfile::where('user_id', $userId)
            ->with(['items', 'candidateSkills.skill'])
            ->first();

        // Pre-fetch all existing suggestion dedup keys for this document in one query.
        $this->existingDedupKeys = $this->loadExistingDedupKeys($document->id);

        $created = 0;

        $created += $this->createBasicInformationSuggestions($document, $run, $validated->basicInformation, $profile, $schemaVersion);
        $created += $this->createFieldSuggestion($document, $run, 'headline', null, 'headline', $validated->headline, $profile, $schemaVersion);
        $created += $this->createFieldSuggestion($document, $run, 'summary', null, 'professional_summary', $validated->professionalSummary, $profile, $schemaVersion);

        foreach ($validated->professionalLinks as $link) {
            $category = $link['type'] ?? 'other';
            $created += $this->createEntitySuggestion($document, $run, 'social_link', $category, $link, $profile, $schemaVersion);
        }

        foreach ($validated->experiences as $exp) {
            $created += $this->createEntitySuggestion($document, $run, 'experience', null, $exp, $profile, $schemaVersion);
        }

        foreach ($validated->projects as $proj) {
            $created += $this->createEntitySuggestion($document, $run, 'project', null, $proj, $profile, $schemaVersion);
        }

        foreach ($validated->education as $edu) {
            $created += $this->createEntitySuggestion($document, $run, 'education', null, $edu, $profile, $schemaVersion);
        }

        foreach ($validated->certifications as $cert) {
            $created += $this->createEntitySuggestion($document, $run, 'certification', null, $cert, $profile, $schemaVersion);
        }

        foreach ($validated->languages as $lang) {
            $created += $this->createEntitySuggestion($document, $run, 'language', null, $lang, $profile, $schemaVersion);
        }

        foreach ($validated->skills as $skill) {
            $created += $this->createEntitySuggestion($document, $run, 'skill', $skill['category'] ?? null, $skill, $profile, $schemaVersion);
        }

        if ($created === 0) {
            $document->update([
                'status' => CvDocumentStatus::Failed,
                'failure_reason' => 'AI analysis returned no valid suggestions.',
                'failure_code' => 'ai_no_suggestions',
            ]);
            $run->update([
                'status' => CvProcessingRunStatus::Failed,
                'failure_reason' => 'No valid suggestions generated',
                'failure_code' => 'ai_no_suggestions',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('AI analysis returned no valid suggestions.');
        }

        $document->update(['status' => CvDocumentStatus::ReadyForReview]);
        $run->update([
            'status' => CvProcessingRunStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Pre-fetch all existing suggestion dedup keys for a document in one query.
     *
     * @return array<string, true>
     */
    private function loadExistingDedupKeys(int $documentId): array
    {
        $existing = CvSuggestion::where('cv_document_id', $documentId)
            ->select('type', 'field_name')
            ->get();

        $keys = [];
        foreach ($existing as $suggestion) {
            $typeValue = $suggestion->type->value;
            $keys[$typeValue.'|'.($suggestion->field_name ?? '')] = true;
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $basicInfo
     */
    private function createBasicInformationSuggestions(CvDocument $document, CvProcessingRun $run, array $basicInfo, ?CandidateProfile $profile, string $schemaVersion): int
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

            $dedupKey = 'basic_information|'.$fieldName;
            if (isset($this->existingDedupKeys[$dedupKey])) {
                continue;
            }

            $currentValue = $this->getCurrentProfileField($profile, $fieldName);

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

            $this->existingDedupKeys[$dedupKey] = true;
            $count++;
        }

        return $count;
    }

    private function createFieldSuggestion(CvDocument $document, CvProcessingRun $run, string $type, ?string $category, string $fieldName, ?string $value, ?CandidateProfile $profile, string $schemaVersion): int
    {
        if ($value === null) {
            return 0;
        }

        $dedupKey = $type.'|'.$fieldName;
        if (isset($this->existingDedupKeys[$dedupKey])) {
            return 0;
        }

        $currentValue = $this->getCurrentProfileField($profile, $fieldName);

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

        $this->existingDedupKeys[$dedupKey] = true;

        return 1;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function createEntitySuggestion(CvDocument $document, CvProcessingRun $run, string $type, ?string $category, array $entity, ?CandidateProfile $profile, string $schemaVersion): int
    {
        $entityWithoutSource = $entity;
        unset($entityWithoutSource['source']);

        $sourceText = $entity['source']['text'] ?? null;
        $sourcePage = $entity['source']['page'] ?? null;

        $dedupKey = $this->getDedupKey($type, $entityWithoutSource);
        $dedupIndex = $type.'|'.($dedupKey ?? '');

        if (isset($this->existingDedupKeys[$dedupIndex])) {
            return 0;
        }

        $currentValue = $this->getCurrentEntityValue($profile, $type, $entityWithoutSource);

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

        $this->existingDedupKeys[$dedupIndex] = true;

        return 1;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function getDedupKey(string $type, array $entity): ?string
    {
        return match ($type) {
            'social_link' => isset($entity['type']) ? ProfileIdentityService::normalize((string) $entity['type']) : null,
            'experience', 'education', 'project', 'certification' => ProfileIdentityService::profileItemKey($type, $entity),
            'language' => ProfileIdentityService::languageKey((string) ($entity['language'] ?? '')),
            'skill' => ProfileIdentityService::normalize((string) ($entity['name'] ?? '')),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentEntityValue(?CandidateProfile $profile, string $type, array $entity): ?array
    {
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

    private function getCurrentProfileField(?CandidateProfile $profile, string $field): ?array
    {
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
        return ProfileIdentityService::findExistingLanguage(
            $profile->languages ?? [],
            (string) ($entity['language'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>|null
     */
    private function getCurrentProfileItem(CandidateProfile $profile, array $entity, string $type): ?array
    {
        $existing = ProfileIdentityService::findExistingProfileItem($profile->items, $type, $entity);

        if ($existing) {
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

        $possible = ProfileIdentityService::findPossibleDuplicateProfileItem($profile->items, $type, $entity);

        if ($possible !== null) {
            $existingPossible = $possible['existing'];

            return [
                'id' => $existingPossible->id,
                'title' => $existingPossible->title,
                'organization' => $existingPossible->organization,
                'location' => $existingPossible->location,
                'start_date' => $existingPossible->start_date?->toIso8601String(),
                'end_date' => $existingPossible->end_date?->toIso8601String(),
                'description' => $existingPossible->description,
                'is_possible_duplicate' => true,
                'similarity' => round($possible['similarity'], 2),
                'reason' => $possible['reason'],
            ];
        }

        return null;
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

        if ($this->cachedSkillMaps === null) {
            $this->cachedSkillMaps = ProfileIdentityService::buildSkillLookupMaps(Skill::all(), SkillAlias::all());
        }

        $existing = ProfileIdentityService::findExistingCandidateSkill(
            $profile->candidateSkills,
            $skillName,
            $this->cachedSkillMaps['skills'],
            $this->cachedSkillMaps['aliases'],
        );

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

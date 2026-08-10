<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySkill;
use App\Models\JobRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmOpportunityAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
        private GeneratePreviewAction $previewAction,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion, string $versionToken): JobOpportunity
    {
        return DB::transaction(function () use ($ingestion, $versionToken): JobOpportunity {
            $lockedIngestion = JobOpportunityIngestion::query()
                ->whereKey($ingestion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIngestion->status === JobIngestionStatus::Confirmed) {
                return JobOpportunity::query()
                    ->where('ingestion_id', $lockedIngestion->id)
                    ->with(['requirements', 'skills', 'company'])
                    ->firstOrFail();
            }

            $this->stateService->assertReviewable($lockedIngestion->status);

            $suggestions = $lockedIngestion->suggestions()
                ->lockForUpdate()
                ->get();

            if ($suggestions->isEmpty()) {
                throw new ConflictException(
                    'No reviewed suggestions are available for confirmation.',
                    'incomplete_review',
                );
            }

            $preview = $this->previewAction->execute($lockedIngestion);

            if ($preview['version_token'] !== $versionToken) {
                throw new ConflictException(
                    'Preview is outdated. Please regenerate before confirming.',
                    'stale_preview',
                );
            }

            $candidateProfile = User::query()
                ->findOrFail($lockedIngestion->user_id)
                ->candidateProfile()
                ->firstOrCreate([], ['profile_completion' => 0]);
            $previewData = $preview['data'];

            $opportunity = JobOpportunity::query()->create(
                $this->buildOpportunityData($lockedIngestion, $previewData, $candidateProfile)
            );

            $this->createRequirements($opportunity, $previewData);
            $this->createSkills($opportunity, $previewData);

            $lockedIngestion->update([
                'status' => JobIngestionStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            return $opportunity->load(['requirements', 'skills', 'company']);
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $previewData
     * @return array<string, mixed>
     */
    private function buildOpportunityData(
        JobOpportunityIngestion $ingestion,
        array $previewData,
        CandidateProfile $profile,
    ): array {
        $overview = $this->section($previewData, 'overview');
        $workDetails = $this->section($previewData, 'work_details');
        $compensation = $this->section($previewData, 'compensation');
        $dates = $this->section($previewData, 'dates');

        return [
            'candidate_profile_id' => $profile->id,
            'ingestion_id' => $ingestion->id,
            'title' => $this->stringValue($overview, 'title') ?? 'Untitled Position',
            'company_name' => $this->stringValue($overview, 'company'),
            'department' => $this->stringValue($overview, 'department'),
            'external_reference' => $this->stringValue($overview, 'external_reference'),
            'summary' => $this->stringValue($overview, 'summary'),
            'application_url' => $this->stringValue($overview, 'application_url'),
            'personal_label' => $ingestion->personal_label,
            'source_url' => $ingestion->source_url,
            'city' => $this->stringValue($workDetails, 'city'),
            'region' => $this->stringValue($workDetails, 'region'),
            'country' => $this->stringValue($workDetails, 'country'),
            'work_mode' => $this->stringValue($workDetails, 'work_mode'),
            'contract_type' => $this->stringValue($workDetails, 'contract_type'),
            'seniority_level' => $this->stringValue($workDetails, 'seniority_level'),
            'working_hours' => $this->stringValue($workDetails, 'working_hours'),
            'travel_required' => $this->booleanValue($workDetails, 'travel_required'),
            'relocation_required' => $this->booleanValue($workDetails, 'relocation_required'),
            'salary_min' => $compensation['salary_min'] ?? null,
            'salary_max' => $compensation['salary_max'] ?? null,
            'salary_currency' => $this->stringValue($compensation, 'currency'),
            'salary_period' => $this->stringValue($compensation, 'period'),
            'compensation_text' => $this->stringValue($compensation, 'text'),
            'benefits' => $this->listValue($compensation, 'benefits'),
            'publication_date' => $this->stringValue($dates, 'publication_date'),
            'application_deadline' => $this->stringValue($dates, 'application_deadline'),
            'expected_start_date' => $this->stringValue($dates, 'expected_start_date'),
            'employment_duration' => $this->stringValue($dates, 'employment_duration'),
            'additional_requirements' => $this->listValue($previewData, 'additional_requirements'),
            'source_hash' => $ingestion->content_hash,
            'saved_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $previewData
     */
    private function createRequirements(JobOpportunity $opportunity, array $previewData): void
    {
        $order = 0;

        foreach ($this->listValue($previewData, 'responsibilities') as $item) {
            if (! is_array($item) || ! is_string($item['text'] ?? null)) {
                continue;
            }

            $this->createRequirement($opportunity, [
                'category' => 'responsibility',
                'content' => $item['text'],
                'source_evidence' => $this->nullableString($item['source_evidence'] ?? null),
                'display_order' => $order++,
            ]);
        }

        foreach ([
            'required_experience' => 'required',
            'preferred_experience' => 'preferred',
        ] as $section => $classification) {
            foreach ($this->listValue($previewData, $section) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $summary = $this->nullableString($item['summary'] ?? null) ?? '';
                $years = is_numeric($item['years'] ?? null) ? $item['years'] : null;
                $content = $summary;

                if ($years !== null) {
                    $content .= ($content !== '' ? ' ' : '')."({$years} years)";
                }

                if ($content === '') {
                    continue;
                }

                $this->createRequirement($opportunity, [
                    'category' => $section,
                    'content' => $content,
                    'classification' => $classification,
                    'source_evidence' => $this->nullableString($item['source_evidence'] ?? null),
                    'display_order' => $order++,
                ]);
            }
        }

        foreach ($this->listValue($previewData, 'education') as $item) {
            if (! is_array($item) || ! is_string($item['degree'] ?? null)) {
                continue;
            }

            $content = $item['degree'];
            $field = $this->nullableString($item['field'] ?? null);

            if ($field !== null) {
                $content .= " in {$field}";
            }

            $this->createRequirement($opportunity, [
                'category' => 'education',
                'content' => $content,
                'classification' => $this->classification($item['required'] ?? null),
                'source_evidence' => $this->nullableString($item['source_evidence'] ?? null),
                'display_order' => $order++,
            ]);
        }
        foreach ($this->listValue($previewData, 'languages_certifications') as $item) {
            if (! is_array($item) || ! is_string($item['name'] ?? null)) {
                continue;
            }

            $isLanguage = ($item['type'] ?? null) === 'language';
            $classification = $this->classification($item['required'] ?? null);

            $this->createRequirement($opportunity, [
                'category' => $isLanguage
                    ? 'language'
                    : ($classification === 'preferred' ? 'preferred_certification' : 'required_certification'),
                'content' => $item['name'],
                'classification' => $classification,
                'language' => $isLanguage ? $item['name'] : null,
                'language_proficiency' => $isLanguage
                    ? $this->nullableString($item['proficiency'] ?? null)
                    : null,
                'source_evidence' => $this->nullableString($item['source_evidence'] ?? null),
                'display_order' => $order++,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $previewData
     */
    private function createSkills(JobOpportunity $opportunity, array $previewData): void
    {
        $order = 0;

        foreach ([
            'required_skills' => 'required',
            'preferred_skills' => 'preferred',
        ] as $section => $classification) {
            foreach ($this->listValue($previewData, $section) as $item) {
                if (! is_array($item) || ! is_string($item['label'] ?? null)) {
                    continue;
                }

                JobOpportunitySkill::query()->create([
                    'job_opportunity_id' => $opportunity->id,
                    'skill_id' => is_numeric($item['resolved_skill_id'] ?? null)
                        ? (int) $item['resolved_skill_id']
                        : null,
                    'original_label' => $item['label'],
                    'classification' => $classification,
                    'proficiency' => $this->nullableString($item['proficiency'] ?? null),
                    'years_experience' => is_numeric($item['years_experience'] ?? null)
                        ? $item['years_experience']
                        : null,
                    'source_evidence' => $this->nullableString($item['source_evidence'] ?? null),
                    'display_order' => $order++,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRequirement(JobOpportunity $opportunity, array $attributes): void
    {
        $requirement = new JobRequirement;
        $requirement->fill([
            'job_opportunity_id' => $opportunity->id,
            ...$attributes,
        ]);
        $requirement->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function section(array $data, string $key): array
    {
        $section = $data[$key] ?? null;

        return is_array($section) ? $section : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private function listValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringValue(array $data, string $key): ?string
    {
        return $this->nullableString($data[$key] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function booleanValue(array $data, string $key): ?bool
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (strcasecmp($trimmed, 'null') === 0) {
            return null;
        }

        return $trimmed;
    }

    private function classification(mixed $required): ?string
    {
        if (! is_bool($required)) {
            return null;
        }

        return $required ? 'required' : 'preferred';
    }
}

<?php

namespace App\Domain\Opportunities\Services;

use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Data\SuggestionData;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Exceptions\Api\ConflictException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class JobAnalysisSchemaValidator
{
    public function validate(JobAnalysisResult $result): array
    {
        $job = $result->job;
        $expectedSchemaVersion = Config::string('job-ingestion.analysis_schema_version', '1.0.0');

        $validator = Validator::make([
            'schema_version' => $result->schemaVersion,
            'job' => $job,
            'warnings' => $result->warnings,
        ], $this->rules($expectedSchemaVersion));

        if ($validator->fails()) {
            throw new ConflictException(
                'AI output did not match the required job analysis schema.',
                'invalid_ai_output',
                [
                    'processing_stage' => 'schema_validation',
                    'provider_response_received' => true,
                    'validation_paths' => array_keys($validator->errors()->toArray()),
                ],
            );
        }

        $suggestions = [];
        $warnings = $result->warnings;
        $schemaVersion = Config::string('job-ingestion.analysis_schema_version', '1.0.0');

        $suggestions = array_merge($suggestions, $this->mapOverviewSuggestions($job, $schemaVersion));
        $suggestions = array_merge($suggestions, $this->mapWorkDetailsSuggestions($job, $schemaVersion));
        $suggestions = array_merge($suggestions, $this->mapResponsibilitiesSuggestions($job, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapExperienceSuggestions($job, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapEducationSuggestions($job, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapSkillSuggestions($job, 'required_skills', 'required_skills', SuggestionType::RequiredSkill, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapSkillSuggestions($job, 'preferred_skills', 'preferred_skills', SuggestionType::PreferredSkill, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapLanguageSuggestions($job, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapCertificationSuggestions($job, $schemaVersion, $warnings));
        $suggestions = array_merge($suggestions, $this->mapCompensationSuggestions($job, $schemaVersion));
        $suggestions = array_merge($suggestions, $this->mapDateSuggestions($job, $schemaVersion));

        return [
            'suggestions' => $suggestions,
            'warnings' => $warnings,
        ];
    }

    private function rules(string $schemaVersion): array
    {
        return [
            'schema_version' => ['required', 'string', "in:{$schemaVersion}"],
            'job' => ['required', 'array'],
            'job.title' => ['required', 'string', 'max:255'],
            'job.company' => ['sometimes', 'nullable', 'string'],
            'job.department' => ['sometimes', 'nullable', 'string'],
            'job.external_reference' => ['sometimes', 'nullable', 'string'],
            'job.summary' => ['sometimes', 'nullable', 'string'],
            'job.application_url' => ['sometimes', 'nullable', 'string'],
            'job.location' => ['sometimes', 'nullable', 'array'],
            'job.location.city' => ['sometimes', 'nullable', 'string'],
            'job.location.region' => ['sometimes', 'nullable', 'string'],
            'job.location.country' => ['sometimes', 'nullable', 'string'],
            'job.work_mode' => ['sometimes', 'nullable', 'in:remote,hybrid,on_site'],
            'job.contract_type' => ['sometimes', 'nullable', 'in:full-time,part-time,contract,internship,freelance'],
            'job.seniority_level' => ['sometimes', 'nullable', 'string'],
            'job.working_hours' => ['sometimes', 'nullable', 'string'],
            'job.travel_required' => ['sometimes', 'nullable', 'boolean'],
            'job.relocation_required' => ['sometimes', 'nullable', 'boolean'],
            'job.responsibilities' => ['sometimes', 'array'],
            'job.responsibilities.*' => ['array'],
            'job.responsibilities.*.text' => ['required', 'string'],
            'job.responsibilities.*.source' => ['sometimes', 'nullable', 'string'],
            'job.required_experience' => ['sometimes', 'array'],
            'job.required_experience.*.summary' => ['required', 'string'],
            'job.required_experience.*.years' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job.required_experience.*.source' => ['sometimes', 'nullable', 'string'],
            'job.preferred_experience' => ['sometimes', 'array'],
            'job.preferred_experience.*.summary' => ['required', 'string'],
            'job.preferred_experience.*.years' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job.preferred_experience.*.source' => ['sometimes', 'nullable', 'string'],
            'job.education_requirements' => ['sometimes', 'array'],
            'job.education_requirements.*.degree' => ['required', 'string'],
            'job.education_requirements.*.field' => ['sometimes', 'nullable', 'string'],
            'job.education_requirements.*.required' => ['sometimes', 'nullable', 'boolean'],
            'job.education_requirements.*.equivalent_experience' => ['sometimes', 'nullable', 'string'],
            'job.education_requirements.*.source' => ['sometimes', 'nullable', 'string'],
            'job.required_skills' => ['sometimes', 'array'],
            'job.required_skills.*.label' => ['required', 'string'],
            'job.required_skills.*.proficiency' => ['sometimes', 'nullable', 'string'],
            'job.required_skills.*.years_experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job.required_skills.*.source' => ['sometimes', 'nullable', 'string'],
            'job.preferred_skills' => ['sometimes', 'array'],
            'job.preferred_skills.*.label' => ['required', 'string'],
            'job.preferred_skills.*.proficiency' => ['sometimes', 'nullable', 'string'],
            'job.preferred_skills.*.years_experience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'job.preferred_skills.*.source' => ['sometimes', 'nullable', 'string'],
            'job.languages' => ['sometimes', 'array'],
            'job.languages.*.language' => ['required', 'string'],
            'job.languages.*.required' => ['sometimes', 'nullable', 'boolean'],
            'job.languages.*.proficiency' => ['sometimes', 'nullable', 'string'],
            'job.languages.*.source' => ['sometimes', 'nullable', 'string'],
            'job.certifications' => ['sometimes', 'array'],
            'job.certifications.*.name' => ['required', 'string'],
            'job.certifications.*.required' => ['sometimes', 'nullable', 'boolean'],
            'job.certifications.*.source' => ['sometimes', 'nullable', 'string'],
            'job.compensation' => ['sometimes', 'nullable', 'array'],
            'job.compensation.salary_min' => ['sometimes', 'nullable', 'numeric'],
            'job.compensation.salary_max' => ['sometimes', 'nullable', 'numeric'],
            'job.compensation.currency' => ['sometimes', 'nullable', 'string'],
            'job.compensation.period' => ['sometimes', 'nullable', 'in:yearly,monthly,hourly,daily'],
            'job.compensation.text' => ['sometimes', 'nullable', 'string'],
            'job.benefits' => ['sometimes', 'array'],
            'job.benefits.*' => ['string'],
            'job.publication_date' => ['sometimes', 'nullable', 'string'],
            'job.application_deadline' => ['sometimes', 'nullable', 'string'],
            'job.expected_start_date' => ['sometimes', 'nullable', 'string'],
            'job.employment_duration' => ['sometimes', 'nullable', 'string'],
            'job.additional_requirements' => ['sometimes', 'array'],
            'job.additional_requirements.*' => ['string'],
            'warnings' => ['present', 'array'],
            'warnings.*' => ['string'],
        ];
    }

    /**
     * Normalize a raw source evidence string coming from the LLM.
     * Returns null for empty, overly long, or degenerate values.
     */
    private function normalizeEvidence(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        // Strip trailing colons / dashes / bullets that commonly appear with headings
        $trimmed = rtrim($trimmed, " :\t-–—•");
        $trimmed = trim($trimmed);

        if ($trimmed === '') {
            return null;
        }

        // Hard cap — prevents storing an entire job description as evidence.
        if (mb_strlen($trimmed) > 500) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Detect whether a group of evidences is degenerate (all identical short headings).
     * E.g. every responsibility has "Missions principales" — should be discarded.
     */
    private function isDegenerateEvidenceGroup(array $evidences): bool
    {
        $nonNull = array_values(array_filter($evidences, fn (?string $v) => $v !== null && trim($v) !== ''));

        if (count($nonNull) < 2) {
            return false;
        }

        $first = mb_strtolower(trim($nonNull[0]));

        foreach ($nonNull as $ev) {
            if (mb_strtolower(trim((string) $ev)) !== $first) {
                return false;
            }
        }

        $sample = trim($nonNull[0]);

        // Headings are short (< 40 chars) and contain no sentence-ending punctuation.
        if (mb_strlen($sample) >= 40) {
            return false;
        }

        if (preg_match('/[.!?]/u', $sample)) {
            return false;
        }

        return true;
    }

    private function mapOverviewSuggestions(array $job, string $schemaVersion): array
    {
        $suggestions = [];
        $mappings = [
            'title' => [SuggestionType::JobTitle, 'overview', null],
            'company' => [SuggestionType::Company, 'overview', null],
            'department' => [SuggestionType::Department, 'overview', null],
            'external_reference' => [SuggestionType::ExternalReference, 'overview', null],
            'summary' => [SuggestionType::Summary, 'overview', null],
            'application_url' => [SuggestionType::ApplicationUrl, 'overview', null],
        ];

        foreach ($mappings as $key => [$type, $group, $field]) {
            if (JobValueMeaningfulness::isMeaningfulString($job[$key] ?? null)) {
                $suggestions[] = new SuggestionData(
                    type: $type->value,
                    groupKey: $group,
                    field: $field,
                    extractedValue: ['value' => $job[$key]],
                    sourceEvidence: $this->normalizeEvidence($job['source'] ?? null),
                    schemaVersion: $schemaVersion,
                );
            }
        }

        return $suggestions;
    }

    private function mapWorkDetailsSuggestions(array $job, string $schemaVersion): array
    {
        $suggestions = [];

        $location = $job['location'] ?? [];
        if (is_array($location)) {
            $locationMappings = [
                'city' => SuggestionType::City,
                'region' => SuggestionType::Region,
                'country' => SuggestionType::Country,
            ];
            foreach ($locationMappings as $key => $type) {
                if (JobValueMeaningfulness::isMeaningfulString($location[$key] ?? null)) {
                    $suggestions[] = new SuggestionData(
                        type: $type->value,
                        groupKey: 'work_details',
                        field: $key,
                        extractedValue: ['value' => $location[$key]],
                        sourceEvidence: $this->normalizeEvidence($location['source'] ?? null),
                        schemaVersion: $schemaVersion,
                    );
                }
            }
        }

        $scalarMappings = [
            'work_mode' => SuggestionType::WorkMode,
            'contract_type' => SuggestionType::ContractType,
            'seniority_level' => SuggestionType::SeniorityLevel,
            'working_hours' => SuggestionType::WorkingHours,
        ];

        foreach ($scalarMappings as $key => $type) {
            if (JobValueMeaningfulness::isMeaningfulString($job[$key] ?? null)) {
                $suggestions[] = new SuggestionData(
                    type: $type->value,
                    groupKey: 'work_details',
                    field: $key,
                    extractedValue: ['value' => $job[$key]],
                    sourceEvidence: $this->normalizeEvidence($job['source'] ?? null),
                    schemaVersion: $schemaVersion,
                );
            }
        }

        foreach (['travel_required', 'relocation_required'] as $key) {
            if (array_key_exists($key, $job) && is_bool($job[$key])) {
                $type = $key === 'travel_required' ? SuggestionType::TravelRequired : SuggestionType::RelocationRequired;
                $suggestions[] = new SuggestionData(
                    type: $type->value,
                    groupKey: 'work_details',
                    field: $key,
                    extractedValue: ['value' => $job[$key]],
                    sourceEvidence: null,
                    schemaVersion: $schemaVersion,
                );
            }
        }

        return $suggestions;
    }

    private function mapResponsibilitiesSuggestions(array $job, string $schemaVersion, array &$warnings): array
    {
        $raw = $job['responsibilities'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $filtered = [];
        foreach ($raw as $item) {
            if (is_array($item) && JobValueMeaningfulness::isMeaningfulResponsibility($item)) {
                $filtered[] = $item;
            }
        }

        if ($filtered === []) {
            return [];
        }

        $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

        if ($this->isDegenerateEvidenceGroup($evidences)) {
            $evidences = array_fill(0, count($evidences), null);
            $warnings[] = 'responsibilities: source evidence was generic section labels; removed';
        }

        $suggestions = [];
        foreach ($filtered as $idx => $item) {
            $suggestions[] = new SuggestionData(
                type: SuggestionType::Responsibility->value,
                groupKey: 'responsibilities',
                field: null,
                extractedValue: ['text' => $item['text']],
                sourceEvidence: $evidences[$idx],
                schemaVersion: $schemaVersion,
            );
        }

        return $suggestions;
    }

    private function mapExperienceSuggestions(array $job, string $schemaVersion, array &$warnings): array
    {
        $suggestions = [];

        foreach (['required_experience', 'preferred_experience'] as $key) {
            $items = $job[$key] ?? [];
            if (! is_array($items)) {
                continue;
            }

            $filtered = [];
            foreach ($items as $item) {
                if (is_array($item) && JobValueMeaningfulness::isMeaningfulExperience($item)) {
                    $filtered[] = $item;
                }
            }

            if ($filtered === []) {
                continue;
            }

            $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

            if ($this->isDegenerateEvidenceGroup($evidences)) {
                $evidences = array_fill(0, count($evidences), null);
                $warnings[] = "{$key}: source evidence was generic section labels; removed";
            }

            $type = $key === 'required_experience' ? SuggestionType::RequiredExperience : SuggestionType::PreferredExperience;
            $classification = $key === 'required_experience' ? 'required' : 'preferred';

            foreach ($filtered as $idx => $item) {
                $suggestions[] = new SuggestionData(
                    type: $type->value,
                    groupKey: 'experience',
                    field: $classification,
                    extractedValue: $item,
                    sourceEvidence: $evidences[$idx],
                    schemaVersion: $schemaVersion,
                );
            }
        }

        return $suggestions;
    }

    private function mapEducationSuggestions(array $job, string $schemaVersion, array &$warnings): array
    {
        $items = $job['education_requirements'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $filtered = [];
        foreach ($items as $item) {
            if (is_array($item) && JobValueMeaningfulness::isMeaningfulEducation($item)) {
                $filtered[] = $item;
            }
        }

        if ($filtered === []) {
            return [];
        }

        $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

        if ($this->isDegenerateEvidenceGroup($evidences)) {
            $evidences = array_fill(0, count($evidences), null);
            $warnings[] = 'education_requirements: source evidence was generic section labels; removed';
        }

        $suggestions = [];
        foreach ($filtered as $idx => $item) {
            $suggestions[] = new SuggestionData(
                type: SuggestionType::Education->value,
                groupKey: 'education',
                field: null,
                extractedValue: $item,
                sourceEvidence: $evidences[$idx],
                schemaVersion: $schemaVersion,
            );
        }

        return $suggestions;
    }

    private function mapSkillSuggestions(array $job, string $jobKey, string $groupKey, SuggestionType $type, string $schemaVersion, array &$warnings): array
    {
        $items = $job[$jobKey] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $filtered = [];
        foreach ($items as $item) {
            if (is_array($item) && JobValueMeaningfulness::isMeaningfulSkill($item)) {
                $filtered[] = $item;
            }
        }

        if ($filtered === []) {
            return [];
        }

        $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

        if ($this->isDegenerateEvidenceGroup($evidences)) {
            $evidences = array_fill(0, count($evidences), null);
            $warnings[] = "{$jobKey}: source evidence was generic section labels; removed";
        }

        $suggestions = [];
        foreach ($filtered as $idx => $item) {
            $suggestions[] = new SuggestionData(
                type: $type->value,
                groupKey: $groupKey,
                field: null,
                extractedValue: $item,
                sourceEvidence: $evidences[$idx],
                schemaVersion: $schemaVersion,
            );
        }

        return $suggestions;
    }

    private function mapLanguageSuggestions(array $job, string $schemaVersion, array &$warnings): array
    {
        $items = $job['languages'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $filtered = [];
        foreach ($items as $item) {
            if (is_array($item) && JobValueMeaningfulness::isMeaningfulLanguage($item)) {
                $filtered[] = $item;
            }
        }

        if ($filtered === []) {
            return [];
        }

        $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

        if ($this->isDegenerateEvidenceGroup($evidences)) {
            $evidences = array_fill(0, count($evidences), null);
            $warnings[] = 'languages: source evidence was generic section labels; removed';
        }

        $suggestions = [];
        foreach ($filtered as $idx => $item) {
            $suggestions[] = new SuggestionData(
                type: SuggestionType::Language->value,
                groupKey: 'languages_certifications',
                field: null,
                extractedValue: $item,
                sourceEvidence: $evidences[$idx],
                schemaVersion: $schemaVersion,
            );
        }

        return $suggestions;
    }

    private function mapCertificationSuggestions(array $job, string $schemaVersion, array &$warnings): array
    {
        $items = $job['certifications'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $filtered = [];
        foreach ($items as $item) {
            if (is_array($item) && JobValueMeaningfulness::isMeaningfulCertification($item)) {
                $filtered[] = $item;
            }
        }

        if ($filtered === []) {
            return [];
        }

        $evidences = array_map(fn (array $item) => $this->normalizeEvidence($item['source'] ?? null), $filtered);

        if ($this->isDegenerateEvidenceGroup($evidences)) {
            $evidences = array_fill(0, count($evidences), null);
            $warnings[] = 'certifications: source evidence was generic section labels; removed';
        }

        $suggestions = [];
        foreach ($filtered as $idx => $item) {
            $suggestions[] = new SuggestionData(
                type: SuggestionType::Certification->value,
                groupKey: 'languages_certifications',
                field: null,
                extractedValue: $item,
                sourceEvidence: $evidences[$idx],
                schemaVersion: $schemaVersion,
            );
        }

        return $suggestions;
    }

    private function mapCompensationSuggestions(array $job, string $schemaVersion): array
    {
        $suggestions = [];
        $comp = $job['compensation'] ?? [];

        if (is_array($comp) && ! empty($comp)) {
            $hasData = false;
            $clean = [];

            foreach (['salary_min', 'salary_max', 'currency', 'period', 'text'] as $key) {
                if (isset($comp[$key])) {
                    if (is_string($comp[$key])) {
                        $trimmed = trim($comp[$key]);
                        if ($trimmed === '') {
                            continue;
                        }
                        $clean[$key] = $trimmed;
                    } elseif (is_numeric($comp[$key])) {
                        $clean[$key] = $comp[$key];
                    } else {
                        continue;
                    }
                    $hasData = true;
                }
            }

            if ($hasData) {
                $suggestions[] = new SuggestionData(
                    type: SuggestionType::Compensation->value,
                    groupKey: 'compensation',
                    field: null,
                    extractedValue: $clean,
                    sourceEvidence: null,
                    schemaVersion: $schemaVersion,
                );
            }
        }

        $benefits = $job['benefits'] ?? [];
        if (is_array($benefits)) {
            foreach ($benefits as $benefit) {
                if (JobValueMeaningfulness::isMeaningfulBenefit($benefit)) {
                    $suggestions[] = new SuggestionData(
                        type: SuggestionType::Benefit->value,
                        groupKey: 'compensation',
                        field: null,
                        extractedValue: ['name' => trim($benefit)],
                        sourceEvidence: null,
                        schemaVersion: $schemaVersion,
                    );
                }
            }
        }

        return $suggestions;
    }

    private function mapDateSuggestions(array $job, string $schemaVersion): array
    {
        $suggestions = [];
        $mappings = [
            'publication_date' => SuggestionType::PublicationDate,
            'application_deadline' => SuggestionType::ApplicationDeadline,
            'expected_start_date' => SuggestionType::ExpectedStartDate,
            'employment_duration' => SuggestionType::EmploymentDuration,
        ];

        foreach ($mappings as $key => $type) {
            if (JobValueMeaningfulness::isMeaningfulDate($job[$key] ?? null)) {
                $suggestions[] = new SuggestionData(
                    type: $type->value,
                    groupKey: 'dates',
                    field: $key,
                    extractedValue: ['value' => $job[$key]],
                    sourceEvidence: null,
                    schemaVersion: $schemaVersion,
                );
            }
        }

        $additional = $job['additional_requirements'] ?? [];
        if (is_array($additional)) {
            foreach ($additional as $req) {
                if (JobValueMeaningfulness::isMeaningfulAdditionalRequirement($req)) {
                    $suggestions[] = new SuggestionData(
                        type: SuggestionType::AdditionalRequirement->value,
                        groupKey: 'additional',
                        field: null,
                        extractedValue: ['text' => trim($req)],
                        sourceEvidence: null,
                        schemaVersion: $schemaVersion,
                    );
                }
            }
        }

        return $suggestions;
    }
}

<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Domain\Opportunities\Services\JobValueMeaningfulness;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

class GeneratePreviewAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion): array
    {
        $this->stateService->assertReviewable($ingestion->status);

        $suggestions = $ingestion->suggestions()
            ->orderBy('id')
            ->get();

        $pendingMandatory = $suggestions->filter(fn ($s) => $s->review_decision === ReviewDecision::Pending)->count();

        if ($pendingMandatory > 0) {
            throw new ConflictException(
                "{$pendingMandatory} suggestions still pending review.",
                'incomplete_review',
            );
        }

        $ambiguousSkills = $suggestions->filter(fn (JobOpportunitySuggestion $suggestion): bool => (
            $suggestion->resolution === SkillResolutionState::Ambiguous
            && ! in_array($suggestion->review_decision, [
                ReviewDecision::Rejected,
                ReviewDecision::KeepBlank,
            ], true)
        ));

        if ($ambiguousSkills->isNotEmpty()) {
            throw new ConflictException(
                'Ambiguous skills must be resolved before preview.',
                'unresolved_skill_mapping',
            );
        }

        $accepted = $suggestions->filter(fn (JobOpportunitySuggestion $suggestion): bool => in_array($suggestion->review_decision, [
            ReviewDecision::Accepted, ReviewDecision::Edited, ReviewDecision::Resolved,
        ], true));

        $excluded = $suggestions->filter(fn (JobOpportunitySuggestion $suggestion): bool => in_array($suggestion->review_decision, [
            ReviewDecision::Rejected, ReviewDecision::KeepBlank,
        ], true));

        $unknownSkills = $accepted->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->resolution === SkillResolutionState::Unknown
        );

        $overview = $this->buildOverview($accepted);
        $overview['title'] ??= 'Untitled Position';

        $data = [
            'overview' => $overview,
            'work_details' => $this->buildWorkDetails($accepted),
            'responsibilities' => $this->buildListItems($accepted, SuggestionType::Responsibility->value),
            'required_experience' => $this->buildRequirementItems($accepted, 'required_experience'),
            'preferred_experience' => $this->buildRequirementItems($accepted, 'preferred_experience'),
            'education' => $this->buildEducationItems($accepted),
            'required_skills' => $this->buildSkillItems($accepted, 'required_skills'),
            'preferred_skills' => $this->buildSkillItems($accepted, 'preferred_skills'),
            'languages_certifications' => $this->buildLanguagesCerts($accepted),
            'compensation' => $this->buildCompensation($accepted),
            'dates' => $this->buildDates($accepted),
            'additional_requirements' => $this->buildAdditionalRequirements($accepted),
            'excluded' => $excluded->values(),
            'unknown_skills' => $unknownSkills->values(),
            'warnings' => [],
        ];

        $versionToken = $this->generateVersionToken($ingestion, $suggestions);
        $schemaVersion = Config::string('job-ingestion.analysis_schema_version', '1.0.0');

        return [
            'data' => $data,
            'version_token' => $versionToken,
            'schema_version' => $schemaVersion,
        ];
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function generateVersionToken(JobOpportunityIngestion $ingestion, Collection $suggestions): string
    {
        $schemaVersion = Config::string('job-ingestion.analysis_schema_version', '1.0.0');

        $payload = json_encode([
            'ingestion_id' => $ingestion->id,
            'ingestion_version' => $ingestion->version,
            'content_hash' => $ingestion->content_hash,
            'schema_version' => $schemaVersion,
            'total_suggestions' => $suggestions->count(),
            'decisions' => $suggestions->map(fn (JobOpportunitySuggestion $suggestion): array => [
                'id' => $suggestion->id,
                'decision' => $suggestion->review_decision->value,
                'edited_value' => $suggestion->edited_value,
                'resolution' => $suggestion->resolution?->value,
                'resolved_skill_id' => $suggestion->resolved_skill_id,
                'version' => $suggestion->version,
            ])->toArray(),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildOverview(Collection $suggestions): array
    {
        $overview = [];
        $fields = [
            'job_title' => 'title',
            'company' => 'company',
            'department' => 'department',
            'external_reference' => 'external_reference',
            'summary' => 'summary',
            'application_url' => 'application_url',
        ];

        foreach ($fields as $type => $key) {
            $suggestion = $suggestions->first(
                fn (JobOpportunitySuggestion $candidate): bool => $candidate->type->value === $type
            );

            if ($suggestion !== null) {
                $value = $this->effectiveValue($suggestion)['value'] ?? null;

                if ($value !== null) {
                    $overview[$key] = $value;
                }
            }
        }

        return $overview;
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildWorkDetails(Collection $suggestions): array
    {
        $details = [];
        $fields = [
            'city' => 'city', 'region' => 'region', 'country' => 'country',
            'work_mode' => 'work_mode', 'contract_type' => 'contract_type',
            'seniority_level' => 'seniority_level', 'working_hours' => 'working_hours',
            'travel_required' => 'travel_required', 'relocation_required' => 'relocation_required',
        ];

        foreach ($fields as $type => $key) {
            $suggestion = $suggestions->first(
                fn (JobOpportunitySuggestion $candidate): bool => $candidate->type->value === $type
            );

            if ($suggestion !== null) {
                $value = $this->effectiveValue($suggestion)['value'] ?? null;

                if ($value !== null) {
                    $details[$key] = $value;
                }
            }
        }

        return $details;
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildListItems(Collection $suggestions, string $type): array
    {
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->type->value === $type
        )->values();

        return $items->filter(fn (JobOpportunitySuggestion $suggestion): bool => JobValueMeaningfulness::isMeaningfulResponsibility(
            $this->effectiveValue($suggestion)
        ))->map(fn (JobOpportunitySuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'text' => $this->effectiveValue($suggestion)['text'] ?? '',
            'source_evidence' => $suggestion->source_evidence,
        ])->toArray();
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildRequirementItems(Collection $suggestions, string $classification): array
    {
        $types = $classification === 'required_experience' ? ['required_experience'] : ['preferred_experience'];
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => in_array($suggestion->type->value, $types, true)
        )->values();

        return $items->filter(fn (JobOpportunitySuggestion $suggestion): bool => JobValueMeaningfulness::isMeaningfulExperience(
            $this->effectiveValue($suggestion)
        ))->map(fn (JobOpportunitySuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'summary' => $this->effectiveValue($suggestion)['summary'] ?? '',
            'years' => $this->effectiveValue($suggestion)['years'] ?? null,
            'source_evidence' => $suggestion->source_evidence,
        ])->toArray();
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildEducationItems(Collection $suggestions): array
    {
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->type === SuggestionType::Education
        )->values();

        return $items->filter(fn (JobOpportunitySuggestion $suggestion): bool => JobValueMeaningfulness::isMeaningfulEducation(
            $this->effectiveValue($suggestion)
        ))->map(fn (JobOpportunitySuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'degree' => $this->effectiveValue($suggestion)['degree'] ?? '',
            'field' => $this->effectiveValue($suggestion)['field'] ?? null,
            'required' => $this->effectiveValue($suggestion)['required'] ?? null,
            'source_evidence' => $suggestion->source_evidence,
        ])->toArray();
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildSkillItems(Collection $suggestions, string $groupKey): array
    {
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->group_key === $groupKey
        )->values();

        $merged = [];

        foreach ($items as $suggestion) {
            $value = $this->effectiveValue($suggestion);

            if (! JobValueMeaningfulness::isMeaningfulSkill($value)) {
                continue;
            }

            $label = (string) ($value['label'] ?? '');
            $key = $suggestion->resolved_skill_id !== null
                ? 'skill:'.$suggestion->resolved_skill_id
                : 'label:'.mb_strtolower(trim($label));

            if (isset($merged[$key])) {
                $merged[$key]['source_evidence'] = $this->mergeEvidence(
                    $merged[$key]['source_evidence'],
                    $suggestion->source_evidence,
                );

                continue;
            }

            $merged[$key] = [
                'id' => $suggestion->id,
                'label' => $label,
                'proficiency' => $value['proficiency'] ?? null,
                'years_experience' => $value['years_experience'] ?? null,
                'resolution' => $suggestion->resolution->value,
                'resolved_skill_id' => $suggestion->resolved_skill_id,
                'source_evidence' => $suggestion->source_evidence,
            ];
        }

        return array_values($merged);
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildLanguagesCerts(Collection $suggestions): array
    {
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->group_key === 'languages_certifications'
        )->values();

        return $items->filter(function (JobOpportunitySuggestion $suggestion): bool {
            $item = $this->effectiveValue($suggestion);

            return $suggestion->type === SuggestionType::Language
                ? JobValueMeaningfulness::isMeaningfulLanguage($item)
                : JobValueMeaningfulness::isMeaningfulCertification($item);
        })->map(fn (JobOpportunitySuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'type' => $suggestion->type->value,
            'name' => $this->effectiveValue($suggestion)['name']
                ?? $this->effectiveValue($suggestion)['language']
                ?? '',
            'required' => $this->effectiveValue($suggestion)['required'] ?? null,
            'proficiency' => $this->effectiveValue($suggestion)['proficiency'] ?? null,
            'source_evidence' => $suggestion->source_evidence,
        ])->toArray();
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildAdditionalRequirements(Collection $suggestions): array
    {
        $items = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->type === SuggestionType::AdditionalRequirement
        )->values();

        return $items->filter(fn (JobOpportunitySuggestion $suggestion): bool => JobValueMeaningfulness::isMeaningfulAdditionalRequirement(
            $this->effectiveValue($suggestion)['text'] ?? null
        ))->map(fn (JobOpportunitySuggestion $suggestion): array => [
            'id' => $suggestion->id,
            'text' => $this->effectiveValue($suggestion)['text'] ?? '',
            'source_evidence' => $suggestion->source_evidence,
        ])->toArray();
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildCompensation(Collection $suggestions): ?array
    {
        $compensation = $suggestions->first(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->type === SuggestionType::Compensation
        );
        $benefits = $suggestions->filter(
            fn (JobOpportunitySuggestion $suggestion): bool => $suggestion->type === SuggestionType::Benefit
        )->map(fn (JobOpportunitySuggestion $suggestion): mixed => $this->effectiveValue($suggestion)['name'] ?? null)
            ->filter(fn (mixed $benefit): bool => JobValueMeaningfulness::isMeaningfulBenefit($benefit))
            ->values()
            ->all();

        if ($compensation === null && $benefits === []) {
            return null;
        }

        $value = $compensation === null ? [] : $this->effectiveValue($compensation);

        return [
            'salary_min' => $value['salary_min'] ?? null,
            'salary_max' => $value['salary_max'] ?? null,
            'currency' => $value['currency'] ?? null,
            'period' => $value['period'] ?? null,
            'text' => $value['text'] ?? null,
            'benefits' => $benefits,
        ];
    }

    /**
     * @param  Collection<int, JobOpportunitySuggestion>  $suggestions
     */
    private function buildDates(Collection $suggestions): array
    {
        $dates = [];
        $fields = ['publication_date', 'application_deadline', 'expected_start_date', 'employment_duration'];

        foreach ($fields as $type) {
            $suggestion = $suggestions->first(
                fn (JobOpportunitySuggestion $candidate): bool => $candidate->type->value === $type
            );

            if ($suggestion !== null) {
                $dates[$type] = $this->effectiveValue($suggestion)['value'] ?? null;
            }
        }

        return $dates;
    }

    /**
     * @return array<string, mixed>
     */
    private function effectiveValue(JobOpportunitySuggestion $suggestion): array
    {
        if ($suggestion->review_decision === ReviewDecision::Edited && $suggestion->edited_value !== null) {
            return $suggestion->edited_value;
        }

        return $suggestion->extracted_value;
    }

    private function mergeEvidence(?string $existing, ?string $additional): ?string
    {
        $evidence = array_values(array_unique(array_filter([
            $existing,
            $additional,
        ], fn (?string $value): bool => $value !== null && trim($value) !== '')));

        return $evidence === [] ? null : implode("\n", $evidence);
    }
}

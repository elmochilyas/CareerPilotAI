<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use Illuminate\Support\Facades\Config;

class GeneratePreviewAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion): array
    {
        $this->stateService->assertReviewable($ingestion->status);

        $suggestions = $ingestion->suggestions;

        $pendingMandatory = $suggestions->filter(fn ($s) => $s->review_decision === ReviewDecision::Pending)->count();

        if ($pendingMandatory > 0) {
            throw new ConflictException(
                "{$pendingMandatory} suggestions still pending review.",
                'incomplete_review',
            );
        }

        $ambiguousSkills = $suggestions->filter(
            fn ($s) => $s->resolution === SkillResolutionState::Ambiguous
        );

        if ($ambiguousSkills->isNotEmpty()) {
            throw new ConflictException(
                'Ambiguous skills must be resolved before preview.',
                'unresolved_skill_mapping',
            );
        }

        $accepted = $suggestions->filter(fn ($s) => in_array($s->review_decision, [
            ReviewDecision::Accepted, ReviewDecision::Edited, ReviewDecision::Resolved,
        ], true));

        $excluded = $suggestions->filter(fn ($s) => in_array($s->review_decision, [
            ReviewDecision::Rejected, ReviewDecision::KeepBlank,
        ], true));

        $unknownSkills = $suggestions->filter(
            fn ($s) => $s->resolution === SkillResolutionState::Unknown
        );

        $data = [
            'overview' => $this->buildOverview($accepted),
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

    private function generateVersionToken(JobOpportunityIngestion $ingestion, $suggestions): string
    {
        $payload = json_encode([
            'ingestion_id' => $ingestion->id,
            'ingestion_version' => $ingestion->version,
            'total_suggestions' => $suggestions->count(),
            'decisions' => $suggestions->map(fn ($s) => [
                'id' => $s->id,
                'decision' => $s->review_decision?->value,
                'version' => $s->version,
            ])->toArray(),
        ]);

        return hash('sha256', $payload);
    }

    private function buildOverview($suggestions): array
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
            $suggestion = $suggestions->firstWhere('type', $type);

            if ($suggestion !== null) {
                $value = $suggestion->review_decision === ReviewDecision::Edited && $suggestion->edited_value !== null
                    ? ($suggestion->edited_value['value'] ?? null)
                    : ($suggestion->extracted_value['value'] ?? null);

                if ($value !== null) {
                    $overview[$key] = $value;
                }
            }
        }

        return $overview;
    }

    private function buildWorkDetails($suggestions): array
    {
        $details = [];
        $fields = [
            'city' => 'city', 'region' => 'region', 'country' => 'country',
            'work_mode' => 'work_mode', 'contract_type' => 'contract_type',
            'seniority_level' => 'seniority_level', 'working_hours' => 'working_hours',
            'travel_required' => 'travel_required', 'relocation_required' => 'relocation_required',
        ];

        foreach ($fields as $type => $key) {
            $suggestion = $suggestions->firstWhere('type', $type);

            if ($suggestion !== null) {
                $value = $suggestion->review_decision === ReviewDecision::Edited && $suggestion->edited_value !== null
                    ? ($suggestion->edited_value['value'] ?? null)
                    : ($suggestion->extracted_value['value'] ?? null);

                if ($value !== null) {
                    $details[$key] = $value;
                }
            }
        }

        return $details;
    }

    private function buildListItems($suggestions, string $type): array
    {
        $items = $suggestions->filter(fn ($s) => $s->type->value === $type)->values();

        return $items->map(fn ($s) => [
            'id' => $s->id,
            'text' => $s->review_decision === ReviewDecision::Edited && $s->edited_value !== null
                ? ($s->edited_value['text'] ?? $s->extracted_value['text'] ?? '')
                : ($s->extracted_value['text'] ?? ''),
            'source_evidence' => $s->source_evidence,
        ])->toArray();
    }

    private function buildRequirementItems($suggestions, string $classification): array
    {
        $types = $classification === 'required_experience' ? ['required_experience'] : ['preferred_experience'];
        $items = $suggestions->filter(fn ($s) => in_array($s->type->value, $types, true))->values();

        return $items->map(fn ($s) => [
            'id' => $s->id,
            'summary' => $s->extracted_value['summary'] ?? '',
            'years' => $s->extracted_value['years'] ?? null,
            'source_evidence' => $s->source_evidence,
        ])->toArray();
    }

    private function buildEducationItems($suggestions): array
    {
        $items = $suggestions->filter(fn ($s) => $s->type->value === 'education')->values();

        return $items->map(fn ($s) => [
            'id' => $s->id,
            'degree' => $s->extracted_value['degree'] ?? '',
            'field' => $s->extracted_value['field'] ?? null,
            'required' => $s->extracted_value['required'] ?? null,
            'source_evidence' => $s->source_evidence,
        ])->toArray();
    }

    private function buildSkillItems($suggestions, string $groupKey): array
    {
        $items = $suggestions->filter(fn ($s) => ($s->group_key ?? '') === $groupKey)->values();

        return $items->map(fn ($s) => [
            'id' => $s->id,
            'label' => $s->extracted_value['label'] ?? '',
            'proficiency' => $s->extracted_value['proficiency'] ?? null,
            'years_experience' => $s->extracted_value['years_experience'] ?? null,
            'resolution' => $s->resolution?->value ?? 'unknown',
            'source_evidence' => $s->source_evidence,
        ])->toArray();
    }

    private function buildLanguagesCerts($suggestions): array
    {
        $items = $suggestions->filter(fn ($s) => ($s->group_key ?? '') === 'languages_certifications')->values();

        return $items->map(fn ($s) => [
            'id' => $s->id,
            'type' => $s->type->value,
            'name' => $s->extracted_value['name'] ?? $s->extracted_value['language'] ?? '',
            'required' => $s->extracted_value['required'] ?? null,
            'proficiency' => $s->extracted_value['proficiency'] ?? null,
        ])->toArray();
    }

    private function buildCompensation($suggestions): ?array
    {
        $comp = $suggestions->firstWhere('type', 'compensation');

        if ($comp === null) {
            return null;
        }

        return [
            'salary_min' => $comp->extracted_value['salary_min'] ?? null,
            'salary_max' => $comp->extracted_value['salary_max'] ?? null,
            'currency' => $comp->extracted_value['currency'] ?? null,
            'period' => $comp->extracted_value['period'] ?? null,
            'text' => $comp->extracted_value['text'] ?? null,
        ];
    }

    private function buildDates($suggestions): array
    {
        $dates = [];
        $fields = ['publication_date', 'application_deadline', 'expected_start_date', 'employment_duration'];

        foreach ($fields as $type) {
            $s = $suggestions->firstWhere('type', $type);
            if ($s !== null) {
                $dates[$type] = $s->extracted_value['value'] ?? null;
            }
        }

        return $dates;
    }
}

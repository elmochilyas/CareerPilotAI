<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\JobIngestionStatus;
use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySkill;
use App\Models\JobRequirement;
use Illuminate\Support\Facades\DB;

class ConfirmOpportunityAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
        private GeneratePreviewAction $previewAction,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion, string $versionToken): JobOpportunity
    {
        if ($ingestion->status === JobIngestionStatus::Confirmed) {
            $existing = $ingestion->opportunity()->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $this->stateService->assertReviewable($ingestion->status);
        $this->verifyPreviewFreshness($ingestion, $versionToken);

        $suggestions = $ingestion->suggestions;

        $this->verifyDecisionsComplete($suggestions);
        $this->verifyNoAmbiguousSkills($suggestions);

        return DB::transaction(function () use ($ingestion, $suggestions) {
            $user = $ingestion->user;
            $candidateProfile = CandidateProfile::where('user_id', $user->id)->firstOrFail();

            $opportunityData = $this->buildOpportunityData($ingestion, $suggestions, $candidateProfile);

            $opportunity = JobOpportunity::create($opportunityData);

            $this->createRequirements($opportunity, $suggestions);
            $this->createSkills($opportunity, $suggestions);

            $ingestion->update([
                'status' => JobIngestionStatus::Confirmed,
                'confirmed_at' => now(),
            ]);

            return $opportunity;
        });
    }

    private function verifyPreviewFreshness(JobOpportunityIngestion $ingestion, string $versionToken): void
    {
        $preview = $this->previewAction->execute($ingestion);

        if ($preview['version_token'] !== $versionToken) {
            throw new ConflictException(
                'Preview is outdated. Please regenerate before confirming.',
                'stale_preview',
            );
        }
    }

    private function verifyDecisionsComplete($suggestions): void
    {
        $pending = $suggestions->filter(fn ($s) => $s->review_decision === ReviewDecision::Pending);

        if ($pending->isNotEmpty()) {
            throw new ConflictException(
                'All suggestions must be reviewed before confirmation.',
                'incomplete_review',
            );
        }
    }

    private function verifyNoAmbiguousSkills($suggestions): void
    {
        $ambiguous = $suggestions->filter(
            fn ($s) => $s->resolution === SkillResolutionState::Ambiguous
        );

        if ($ambiguous->isNotEmpty()) {
            throw new ConflictException(
                'Ambiguous skills must be resolved before confirmation.',
                'unresolved_skill_mapping',
            );
        }
    }

    private function buildOpportunityData(JobOpportunityIngestion $ingestion, $suggestions, CandidateProfile $profile): array
    {
        $getValue = function ($suggestions, string $type, string $field = 'value') {
            $s = $suggestions->firstWhere('type', $type);

            if ($s === null) {
                return null;
            }

            if ($s->review_decision === ReviewDecision::Edited && $s->edited_value !== null) {
                return $s->edited_value[$field] ?? null;
            }

            if ($s->review_decision === ReviewDecision::Rejected || $s->review_decision === ReviewDecision::KeepBlank) {
                return null;
            }

            return $s->extracted_value[$field] ?? null;
        };

        $getBool = function ($suggestions, string $type) {
            $s = $suggestions->firstWhere('type', $type);

            if ($s === null) {
                return null;
            }

            if ($s->review_decision === ReviewDecision::Edited && $s->edited_value !== null) {
                return filter_var($s->edited_value['value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }

            return filter_var($s->extracted_value['value'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $getLocation = function ($suggestions, string $part) {
            $field = $part;
            $s = $suggestions->firstWhere('field', $field);

            if ($s === null) {
                $s = $suggestions->firstWhere('type', $part);
            }

            if ($s === null) {
                return null;
            }

            if ($s->review_decision === ReviewDecision::Edited && $s->edited_value !== null) {
                return $s->edited_value['value'] ?? null;
            }

            return $s->extracted_value['value'] ?? null;
        };

        return [
            'candidate_profile_id' => $profile->id,
            'ingestion_id' => $ingestion->id,
            'title' => $getValue($suggestions, 'job_title', 'title') ?? 'Untitled Position',
            'company_name' => $getValue($suggestions, 'company', 'company_name'),
            'department' => $getValue($suggestions, 'department', 'department'),
            'external_reference' => $getValue($suggestions, 'external_reference', 'external_reference'),
            'summary' => $getValue($suggestions, 'summary', 'summary'),
            'application_url' => $getValue($suggestions, 'application_url', 'application_url'),
            'personal_label' => $ingestion->personal_label,
            'source_url' => $ingestion->source_url,
            'city' => $getLocation($suggestions, 'city'),
            'region' => $getLocation($suggestions, 'region'),
            'country' => $getLocation($suggestions, 'country'),
            'work_mode' => $getValue($suggestions, 'work_mode', 'work_mode'),
            'contract_type' => $getValue($suggestions, 'contract_type', 'contract_type'),
            'seniority_level' => $getValue($suggestions, 'seniority_level', 'seniority_level'),
            'working_hours' => $getValue($suggestions, 'working_hours', 'working_hours'),
            'travel_required' => $getBool($suggestions, 'travel_required'),
            'relocation_required' => $getBool($suggestions, 'relocation_required'),
            'source_hash' => $ingestion->content_hash,
            'saved_at' => now(),
        ];
    }

    private function createRequirements(JobOpportunity $opportunity, $suggestions): void
    {
        $order = 0;

        $responsibilities = $suggestions->filter(fn ($s) => $s->type->value === 'responsibility');
        foreach ($responsibilities as $s) {
            $text = $s->review_decision === ReviewDecision::Edited && $s->edited_value !== null
                ? ($s->edited_value['text'] ?? '')
                : ($s->extracted_value['text'] ?? '');

            if (trim($text) === '') {
                continue;
            }

            JobRequirement::create([
                'job_opportunity_id' => $opportunity->id,
                'category' => 'responsibility',
                'content' => $text,
                'display_order' => $order++,
            ]);
        }

        foreach (['required_experience' => 'required', 'preferred_experience' => 'preferred'] as $type => $classification) {
            $items = $suggestions->filter(fn ($s) => $s->type->value === $type);

            foreach ($items as $s) {
                $summary = $s->extracted_value['summary'] ?? '';
                $years = $s->extracted_value['years'] ?? null;

                if (trim($summary) === '' && $years === null) {
                    continue;
                }

                $content = $summary;
                if ($years !== null) {
                    $content .= ($content !== '' ? ' ' : '')."({$years} years)";
                }

                JobRequirement::create([
                    'job_opportunity_id' => $opportunity->id,
                    'category' => $classification === 'required' ? 'required_experience' : 'preferred_experience',
                    'content' => $content,
                    'classification' => $classification,
                    'display_order' => $order++,
                ]);
            }
        }

        $educationItems = $suggestions->filter(fn ($s) => $s->type->value === 'education');
        foreach ($educationItems as $s) {
            $degree = $s->extracted_value['degree'] ?? '';
            $field = $s->extracted_value['field'] ?? null;

            if (trim($degree) === '') {
                continue;
            }

            $content = $degree;
            if ($field !== null && trim($field) !== '') {
                $content .= " in {$field}";
            }

            JobRequirement::create([
                'job_opportunity_id' => $opportunity->id,
                'category' => 'education',
                'content' => $content,
                'display_order' => $order++,
            ]);
        }

        $langItems = $suggestions->filter(fn ($s) => $s->type->value === 'language');
        foreach ($langItems as $s) {
            $language = $s->extracted_value['language'] ?? '';
            $proficiency = $s->extracted_value['proficiency'] ?? null;

            if (trim($language) === '') {
                continue;
            }

            JobRequirement::create([
                'job_opportunity_id' => $opportunity->id,
                'category' => 'language',
                'content' => $language,
                'language' => $language,
                'language_proficiency' => $proficiency,
                'display_order' => $order++,
            ]);
        }

        $certItems = $suggestions->filter(fn ($s) => $s->type->value === 'certification');
        foreach ($certItems as $s) {
            $name = $s->extracted_value['name'] ?? '';
            if (trim($name) === '') {
                continue;
            }

            JobRequirement::create([
                'job_opportunity_id' => $opportunity->id,
                'category' => 'education',
                'content' => $name,
                'display_order' => $order++,
            ]);
        }
    }

    private function createSkills(JobOpportunity $opportunity, $suggestions): void
    {
        $order = 0;

        $skillMappings = [
            'required_skills' => 'required',
            'preferred_skills' => 'preferred',
        ];

        foreach ($skillMappings as $groupKey => $classification) {
            $items = $suggestions->filter(fn ($s) => ($s->group_key ?? '') === $groupKey);

            $seen = [];

            foreach ($items as $s) {
                $label = $s->extracted_value['label'] ?? '';
                $normalized = mb_strtolower(trim($label));

                if ($normalized === '') {
                    continue;
                }

                if (isset($seen[$normalized])) {
                    continue;
                }
                $seen[$normalized] = true;

                JobOpportunitySkill::create([
                    'job_opportunity_id' => $opportunity->id,
                    'skill_id' => $s->resolved_skill_id,
                    'original_label' => $label,
                    'classification' => $classification,
                    'proficiency' => $s->extracted_value['proficiency'] ?? null,
                    'years_experience' => $s->extracted_value['years_experience'] ?? null,
                    'source_evidence' => $s->source_evidence,
                    'display_order' => $order++,
                ]);
            }
        }
    }
}

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
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveSuggestionDecisionAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(
        JobOpportunityIngestion $ingestion,
        JobOpportunitySuggestion $suggestion,
        string $decision,
        int $expectedVersion,
        ?array $editedValue = null,
        ?int $resolvedSkillId = null,
    ): JobOpportunitySuggestion {
        return DB::transaction(function () use (
            $ingestion,
            $suggestion,
            $decision,
            $editedValue,
            $expectedVersion,
            $resolvedSkillId,
        ): JobOpportunitySuggestion {
            $lockedIngestion = JobOpportunityIngestion::query()
                ->whereKey($ingestion->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSuggestion = JobOpportunitySuggestion::query()
                ->whereKey($suggestion->getKey())
                ->where('ingestion_id', $lockedIngestion->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->applyLocked(
                $lockedIngestion,
                $lockedSuggestion,
                $decision,
                $expectedVersion,
                $editedValue,
                $resolvedSkillId,
            );
        }, attempts: 3);
    }

    public function applyLocked(
        JobOpportunityIngestion $ingestion,
        JobOpportunitySuggestion $suggestion,
        string $decision,
        int $expectedVersion,
        ?array $editedValue,
        ?int $resolvedSkillId,
    ): JobOpportunitySuggestion {
        $this->stateService->assertReviewable($ingestion->status);

        if ($suggestion->ingestion_id !== $ingestion->id) {
            throw new ConflictException(
                'Suggestion does not belong to this ingestion.',
                'suggestion_mismatch',
            );
        }

        if ($suggestion->version !== $expectedVersion) {
            throw new ConflictException(
                'Suggestion has been modified. Please refresh and try again.',
                'stale_mutation',
            );
        }

        $reviewDecision = ReviewDecision::tryFrom($decision);

        if ($reviewDecision === null) {
            throw new ConflictException("Invalid decision: {$decision}.", 'invalid_decision');
        }

        if ($reviewDecision === ReviewDecision::Edited && $editedValue === null) {
            throw new ConflictException('Edited decision requires an edited value.', 'edited_value_required');
        }

        if ($reviewDecision === ReviewDecision::Edited) {
            $this->validateEditedValue($suggestion, $editedValue);
        }

        $resolution = $suggestion->resolution;
        $skillId = $suggestion->resolved_skill_id;

        if ($reviewDecision === ReviewDecision::Resolved) {
            if (! in_array($suggestion->type, [
                SuggestionType::RequiredSkill,
                SuggestionType::PreferredSkill,
            ], true)) {
                throw ValidationException::withMessages([
                    'decision' => ['Only skill suggestions can use the resolved decision.'],
                ]);
            }

            if ($resolvedSkillId !== null) {
                $skillExists = Skill::query()
                    ->whereKey($resolvedSkillId)
                    ->where('is_active', true)
                    ->exists();

                if (! $skillExists) {
                    throw ValidationException::withMessages([
                        'resolved_skill_id' => ['The selected skill is not available.'],
                    ]);
                }

                $resolution = SkillResolutionState::CandidateResolved;
                $skillId = $resolvedSkillId;
            } else {
                $resolution = SkillResolutionState::Unknown;
                $skillId = null;
            }
        }

        $suggestion->update([
            'review_decision' => $reviewDecision,
            'edited_value' => $reviewDecision === ReviewDecision::Edited ? $editedValue : null,
            'resolution' => $resolution,
            'resolved_skill_id' => $skillId,
            'reviewed_at' => now(),
            'version' => $suggestion->version + 1,
        ]);

        $ingestion->increment('version');

        return $suggestion->refresh();
    }

    /**
     * @param  array<string, mixed>  $editedValue
     */
    private function validateEditedValue(
        JobOpportunitySuggestion $suggestion,
        array $editedValue,
    ): void {
        $rules = match ($suggestion->type) {
            SuggestionType::JobTitle,
            SuggestionType::Company,
            SuggestionType::Department,
            SuggestionType::ExternalReference => [
                'value' => ['required', 'string', 'max:255'],
            ],
            SuggestionType::Summary => [
                'value' => ['required', 'string', 'max:10000'],
            ],
            SuggestionType::ApplicationUrl => [
                'value' => ['required', 'string', 'url:http,https', 'max:500'],
            ],
            SuggestionType::City,
            SuggestionType::Region,
            SuggestionType::Country,
            SuggestionType::WorkingHours,
            SuggestionType::EmploymentDuration => [
                'value' => ['required', 'string', 'max:100'],
            ],
            SuggestionType::WorkMode => [
                'value' => ['required', 'string', 'in:remote,hybrid,on_site'],
            ],
            SuggestionType::ContractType => [
                'value' => ['required', 'string', 'in:full-time,part-time,contract,internship,freelance'],
            ],
            SuggestionType::SeniorityLevel => [
                'value' => ['required', 'string', 'in:junior,mid,senior,lead,manager,director,executive,intern,graduate'],
            ],
            SuggestionType::TravelRequired,
            SuggestionType::RelocationRequired => [
                'value' => ['required', 'boolean'],
            ],
            SuggestionType::Responsibility => [
                'text' => ['required', 'string', 'max:5000'],
            ],
            SuggestionType::RequiredExperience,
            SuggestionType::PreferredExperience => [
                'summary' => ['required', 'string', 'max:2000'],
                'years' => ['nullable', 'numeric', 'min:0', 'max:99'],
            ],
            SuggestionType::Education => [
                'degree' => ['required', 'string', 'max:255'],
                'field' => ['nullable', 'string', 'max:255'],
                'required' => ['nullable', 'boolean'],
                'equivalent_experience' => ['nullable', 'string', 'max:1000'],
            ],
            SuggestionType::RequiredSkill,
            SuggestionType::PreferredSkill => [
                'label' => ['required', 'string', 'max:150'],
                'proficiency' => ['nullable', 'string', 'max:30'],
                'years_experience' => ['nullable', 'numeric', 'min:0', 'max:99'],
            ],
            SuggestionType::Language => [
                'language' => ['required', 'string', 'max:100'],
                'required' => ['nullable', 'boolean'],
                'proficiency' => ['nullable', 'string', 'max:30'],
            ],
            SuggestionType::Certification => [
                'name' => ['required', 'string', 'max:255'],
                'required' => ['nullable', 'boolean'],
            ],
            SuggestionType::Compensation => [
                'salary_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'salary_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'gte:salary_min'],
                'currency' => ['nullable', 'string', 'size:3'],
                'period' => ['nullable', 'string', 'in:yearly,monthly,hourly,daily'],
                'text' => ['nullable', 'string', 'max:2000'],
            ],
            SuggestionType::Benefit => [
                'name' => ['required', 'string', 'max:255'],
            ],
            SuggestionType::PublicationDate,
            SuggestionType::ApplicationDeadline,
            SuggestionType::ExpectedStartDate => [
                'value' => ['required', 'date_format:Y-m-d'],
            ],
            SuggestionType::AdditionalRequirement => [
                'text' => ['required', 'string', 'max:2000'],
            ],
        };

        Validator::make($editedValue, $rules)->validate();

        $isMeaningful = match ($suggestion->type) {
            SuggestionType::Responsibility => JobValueMeaningfulness::isMeaningfulResponsibility($editedValue),
            SuggestionType::RequiredExperience,
            SuggestionType::PreferredExperience => JobValueMeaningfulness::isMeaningfulExperience($editedValue),
            SuggestionType::Education => JobValueMeaningfulness::isMeaningfulEducation($editedValue),
            SuggestionType::RequiredSkill,
            SuggestionType::PreferredSkill => JobValueMeaningfulness::isMeaningfulSkill($editedValue),
            SuggestionType::Language => JobValueMeaningfulness::isMeaningfulLanguage($editedValue),
            SuggestionType::Certification => JobValueMeaningfulness::isMeaningfulCertification($editedValue),
            SuggestionType::Compensation => JobValueMeaningfulness::isMeaningfulCompensation($editedValue),
            SuggestionType::Benefit => JobValueMeaningfulness::isMeaningfulBenefitItem($editedValue),
            SuggestionType::AdditionalRequirement => JobValueMeaningfulness::isMeaningfulAdditionalRequirement(
                $editedValue['text'] ?? null
            ),
            default => array_key_exists('value', $editedValue)
                && (
                    is_bool($editedValue['value'])
                    || JobValueMeaningfulness::isMeaningfulString($editedValue['value'])
                ),
        };

        if (! $isMeaningful) {
            throw ValidationException::withMessages([
                'edited_value' => ['The edited value must contain meaningful job information.'],
            ]);
        }
    }
}

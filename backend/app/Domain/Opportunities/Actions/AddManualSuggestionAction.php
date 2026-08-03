<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AddManualSuggestionAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(
        JobOpportunityIngestion $ingestion,
        SuggestionType $type,
        string $value,
        int $expectedIngestionVersion,
    ): JobOpportunitySuggestion {
        return DB::transaction(function () use (
            $ingestion,
            $type,
            $value,
            $expectedIngestionVersion,
        ): JobOpportunitySuggestion {
            $lockedIngestion = JobOpportunityIngestion::query()
                ->whereKey($ingestion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->stateService->assertReviewable($lockedIngestion->status);

            if ($lockedIngestion->version !== $expectedIngestionVersion) {
                throw new ConflictException(
                    'The review has changed. Refresh and try adding the item again.',
                    'stale_mutation',
                );
            }

            $payload = match ($type) {
                SuggestionType::Responsibility => ['text' => $value],
                SuggestionType::RequiredSkill,
                SuggestionType::PreferredSkill => ['label' => $value],
                default => throw new \LogicException('Unsupported manual suggestion type.'),
            };

            $isSkill = in_array($type, [
                SuggestionType::RequiredSkill,
                SuggestionType::PreferredSkill,
            ], true);

            $suggestion = $lockedIngestion->suggestions()->create([
                'type' => $type,
                'extracted_value' => $payload,
                'edited_value' => $payload,
                'review_decision' => ReviewDecision::Accepted,
                'source_evidence' => null,
                'schema_version' => Config::string(
                    'job-ingestion.analysis_schema_version',
                    '1.0.0',
                ),
                'reviewed_at' => now(),
                'resolution' => $isSkill ? SkillResolutionState::Unknown : null,
                'resolved_skill_id' => null,
                'version' => 1,
            ]);

            $lockedIngestion->increment('version');

            return $suggestion->refresh();
        }, attempts: 3);
    }
}

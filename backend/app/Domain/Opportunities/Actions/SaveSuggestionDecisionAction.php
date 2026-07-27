<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\ReviewDecision;
use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;

class SaveSuggestionDecisionAction
{
    public function __construct(
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(
        JobOpportunityIngestion $ingestion,
        JobOpportunitySuggestion $suggestion,
        string $decision,
        ?array $editedValue = null,
    ): JobOpportunitySuggestion {
        $this->stateService->assertReviewable($ingestion->status);

        if ($ingestion->version !== $ingestion->fresh()->version) {
            throw new ConflictException('Ingestion has been modified. Please refresh.', 'stale_mutation');
        }

        $reviewDecision = ReviewDecision::tryFrom($decision);

        if ($reviewDecision === null) {
            throw new ConflictException("Invalid decision: {$decision}.", 'invalid_decision');
        }

        if ($reviewDecision === ReviewDecision::Edited && $editedValue === null) {
            throw new ConflictException('Edited decision requires an edited value.', 'edited_value_required');
        }

        $suggestion->update([
            'review_decision' => $reviewDecision,
            'edited_value' => $reviewDecision === ReviewDecision::Edited ? $editedValue : $suggestion->edited_value,
            'reviewed_at' => now(),
            'version' => $suggestion->version + 1,
        ]);

        $ingestion->increment('version');

        return $suggestion->fresh();
    }
}

<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Support\Collection;

class BatchSaveDecisionsAction
{
    public function __construct(
        private SaveSuggestionDecisionAction $saveAction,
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion, array $decisions): Collection
    {
        $this->stateService->assertReviewable($ingestion->status);

        $saved = new Collection;

        foreach ($decisions as $item) {
            $suggestion = JobOpportunitySuggestion::where('id', $item['id'])
                ->where('ingestion_id', $ingestion->id)
                ->first();

            if ($suggestion === null) {
                throw new ConflictException("Suggestion {$item['id']} not found.", 'suggestion_not_found');
            }

            $saved->push(
                $this->saveAction->execute(
                    $ingestion,
                    $suggestion,
                    $item['decision'],
                    $item['edited_value'] ?? null,
                )
            );
        }

        return $saved;
    }
}

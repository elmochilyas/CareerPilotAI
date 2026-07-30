<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Services\JobIngestionStateService;
use App\Exceptions\Api\ConflictException;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchSaveDecisionsAction
{
    public function __construct(
        private SaveSuggestionDecisionAction $saveAction,
        private JobIngestionStateService $stateService,
    ) {}

    public function execute(JobOpportunityIngestion $ingestion, array $decisions): Collection
    {
        return DB::transaction(function () use ($ingestion, $decisions): Collection {
            $lockedIngestion = JobOpportunityIngestion::query()
                ->whereKey($ingestion->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->stateService->assertReviewable($lockedIngestion->status);

            $orderedDecisions = collect($decisions)
                ->sortBy('id')
                ->values();
            $suggestions = JobOpportunitySuggestion::query()
                ->where('ingestion_id', $lockedIngestion->id)
                ->whereIn('id', $orderedDecisions->pluck('id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $saved = new Collection;

            foreach ($orderedDecisions as $item) {
                $suggestion = $suggestions->get($item['id']);

                if (! $suggestion instanceof JobOpportunitySuggestion) {
                    throw new ConflictException("Suggestion {$item['id']} not found.", 'suggestion_not_found');
                }

                $saved->push(
                    $this->saveAction->applyLocked(
                        $lockedIngestion,
                        $suggestion,
                        $item['decision'],
                        $item['version'],
                        $item['edited_value'] ?? null,
                        $item['resolved_skill_id'] ?? null,
                    )
                );
            }

            return $saved;
        }, attempts: 3);
    }
}

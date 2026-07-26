<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Data\BatchDecisionData;
use App\Domain\CvIngestion\Data\ImportDecisionData;
use App\Models\CvDocument;
use App\Models\CvSuggestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaveBatchReviewDecisionsAction
{
    public function __construct(private SaveReviewDecisionAction $saveSingle) {}

    /** @return Collection<int, CvSuggestion> */
    public function execute(CvDocument $document, BatchDecisionData $data): Collection
    {
        return DB::transaction(function () use ($document, $data) {
            $results = collect();

            foreach ($data->decisions as $decisionData) {
                $suggestion = CvSuggestion::where('cv_document_id', $document->id)
                    ->findOrFail($decisionData->suggestionId);

                $results->push(
                    $this->saveSingle->execute($document, $suggestion, new ImportDecisionData(
                        suggestionId: $decisionData->suggestionId,
                        decision: $decisionData->decision,
                        editedValue: $decisionData->editedValue,
                        action: $decisionData->action,
                        targetId: $decisionData->targetId,
                    ))
                );
            }

            return $results;
        });
    }
}

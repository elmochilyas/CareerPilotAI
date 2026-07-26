<?php

namespace App\Domain\CvIngestion\Actions;

use App\Domain\CvIngestion\Data\ImportDecisionData;
use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Enums\CvSuggestionReviewStatus;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
use App\Models\CvDocument;
use App\Models\CvSuggestion;

class SaveReviewDecisionAction
{
    public function execute(CvDocument $document, CvSuggestion $suggestion, ImportDecisionData $data): CvSuggestion
    {
        if ($document->status !== CvDocumentStatus::ReadyForReview) {
            throw new ConflictException(
                'Document is not in reviewable state.',
                'document_not_reviewable',
            );
        }

        if ($suggestion->review_status !== CvSuggestionReviewStatus::Pending) {
            throw new ConflictException(
                'This suggestion has already been reviewed.',
                'already_reviewed',
            );
        }

        $decision = $data->decision;
        $validDecisions = [
            'accepted', 'rejected', 'keep_existing', 'edited',
            'create_new', 'update_existing',
        ];

        if (! in_array($decision, $validDecisions, true)) {
            throw new UnprocessableEntityException(
                "Invalid decision '{$decision}'.",
                'invalid_decision',
            );
        }

        $updateData = [
            'review_status' => $decision,
            'reviewed_decision' => $data->editedValue,
            'reviewed_at' => now(),
        ];

        if ($decision === 'edited' && $data->editedValue !== null) {
            $updateData['suggested_value'] = $data->editedValue;
        }

        $suggestion->update($updateData);

        return $suggestion->fresh();
    }
}

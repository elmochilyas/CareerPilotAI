<?php

namespace App\Domain\Clarification\Listeners;

use App\Domain\Clarification\Events\ProposalAccepted;
use App\Domain\Clarification\Services\ClarificationAuditWriter;
use Illuminate\Support\Facades\Log;

final class WriteClarificationAuditEventListener
{
    public function __construct(
        private readonly ClarificationAuditWriter $writer,
    ) {}

    public function handle(ProposalAccepted $event): void
    {
        try {
            $this->writer->write('proposal_accepted', [
                'answer_id' => $event->answer->id,
                'proposal_id' => $event->proposal->id,
                'user_id' => $event->answer->user_id,
                'match_analysis_id' => $event->answer->question->match_analysis_id,
                'target_type' => $event->targetType,
                'target_id' => $event->targetId,
                'field' => $event->field,
                'before_value' => $event->beforeValue,
                'after_value' => $event->afterValue,
                'metadata' => $event->metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write clarification audit event (listener).', [
                'proposal_id' => $event->proposal->id,
                'answer_id' => $event->answer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

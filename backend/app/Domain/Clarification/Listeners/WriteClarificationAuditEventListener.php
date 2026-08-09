<?php

namespace App\Domain\Clarification\Listeners;

use App\Domain\Clarification\Events\ProposalAccepted;
use App\Models\ClarificationAuditEvent;
use App\Support\RequestIdContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes an immutable audit record when a clarification proposal is accepted.
 * Best-effort observability: a failure here must never roll back or mask the
 * already-committed trusted profile mutation.
 */
final class WriteClarificationAuditEventListener
{
    public function handle(ProposalAccepted $event): void
    {
        try {
            ClarificationAuditEvent::create([
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
        } catch (Throwable $exception) {
            Log::warning('Failed to write clarification audit event.', [
                'proposal_id' => $event->proposal->id,
                'answer_id' => $event->answer->id,
                'request_id' => RequestIdContext::get(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

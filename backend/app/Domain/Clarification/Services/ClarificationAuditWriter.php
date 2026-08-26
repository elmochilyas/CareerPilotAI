<?php

namespace App\Domain\Clarification\Services;

use App\Support\RequestIdContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClarificationAuditWriter
{
    /**
     * Best-effort append-only writer: any failure is logged and never
     * bubbles to the caller, so audit can never roll back trusted data.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function write(string $event, array $attributes): void
    {
        try {
            $payload = array_merge([
                'event' => $event,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes);

            // Normalize target_type enum to string for DB storage
            if (isset($payload['target_type']) && $payload['target_type'] instanceof \BackedEnum) {
                $payload['target_type'] = $payload['target_type']->value;
            } elseif (isset($payload['target_type']) && is_object($payload['target_type'])) {
                $payload['target_type'] = (string) $payload['target_type'];
            }

            // Ensure JSON columns are encoded if needed (DB::table expects strings for JSON)
            foreach (['before_value', 'after_value', 'metadata'] as $jsonKey) {
                if (isset($payload[$jsonKey]) && is_array($payload[$jsonKey])) {
                    $payload[$jsonKey] = json_encode($payload[$jsonKey], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            DB::table('clarification_audit_events')->insert($payload);
        } catch (Throwable $e) {
            Log::warning('Failed to write clarification audit event.', [
                'event' => $event,
                'attributes' => $attributes,
                'request_id' => RequestIdContext::get(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

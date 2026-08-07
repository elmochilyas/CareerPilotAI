<?php

namespace App\Jobs;

use App\Domain\Matching\Services\StalenessService;
use App\Models\MatchAnalysis;
use App\Support\RequestIdContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Observes whether an analysis became stale after a trusted clarification
 * mutation. Observability only: it never recomputes the score and never writes
 * to the analysis (staleness is derived at read time by Matching's
 * StalenessService).
 */
class ObserveClarificationStalenessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        public int $analysisId,
        public ?string $requestId = null,
    ) {
        $this->onQueue(Config::get('clarification.queue', 'clarification'));
    }

    public function handle(StalenessService $staleness): void
    {
        $analysis = MatchAnalysis::find($this->analysisId);

        if ($analysis === null) {
            return;
        }

        if ($this->requestId !== null) {
            RequestIdContext::set($this->requestId);
        }

        try {
            $profile = $analysis->candidateProfile()->first();
            $opportunity = $analysis->jobOpportunity()->first();

            if ($profile === null || $opportunity === null) {
                Log::debug('Clarification staleness observation skipped: source records no longer exist.', [
                    'request_id' => RequestIdContext::get(),
                    'match_analysis_id' => $analysis->id,
                ]);

                return;
            }

            $versions = $staleness->differingVersions($analysis, $profile, $opportunity);

            Log::info('Clarification staleness observation completed.', [
                'request_id' => RequestIdContext::get(),
                'match_analysis_id' => $analysis->id,
                'stale' => $versions['profile'] || $versions['opportunity'],
                'profile_changed' => $versions['profile'],
                'opportunity_changed' => $versions['opportunity'],
            ]);
        } finally {
            if ($this->requestId !== null) {
                RequestIdContext::reset();
            }
        }
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->analysisId)];
    }

    public function backoff(): array
    {
        return Config::array('clarification.staleness_job.backoff', [5, 15, 30]);
    }
}

<?php

namespace App\Jobs;

use App\Domain\CompanyResearch\Data\CompanyResearchResult;
use App\Domain\CompanyResearch\Services\CompanyResearchSchemaValidator;
use App\Domain\CompanyResearch\Services\Contracts\CompanyResearchAnalyzer;
use App\Domain\CompanyResearch\Services\FallbackBriefBuilder;
use App\Models\Company;
use App\Models\JobOpportunity;
use App\Support\RequestIdContext;
use App\Support\SafeWebFetch\SafeFetcher;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ResearchCompanyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        public int $companyId,
        public int $opportunityId,
        public ?string $requestId = null,
        public ?string $pastedContent = null,
        public ?string $suppliedWebsite = null,
    ) {
        $this->onQueue(Config::string('company-research.queue', 'company-research'));
    }

    public function middleware(): array
    {
        $key = 'company-research:'.$this->companyId;

        return [new WithoutOverlapping($key)->dontRelease()];
    }

    public function backoff(): array
    {
        return Config::array('company-research.backoff', [10, 30, 60]);
    }

    public function handle(
        SafeFetcher $fetcher,
        CompanyResearchAnalyzer $analyzer,
        CompanyResearchSchemaValidator $validator,
        FallbackBriefBuilder $fallbackBuilder,
    ): void {
        if ($this->requestId !== null) {
            RequestIdContext::set($this->requestId);
        }

        $company = Company::find($this->companyId);

        if ($company === null) {
            return;
        }

        $opportunity = JobOpportunity::find($this->opportunityId);

        if ($opportunity === null) {
            $this->markFailed($company, 'opportunity_not_found', 'Opportunity not found.');

            return;
        }

        $start = hrtime(true);

        try {
            $evidence = $this->collectEvidence($fetcher, $company, $opportunity);

            $result = $analyzer->analyze($evidence, $opportunity, $company);

            $brief = $validator->validate($result, $evidence);

            $this->persistSuccess($company, $brief, $result, $start);
        } catch (\Throwable $e) {
            $fallbackReason = $this->classifyFallbackReason($e);
            $evidence = $this->collectEvidence($fetcher, $company, $opportunity, true);

            Log::warning('Company research failed, using fallback', [
                'company_id' => $company->id,
                'opportunity_id' => $opportunity->id,
                'error' => $e->getMessage(),
                'fallback_reason' => $fallbackReason,
                'request_id' => $this->requestId,
            ]);

            // Check if we should throw for retry (transient) vs fallback
            // For AI provider timeout/rate_limited/unavailable we fallback rather than throw to avoid stuck processing
            // Only throw if it's a transient error that retry could fix? But spec says fallback on provider failure.
            // So we persist fallback brief and don't rethrow, to complete job as success with limited status.

            $brief = $fallbackBuilder->fromOpportunity($opportunity, $company, $evidence, $fallbackReason);

            $this->persistFallback($company, $brief, $fallbackReason, $e);
        }
    }

    /**
     * @return array<int, array{url: string, title: ?string, content: string, source_type: string, retrieved_at: ?string}>
     */
    private function collectEvidence(SafeFetcher $fetcher, Company $company, JobOpportunity $opportunity, bool $allowCache = false): array
    {
        $evidence = [];
        $seenUrls = [];

        $candidates = [];

        if ($this->suppliedWebsite !== null && trim($this->suppliedWebsite) !== '') {
            $candidates[] = ['url' => trim($this->suppliedWebsite), 'type' => 'official_website'];
        }

        if ($company->website !== null && trim($company->website) !== '') {
            $candidates[] = ['url' => trim($company->website), 'type' => 'official_website'];
        }

        if ($opportunity->source_url !== null && trim($opportunity->source_url) !== '') {
            $candidates[] = ['url' => trim($opportunity->source_url), 'type' => 'job_posting'];
        }

        if ($opportunity->application_url !== null && trim($opportunity->application_url) !== '' && $opportunity->application_url !== $opportunity->source_url) {
            $candidates[] = ['url' => trim($opportunity->application_url), 'type' => 'job_posting'];
        }

        // Deduplicate by canonical URL (lowercase)
        $deduped = [];

        foreach ($candidates as $c) {
            $lower = strtolower(trim($c['url']));

            if (! isset($seenUrls[$lower])) {
                $seenUrls[$lower] = true;
                $deduped[] = $c;
            }
        }

        // Limit to 3 URLs to respect spec
        $deduped = array_slice($deduped, 0, 3);

        foreach ($deduped as $cand) {
            $result = $fetcher->fetch($cand['url']);

            if ($result->ok && $result->body !== null) {
                $evidence[] = [
                    'url' => $result->finalUrl ?? $cand['url'],
                    'title' => $result->title,
                    'content' => mb_substr($result->body, 0, 4000),
                    'source_type' => $cand['type'],
                    'retrieved_at' => $result->retrievedAt?->toISOString() ?? Carbon::now()->toISOString(),
                ];
            } else {
                Log::warning('Company research fetch failed', [
                    'url' => $cand['url'],
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                    'request_id' => $this->requestId,
                ]);
            }
        }

        // Pasted content as synthetic source
        if ($this->pastedContent !== null && trim($this->pastedContent) !== '') {
            $evidence[] = [
                'url' => 'pasted://company-about',
                'title' => 'Pasted company information',
                'content' => mb_substr(trim($this->pastedContent), 0, 4000),
                'source_type' => 'pasted',
                'retrieved_at' => Carbon::now()->toISOString(),
            ];
        }

        // Always include opportunity summary as base evidence
        $oppContent = trim((string) ($opportunity->summary ?? ''));

        if ($oppContent === '') {
            $oppContent = 'Opportunity: '.$opportunity->title.' at '.($opportunity->company_name ?? 'unknown');
        }

        $evidence[] = [
            'url' => 'opportunity://'.$opportunity->id,
            'title' => 'Job posting',
            'content' => mb_substr($oppContent, 0, 2000),
            'source_type' => 'opportunity',
            'retrieved_at' => Carbon::now()->toISOString(),
        ];

        return $evidence;
    }

    private function persistSuccess(Company $company, array $brief, CompanyResearchResult $result, int|float $start): void
    {
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $now = Carbon::now();

        $research = [
            'version' => 1,
            'status' => 'completed',
            'overview' => $brief['overview'],
            'products' => $brief['products'],
            'technology_context' => $brief['technology_context'],
            'role_context' => $brief['role_context'],
            'recent_information' => $brief['recent_information'],
            'candidate_preparation' => $brief['candidate_preparation'],
            'sources' => $brief['sources'],
            'generated_at' => $now->toISOString(),
            'fallback_reason' => null,
            'ai_meta' => [
                'provider' => $result->provider,
                'model' => $result->model,
                'prompt_version' => $result->promptVersion,
                'latency_ms' => $result->latencyMs,
                'tokens' => [
                    'prompt' => $result->tokensPrompt,
                    'completion' => $result->tokensCompletion,
                ],
            ],
        ];

        $company->update([
            'research' => $research,
            'research_status' => 'completed',
            'research_version' => 1,
            'researched_at' => $now,
            'research_failure_code' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function persistFallback(Company $company, array $brief, string $fallbackReason, \Throwable $e): void
    {
        // Brief already contains version/status/etc from builder; ensure ai_meta and latency if needed
        $brief['ai_meta'] = [
            'provider' => 'fallback',
            'model' => null,
            'prompt_version' => Config::string('company-research.prompt_version', '1.0.0'),
            'latency_ms' => 0,
            'tokens' => null,
            'fallback_reason' => $fallbackReason,
            'error_message' => $e->getMessage(),
        ];

        $status = $brief['status'] ?? 'limited';

        $company->update([
            'research' => $brief,
            'research_status' => $status,
            'research_version' => $brief['version'] ?? 1,
            'researched_at' => Carbon::now(),
            'research_failure_code' => $fallbackReason,
        ]);
    }

    private function markFailed(Company $company, string $code, string $message): void
    {
        $company->update([
            'research_status' => 'failed',
            'research_failure_code' => $code,
        ]);

        Log::warning('Company research marked failed', [
            'company_id' => $company->id,
            'code' => $code,
            'message' => $message,
        ]);
    }

    private function classifyFallbackReason(\Throwable $e): string
    {
        $msg = strtolower($e->getMessage());
        $code = method_exists($e, 'getErrorCode') ? strtolower($e->getErrorCode()) : '';

        if (str_contains($code, 'invalid_ai_output') || str_contains($msg, 'invalid_ai_output') || str_contains($msg, 'schema')) {
            return 'ai_schema_validation_failed';
        }

        if (str_contains($code, 'ai_provider_timeout') || str_contains($msg, 'timeout')) {
            return 'provider_timeout';
        }

        if (str_contains($code, 'ai_provider_rate_limited') || str_contains($msg, 'rate_limited')) {
            return 'provider_rate_limited';
        }

        if (str_contains($code, 'ai_provider_unavailable') || str_contains($msg, 'unavailable')) {
            return 'provider_unavailable';
        }

        if (str_contains($code, 'ssrf_blocked') || str_contains($code, 'fetch')) {
            return 'fetch_failed';
        }

        return 'unexpected_processing_failure';
    }

    public function failed(\Throwable $e): void
    {
        $company = Company::find($this->companyId);

        if ($company === null) {
            return;
        }

        try {
            $company->update([
                'research_status' => 'failed',
                'research_failure_code' => 'pipeline_error',
            ]);
        } catch (\Throwable $inner) {
            Log::error('Failed to mark company research as failed', [
                'company_id' => $this->companyId,
                'error' => $inner->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }
}

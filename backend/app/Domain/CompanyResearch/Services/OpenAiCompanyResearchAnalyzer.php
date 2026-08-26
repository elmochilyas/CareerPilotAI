<?php

namespace App\Domain\CompanyResearch\Services;

use App\Domain\CompanyResearch\Data\CompanyResearchResult;
use App\Domain\CompanyResearch\Services\Contracts\CompanyResearchAnalyzer;
use App\Exceptions\Api\ConflictException;
use App\Models\Company;
use App\Models\JobOpportunity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

class OpenAiCompanyResearchAnalyzer implements CompanyResearchAnalyzer
{
    public function analyze(array $evidence, JobOpportunity $opportunity, ?Company $company): CompanyResearchResult
    {
        $schemaVersion = Config::string('company-research.schema_version', '1.0.0');
        $promptVersion = Config::string('company-research.prompt_version', '1.0.0');

        $apiKey = Config::get('ai.providers.openai.key');

        if (empty($apiKey)) {
            throw new ConflictException(
                'AI analysis is currently unavailable.',
                'ai_provider_not_configured',
            );
        }

        $prompt = $this->buildPrompt($evidence, $opportunity, $company);
        $start = hrtime(true);
        $responseReceived = false;

        try {
            $response = (new CompanyResearchAgent($schemaVersion))->prompt(
                $prompt,
                provider: 'openai',
                model: 'gpt-4o-mini',
                timeout: 60,
            );

            $responseReceived = true;
            $end = hrtime(true);
            $latencyMs = (int) (($end - $start) / 1_000_000);

            if (! $response instanceof StructuredAgentResponse) {
                throw $this->invalidOutput('response');
            }

            $data = $response->toArray();

            if (! is_array($data['brief'] ?? null)) {
                throw $this->invalidOutput('brief');
            }

            $meta = $response->meta;
            $usage = $response->usage;

            return new CompanyResearchResult(
                schemaVersion: is_string($data['schema_version'] ?? null) ? $data['schema_version'] : '',
                brief: $data['brief'],
                warnings: is_array($data['warnings'] ?? null) ? $data['warnings'] : [],
                provider: $meta->provider ?? 'openai',
                model: $meta->model ?? 'gpt-4o-mini',
                promptVersion: $promptVersion,
                latencyMs: $latencyMs,
                tokensPrompt: $usage->promptTokens,
                tokensCompletion: $usage->completionTokens,
                responseId: null,
            );
        } catch (Throwable $e) {
            if ($e instanceof ConflictException) {
                throw $e;
            }

            throw $this->classifyException($e, $responseReceived);
        }
    }

    /**
     * @param  array<int, array{url: string, title: ?string, content: string, source_type: string, retrieved_at: ?string}>  $evidence
     */
    private function buildPrompt(array $evidence, JobOpportunity $opportunity, ?Company $company): string
    {
        $evidenceBlock = '';

        foreach ($evidence as $idx => $item) {
            $url = $item['url'] ?? 'unknown';
            $title = $item['title'] ?? 'Untitled';
            $content = mb_substr($item['content'] ?? '', 0, 4000);
            $type = $item['source_type'] ?? 'unknown';
            $evidenceBlock .= "[Source {$idx}] type={$type} title={$title} url={$url}\n{$content}\n---\n";
        }

        $opportunityContext = json_encode([
            'title' => $opportunity->title,
            'company_name' => $opportunity->company_name,
            'summary' => $opportunity->summary,
            'source_url' => $opportunity->source_url,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $companyContext = $company !== null ? json_encode([
            'name' => $company->name,
            'website' => $company->website,
            'industry' => $company->industry,
            'location' => $company->location,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'null';

        return "You are structuring company research.\n"
            ."Opportunity: {$opportunityContext}\n"
            ."Company: {$companyContext}\n"
            ."Evidence set (source_ids refer to index in this list):\n"
            ."<source_material>\n{$evidenceBlock}\n</source_material>\n"
            .'Return brief that cites only these source URLs.';
    }

    private function invalidOutput(string $path): ConflictException
    {
        return new ConflictException(
            'AI provider returned invalid response.',
            'invalid_ai_output',
            [
                'processing_stage' => 'response_mapping',
                'provider_response_received' => true,
                'validation_paths' => [$path],
            ],
        );
    }

    private function classifyException(Throwable $exception, bool $responseReceived): ConflictException
    {
        $requestException = $this->findPrevious($exception, RequestException::class);
        $status = $requestException instanceof RequestException ? $requestException->response->status() : null;

        $context = [
            'processing_stage' => $responseReceived ? 'response_mapping' : 'provider_request',
            'provider_response_received' => $responseReceived,
            'validation_paths' => [],
        ];

        if ($exception instanceof RateLimitedException || $status === 429) {
            return new ConflictException('AI provider rate limit exceeded.', 'ai_provider_rate_limited', $context, $exception);
        }

        if ($status === 401 || $status === 403) {
            return new ConflictException('AI analysis is currently unavailable.', 'ai_provider_authentication_failed', $context, $exception);
        }

        if (
            $status === 408
            || $status === 504
            || ($exception instanceof ConnectionException && Str::contains(Str::lower($exception->getMessage()), ['timeout', 'timed out']))
        ) {
            return new ConflictException('AI provider did not respond within timeout.', 'ai_provider_timeout', $context, $exception);
        }

        if (
            $exception instanceof ProviderOverloadedException
            || $exception instanceof InsufficientCreditsException
            || $exception instanceof ConnectionException
            || ($status !== null && $status >= 500)
        ) {
            return new ConflictException('AI provider is temporarily unavailable.', 'ai_provider_unavailable', $context, $exception);
        }

        return new ConflictException('An unexpected error occurred during company research.', 'unexpected_processing_failure', $context, $exception);
    }

    /**
     * @param  class-string<Throwable>  $type
     */
    private function findPrevious(Throwable $exception, string $type): ?Throwable
    {
        $current = $exception;

        do {
            if ($current instanceof $type) {
                return $current;
            }

            $current = $current->getPrevious();
        } while ($current !== null);

        return null;
    }
}

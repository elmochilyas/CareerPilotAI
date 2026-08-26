<?php

namespace App\Domain\CompanyResearch\Services;

use App\Domain\CompanyResearch\Data\CompanyResearchResult;
use App\Domain\CompanyResearch\Services\Contracts\CompanyResearchAnalyzer;
use App\Models\Company;
use App\Models\JobOpportunity;
use Illuminate\Support\Facades\Config;

class FakeCompanyResearchAnalyzer implements CompanyResearchAnalyzer
{
    public function __construct(
        private FallbackBriefBuilder $fallbackBuilder,
    ) {}

    public function analyze(array $evidence, JobOpportunity $opportunity, ?Company $company): CompanyResearchResult
    {
        $schemaVersion = Config::string('company-research.schema_version', '1.0.0');
        $promptVersion = Config::string('company-research.prompt_version', '1.0.0');

        // In fake mode, produce a deterministic brief from evidence-derived snippets
        // If evidence contains content, synthesize a simple brief that cites the first source

        if ($evidence !== []) {
            $first = $evidence[0];
            $url = $first['url'] ?? 'https://example.com';
            $title = $first['title'] ?? 'Official Website';

            $brief = [
                'overview' => [
                    'name' => $company !== null ? $company->name : ($opportunity->company_name ?? 'Acme'),
                    'website' => $company !== null ? $company->website : null,
                    'industry' => $company !== null && $company->industry !== null ? $company->industry : 'Technology',
                    'headquarters' => $company !== null && $company->location !== null ? $company->location : 'Unknown',
                    'description' => $first['content'] !== '' ? mb_substr($first['content'], 0, 200) : 'A technology company.',
                ],
                'products' => [
                    [
                        'text' => 'Company describes its platform as one of its products.',
                        'kind' => 'fact',
                        'confidence' => 'high',
                        'source_ids' => [0],
                    ],
                ],
                'technology_context' => [
                    [
                        'text' => 'This suggests the backend role may work with systems supporting the platform.',
                        'kind' => 'inference',
                        'confidence' => 'medium',
                        'source_ids' => [0],
                    ],
                ],
                'role_context' => [
                    [
                        'text' => 'The role appears to support the company’s core product development.',
                        'kind' => 'inference',
                        'confidence' => 'medium',
                        'source_ids' => [0],
                    ],
                ],
                'recent_information' => [
                    [
                        'text' => 'No recent relevant developments cited in fetched sources.',
                        'kind' => 'unknown',
                        'confidence' => null,
                        'source_ids' => [],
                    ],
                ],
                'candidate_preparation' => [
                    [
                        'text' => 'Research the company’s product and be ready to discuss how your experience aligns.',
                        'kind' => 'inference',
                        'confidence' => 'medium',
                        'source_ids' => [0],
                    ],
                ],
                'sources' => [
                    [
                        'id' => 0,
                        'url' => $url,
                        'title' => $title,
                        'retrieved_at' => now()->toISOString(),
                        'source_type' => 'official_website',
                    ],
                ],
            ];

            // Include up to 3 sources from evidence
            $id = 1;

            foreach (array_slice($evidence, 1, 2) as $ev) {
                $brief['sources'][] = [
                    'id' => $id++,
                    'url' => $ev['url'],
                    'title' => $ev['title'] ?? null,
                    'retrieved_at' => $ev['retrieved_at'] ?? now()->toISOString(),
                    'source_type' => $ev['source_type'] ?? 'reputable_secondary',
                ];
            }
        } else {
            $fallback = $this->fallbackBuilder->fromOpportunity($opportunity, $company, [], null);
            $brief = [
                'overview' => $fallback['overview'],
                'products' => $fallback['products'],
                'technology_context' => $fallback['technology_context'],
                'role_context' => $fallback['role_context'],
                'recent_information' => $fallback['recent_information'],
                'candidate_preparation' => $fallback['candidate_preparation'],
                'sources' => $fallback['sources'],
            ];
        }

        return new CompanyResearchResult(
            schemaVersion: $schemaVersion,
            brief: $brief,
            warnings: [],
            provider: 'fake',
            model: 'fake-model',
            promptVersion: $promptVersion,
            latencyMs: 10,
            tokensPrompt: 100,
            tokensCompletion: 200,
            responseId: null,
        );
    }
}

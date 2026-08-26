<?php

namespace App\Domain\CompanyResearch\Services;

use App\Models\Company;
use App\Models\JobOpportunity;
use Carbon\Carbon;

class FallbackBriefBuilder
{
    /**
     * Build deterministic limited brief from trusted opportunity/company data.
     *
     * @param  array<int, array{url: string, title: ?string, content: string, source_type: string, retrieved_at: ?string}>  $fetchResults
     * @return array<string, mixed>
     */
    public function fromOpportunity(JobOpportunity $opportunity, ?Company $company, array $fetchResults = [], ?string $fallbackReason = null): array
    {
        $now = Carbon::now()->toISOString();
        $companyName = $company !== null ? $company->name : ($opportunity->company_name ?? 'Unknown Company');
        $website = $company !== null && $company->website !== null ? $company->website : $this->extractWebsite($opportunity);
        $industry = $company !== null ? $company->industry : null;
        $location = $company !== null && $company->location !== null ? $company->location : $this->formatLocation($opportunity);

        $description = $this->buildDescription($opportunity);

        $sources = [];
        $sourceId = 0;

        // Opportunity source always present as fallback
        $sources[] = [
            'id' => $sourceId++,
            'url' => $opportunity->source_url ?? 'opportunity://'.$opportunity->id,
            'title' => 'Job posting',
            'retrieved_at' => $now,
            'source_type' => 'opportunity',
        ];

        if ($website !== null) {
            $sources[] = [
                'id' => $sourceId++,
                'url' => $website,
                'title' => 'Company website',
                'retrieved_at' => $now,
                'source_type' => 'official_website',
            ];
        }

        // Include any successfully fetched sources (even if limited)
        foreach ($fetchResults as $fetched) {
            if ($fetched['url'] === '') {
                continue;
            }

            $sources[] = [
                'id' => $sourceId++,
                'url' => $fetched['url'],
                'title' => $fetched['title'],
                'retrieved_at' => $fetched['retrieved_at'] ?? $now,
                'source_type' => $fetched['source_type'],
            ];
        }

        $overview = [
            'name' => $companyName,
            'website' => $website,
            'industry' => $industry,
            'headquarters' => $location,
            'description' => $description,
        ];

        $roleText = $this->buildRoleContext($opportunity);

        $products = [];
        $technology = [
            [
                'text' => 'Technology details could not be reliably established from available sources.',
                'kind' => 'unknown',
                'confidence' => null,
                'source_ids' => [],
            ],
        ];

        $roleContext = $roleText !== null ? [
            [
                'text' => $roleText,
                'kind' => 'inference',
                'confidence' => 'low',
                'source_ids' => [0],
            ],
        ] : [
            [
                'text' => 'Role context could not be determined.',
                'kind' => 'unknown',
                'confidence' => null,
                'source_ids' => [],
            ],
        ];

        $recent = [
            [
                'text' => 'No recent information available from provided sources.',
                'kind' => 'unknown',
                'confidence' => null,
                'source_ids' => [],
            ],
        ];

        $candidate = [
            [
                'text' => 'Review the job description and company website to prepare for discussion about how your experience aligns with the role.',
                'kind' => 'inference',
                'confidence' => 'medium',
                'source_ids' => [0],
            ],
        ];

        // If description is empty, mark products unknown
        if ($description === null || trim($description) === '') {
            $products = [
                [
                    'text' => 'Products or services could not be reliably established.',
                    'kind' => 'unknown',
                    'confidence' => null,
                    'source_ids' => [],
                ],
            ];
        } else {
            // Try to extract no products rather than hallucinate
            $products = [
                [
                    'text' => 'Products or services could not be reliably established from available sources.',
                    'kind' => 'unknown',
                    'confidence' => null,
                    'source_ids' => [],
                ],
            ];
        }

        return [
            'version' => 1,
            'status' => $fallbackReason !== null ? 'limited' : 'completed',
            'overview' => $overview,
            'products' => $products,
            'technology_context' => $technology,
            'role_context' => $roleContext,
            'recent_information' => $recent,
            'candidate_preparation' => $candidate,
            'sources' => $sources,
            'generated_at' => $now,
            'fallback_reason' => $fallbackReason ?? 'fetch_unavailable',
            'ai_meta' => null,
        ];
    }

    private function extractWebsite(JobOpportunity $opportunity): ?string
    {
        $url = $opportunity->source_url ?? $opportunity->application_url;

        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return 'https://'.$host;
            }
        }

        return null;
    }

    private function formatLocation(JobOpportunity $opportunity): ?string
    {
        $parts = array_filter([
            $opportunity->city,
            $opportunity->region,
            $opportunity->country,
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function buildDescription(JobOpportunity $opportunity): ?string
    {
        $summary = $opportunity->summary;

        if ($summary !== null && trim($summary) !== '') {
            $trimmed = trim($summary);

            return mb_substr($trimmed, 0, 500);
        }

        $title = $opportunity->title;

        if ($title !== null && trim($title) !== '') {
            return 'Opportunity for '.trim($title).'.';
        }

        return null;
    }

    private function buildRoleContext(JobOpportunity $opportunity): ?string
    {
        $title = $opportunity->title;
        $dept = $opportunity->department;
        $company = $opportunity->company_name;

        if ($title === null || trim($title) === '') {
            return null;
        }

        $text = 'This '.trim($title);

        if ($dept !== null && trim($dept) !== '') {
            $text .= ' appears to sit in '.trim($dept);
        }

        if ($company !== null && trim($company) !== '') {
            $text .= ' at '.trim($company);
        }

        $text .= '. This is an interpretation based on the job posting and may not reflect the exact team assignment.';

        return $text;
    }
}

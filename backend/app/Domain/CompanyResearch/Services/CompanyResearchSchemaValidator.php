<?php

namespace App\Domain\CompanyResearch\Services;

use App\Domain\CompanyResearch\Data\CompanyResearchResult;
use App\Exceptions\Api\ConflictException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class CompanyResearchSchemaValidator
{
    /**
     * @param  array<int, array{url: string}>  $evidence
     */
    public function validate(CompanyResearchResult $result, array $evidence): array
    {
        $expectedVersion = Config::string('company-research.schema_version', '1.0.0');

        $validator = Validator::make([
            'schema_version' => $result->schemaVersion,
            'brief' => $result->brief,
            'warnings' => $result->warnings,
        ], $this->rules($expectedVersion));

        if ($validator->fails()) {
            throw new ConflictException(
                'AI output did not match company research schema.',
                'invalid_ai_output',
                [
                    'processing_stage' => 'schema_validation',
                    'provider_response_received' => true,
                    'validation_paths' => array_keys($validator->errors()->toArray()),
                ],
            );
        }

        $brief = $result->brief;
        $sources = $brief['sources'] ?? [];
        $evidenceUrls = array_map(fn ($e) => strtolower(trim($e['url'] ?? '')), $evidence);
        $evidenceUrlSet = array_flip(array_filter($evidenceUrls));

        // Validate sources URLs are in evidence set
        foreach ($sources as $src) {
            $url = strtolower(trim($src['url'] ?? ''));

            if ($url === '' || ! isset($evidenceUrlSet[$url])) {
                // Allow sources that are opportunity/pasted synthetic URLs? But spec says no fabricated URLs.
                // If source is synthetic opportunity:// we may allow if it came from evidence set (which includes it)
                // If not in set, reject
                throw new ConflictException(
                    'AI output contains fabricated source URL.',
                    'invalid_ai_output',
                    [
                        'processing_stage' => 'source_validation',
                        'provider_response_received' => true,
                        'validation_paths' => ['brief.sources.url'],
                    ],
                );
            }
        }

        // Map source ids for existence check
        $sourceIds = array_map(fn ($s) => $s['id'] ?? null, $sources);
        $sourceIdSet = array_flip(array_filter($sourceIds, fn ($id) => is_int($id)));

        $sections = ['products', 'technology_context', 'role_context', 'recent_information', 'candidate_preparation'];

        foreach ($sections as $section) {
            $items = $brief[$section] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $idx => $item) {
                $kind = $item['kind'] ?? null;
                $sourceIdsForItem = $item['source_ids'] ?? [];

                if (! in_array($kind, ['fact', 'inference', 'unknown'], true)) {
                    throw new ConflictException(
                        'Invalid kind in company research.',
                        'invalid_ai_output',
                        [
                            'processing_stage' => 'kind_validation',
                            'provider_response_received' => true,
                            'validation_paths' => ["brief.$section.$idx.kind"],
                        ],
                    );
                }

                if ($kind === 'fact' || $kind === 'inference') {
                    if (! is_array($sourceIdsForItem) || count($sourceIdsForItem) === 0) {
                        throw new ConflictException(
                            'Fact/inference must cite sources.',
                            'invalid_ai_output',
                            [
                                'processing_stage' => 'provenance_validation',
                                'provider_response_received' => true,
                                'validation_paths' => ["brief.$section.$idx.source_ids"],
                            ],
                        );
                    }

                    foreach ($sourceIdsForItem as $sid) {
                        if (! isset($sourceIdSet[$sid])) {
                            throw new ConflictException(
                                'Claim references unknown source id.',
                                'invalid_ai_output',
                                [
                                    'processing_stage' => 'provenance_validation',
                                    'provider_response_received' => true,
                                    'validation_paths' => ["brief.$section.$idx.source_ids"],
                                ],
                            );
                        }
                    }

                    // Technology context extra check: if kind is fact, ensure text is not hallucinated as definitive assignment
                    // We check that inferences about role tech are labeled inference; facts about products must have source.
                    // Here we enforce that if technology_context item says "will work on" definitive, it must be inference
                    if ($section === 'technology_context' && $kind === 'fact') {
                        $text = strtolower($item['text'] ?? '');

                        if (str_contains($text, 'will definitely') || str_contains($text, 'will work on')) {
                            throw new ConflictException(
                                'Technology inference mislabeled as fact.',
                                'invalid_ai_output',
                                [
                                    'processing_stage' => 'fact_inference_validation',
                                    'provider_response_received' => true,
                                    'validation_paths' => ["brief.$section.$idx.kind"],
                                ],
                            );
                        }
                    }
                }

                if ($kind === 'unknown' && is_array($sourceIdsForItem) && count($sourceIdsForItem) !== 0) {
                    throw new ConflictException(
                        'Unknown kind must not have sources.',
                        'invalid_ai_output',
                        [
                            'processing_stage' => 'provenance_validation',
                            'provider_response_received' => true,
                            'validation_paths' => ["brief.$section.$idx.source_ids"],
                        ],
                    );
                }
            }
        }

        return $brief;
    }

    private function rules(string $schemaVersion): array
    {
        return [
            'schema_version' => ['required', 'string', "in:{$schemaVersion}"],
            'brief' => ['required', 'array'],
            'brief.overview' => ['required', 'array'],
            'brief.overview.name' => ['nullable', 'string', 'max:255'],
            'brief.overview.website' => ['nullable', 'string', 'max:500'],
            'brief.overview.industry' => ['nullable', 'string', 'max:255'],
            'brief.overview.headquarters' => ['nullable', 'string', 'max:500'],
            'brief.overview.description' => ['nullable', 'string', 'max:5000'],
            'brief.products' => ['required', 'array'],
            'brief.products.*.text' => ['required', 'string', 'max:2000'],
            'brief.products.*.kind' => ['required', 'in:fact,inference,unknown'],
            'brief.products.*.confidence' => ['nullable', 'in:low,medium,high'],
            'brief.products.*.source_ids' => ['required', 'array'],
            'brief.products.*.source_ids.*' => ['integer'],
            'brief.technology_context' => ['required', 'array'],
            'brief.technology_context.*.text' => ['required', 'string', 'max:2000'],
            'brief.technology_context.*.kind' => ['required', 'in:fact,inference,unknown'],
            'brief.technology_context.*.confidence' => ['nullable', 'in:low,medium,high'],
            'brief.technology_context.*.source_ids' => ['required', 'array'],
            'brief.technology_context.*.source_ids.*' => ['integer'],
            'brief.role_context' => ['required', 'array'],
            'brief.role_context.*.text' => ['required', 'string', 'max:2000'],
            'brief.role_context.*.kind' => ['required', 'in:fact,inference,unknown'],
            'brief.role_context.*.confidence' => ['nullable', 'in:low,medium,high'],
            'brief.role_context.*.source_ids' => ['required', 'array'],
            'brief.role_context.*.source_ids.*' => ['integer'],
            'brief.recent_information' => ['required', 'array'],
            'brief.recent_information.*.text' => ['required', 'string', 'max:2000'],
            'brief.recent_information.*.kind' => ['required', 'in:fact,inference,unknown'],
            'brief.recent_information.*.confidence' => ['nullable', 'in:low,medium,high'],
            'brief.recent_information.*.source_ids' => ['required', 'array'],
            'brief.recent_information.*.source_ids.*' => ['integer'],
            'brief.candidate_preparation' => ['required', 'array'],
            'brief.candidate_preparation.*.text' => ['required', 'string', 'max:2000'],
            'brief.candidate_preparation.*.kind' => ['required', 'in:fact,inference,unknown'],
            'brief.candidate_preparation.*.confidence' => ['nullable', 'in:low,medium,high'],
            'brief.candidate_preparation.*.source_ids' => ['required', 'array'],
            'brief.candidate_preparation.*.source_ids.*' => ['integer'],
            'brief.sources' => ['required', 'array'],
            'brief.sources.*.id' => ['required', 'integer', 'min:0'],
            'brief.sources.*.url' => ['required', 'string', 'max:2048'],
            'brief.sources.*.title' => ['nullable', 'string', 'max:500'],
            'brief.sources.*.retrieved_at' => ['nullable', 'string'],
            'brief.sources.*.source_type' => ['required', 'in:official_website,official_blog,careers,job_posting,reputable_secondary,opportunity,pasted'],
            'warnings' => ['present', 'array'],
            'warnings.*' => ['string'],
        ];
    }
}

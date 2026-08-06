<?php

namespace App\Domain\Matching\Services;

use App\Models\JobOpportunity;
use App\Models\JobOpportunitySkill;

final class OpportunitySnapshot
{
    /**
     * @param  list<array{id: int, category: string|null, content: string, classification: string|null, language: string|null, display_order: int}>  $requirements
     * @param  list<array{id: int, normalized_name: string, original_label: string|null, classification: string|null, display_order: int}>  $skills
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $companyName,
        public readonly ?string $summary,
        public readonly ?string $workMode,
        public readonly ?string $contractType,
        public readonly ?string $seniorityLevel,
        public readonly array $requirements,
        public readonly array $skills,
    ) {}

    public static function fromJobOpportunity(JobOpportunity $opportunity): self
    {
        $opportunity->loadMissing('requirements', 'skills.skill');

        $requirements = $opportunity->requirements
            ->map(fn ($requirement): array => [
                'id' => $requirement->id,
                'category' => $requirement->category,
                'content' => (string) $requirement->content,
                'classification' => $requirement->classification,
                'language' => $requirement->language,
                'display_order' => (int) $requirement->display_order,
            ])
            ->sortBy(fn (array $requirement): int => $requirement['display_order'])
            ->values()
            ->all();

        $skills = $opportunity->skills
            ->map(function (JobOpportunitySkill $opportunitySkill): array {
                $normalizedName = $opportunitySkill->skill->normalized_name
                    ?? TextNormalizer::normalize((string) $opportunitySkill->original_label);

                return [
                    'id' => $opportunitySkill->id,
                    'normalized_name' => $normalizedName,
                    'original_label' => $opportunitySkill->original_label,
                    'classification' => $opportunitySkill->classification,
                    'display_order' => (int) $opportunitySkill->display_order,
                ];
            })
            ->sortBy(fn (array $skill): int => $skill['display_order'])
            ->values()
            ->all();

        return new self(
            title: (string) $opportunity->title,
            companyName: $opportunity->company_name,
            summary: $opportunity->summary,
            workMode: $opportunity->work_mode,
            contractType: $opportunity->contract_type,
            seniorityLevel: $opportunity->seniority_level,
            requirements: $requirements,
            skills: $skills,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCanonicalArray(): array
    {
        $canonical = [
            'title' => $this->title,
            'company_name' => $this->companyName,
            'summary' => $this->summary,
            'work_mode' => $this->workMode,
            'contract_type' => $this->contractType,
            'seniority_level' => $this->seniorityLevel,
            'requirements' => $this->requirements,
            'skills' => $this->skills,
        ];

        return self::sortRecursively($canonical);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $value
     * @return array<string, mixed>|list<mixed>
     */
    private static function sortRecursively(array $value): array
    {
        if (array_is_list($value)) {
            $mapped = array_map(static fn (mixed $item): mixed => is_array($item) ? self::sortRecursively($item) : $item, $value);

            usort($mapped, static fn (mixed $a, mixed $b): int => strcmp((string) json_encode($a), (string) json_encode($b)));

            return $mapped;
        }

        $sorted = [];
        foreach ($value as $key => $item) {
            $sorted[$key] = is_array($item) ? self::sortRecursively($item) : $item;
        }
        ksort($sorted);

        return $sorted;
    }
}

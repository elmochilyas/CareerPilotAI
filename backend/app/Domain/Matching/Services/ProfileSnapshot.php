<?php

namespace App\Domain\Matching\Services;

use App\Models\CandidateProfile;

final class ProfileSnapshot
{
    /**
     * @param  list<array{language: string, proficiency: string|null}>  $languages
     * @param  list<array{id: int, normalized_name: string, state: string, years_experience: float|null, evidence: list<array{type: string, id: int|null, label: string|null}>}>  $skills
     * @param  list<array{id: int, type: string, title: string, organization: string|null, description: string|null}>  $items
     * @param  list<string>  $targetRoles
     * @param  list<string>  $workModes
     * @param  list<string>  $contractTypes
     */
    public function __construct(
        public readonly ?string $headline,
        public readonly ?string $professionalSummary,
        public readonly array $targetRoles,
        public readonly array $workModes,
        public readonly array $contractTypes,
        public readonly array $languages,
        public readonly array $skills,
        public readonly array $items,
    ) {}

    public static function fromCandidateProfile(CandidateProfile $profile): self
    {
        $profile->loadMissing('candidateSkills.skill', 'items');

        $skills = $profile->candidateSkills
            ->map(function ($candidateSkill): array {
                $normalizedName = $candidateSkill->skill->normalized_name
                    ?? TextNormalizer::normalize((string) $candidateSkill->custom_skill_name);

                return [
                    'id' => $candidateSkill->id,
                    'normalized_name' => $normalizedName,
                    'state' => $candidateSkill->state->value,
                    'years_experience' => $candidateSkill->years_experience !== null
                        ? (float) $candidateSkill->years_experience
                        : null,
                    'evidence' => self::evidenceRefs($candidateSkill->evidence),
                ];
            })
            ->sortBy(fn (array $skill): array => [$skill['normalized_name'], $skill['state'], $skill['id']])
            ->values()
            ->all();

        $items = $profile->items
            ->map(fn ($item): array => [
                'id' => $item->id,
                'type' => $item->type->value,
                'title' => (string) $item->title,
                'organization' => $item->organization,
                'description' => $item->description,
            ])
            ->sortBy(fn (array $item): array => [$item['type'], $item['title'], $item['id']])
            ->values()
            ->all();

        $languages = collect($profile->languages ?? [])
            ->map(static fn (array $entry): array => [
                'language' => $entry['language'],
                'proficiency' => $entry['proficiency'],
            ])
            ->sortBy(fn (array $entry): array => [$entry['language'], $entry['proficiency']])
            ->values()
            ->all();

        return new self(
            headline: $profile->headline,
            professionalSummary: $profile->professional_summary,
            targetRoles: array_values(array_filter((array) ($profile->target_roles ?? []), 'is_string')),
            workModes: array_values(array_filter((array) ($profile->work_modes ?? []), 'is_string')),
            contractTypes: array_values(array_filter((array) ($profile->contract_types ?? []), 'is_string')),
            languages: $languages,
            skills: $skills,
            items: $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCanonicalArray(): array
    {
        $canonical = [
            'headline' => $this->headline,
            'professional_summary' => $this->professionalSummary,
            'target_roles' => $this->targetRoles,
            'work_modes' => $this->workModes,
            'contract_types' => $this->contractTypes,
            'languages' => $this->languages,
            'skills' => $this->skills,
            'items' => $this->items,
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

    /**
     * @param  array<int, mixed>|null  $evidence
     * @return list<array{type: string, id: int|null, label: string|null}>
     */
    private static function evidenceRefs(?array $evidence): array
    {
        if (! is_array($evidence)) {
            return [];
        }

        $refs = [];
        foreach ($evidence as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $refs[] = [
                'type' => isset($entry['type']) && is_string($entry['type']) ? $entry['type'] : 'evidence',
                'id' => isset($entry['id']) && is_int($entry['id']) ? $entry['id'] : null,
                'label' => isset($entry['label']) && is_string($entry['label'])
                    ? $entry['label']
                    : (isset($entry['value']) && is_string($entry['value']) ? $entry['value'] : null),
            ];
        }

        return $refs;
    }
}

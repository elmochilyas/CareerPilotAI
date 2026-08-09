<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResolveJobSkillsAction
{
    public function execute(JobOpportunityIngestion $ingestion): void
    {
        $skillSuggestions = $ingestion->suggestions()
            ->whereIn('type', [SuggestionType::RequiredSkill->value, SuggestionType::PreferredSkill->value])
            ->whereNull('resolution')
            ->get();

        $allSkills = Skill::all()->keyBy(fn (Skill $s) => $s->normalized_name);
        $allAliases = SkillAlias::all()->keyBy(fn (SkillAlias $a) => $a->alias);

        $resolved = [];

        foreach ($skillSuggestions as $suggestion) {
            $label = $this->getLabel($suggestion);

            if ($label === null) {
                continue;
            }

            $normalized = $this->normalizeLabel($label);

            $resolution = $this->resolveSkill($normalized, $suggestion, $allSkills, $allAliases);

            $suggestion->update([
                'resolution' => $resolution['state'],
                'resolved_skill_id' => $resolution['skill_id'],
                'version' => $suggestion->version + 1,
            ]);

            $dupKey = $resolution['skill_id'] ?? $normalized;
            $classification = $suggestion->type === SuggestionType::RequiredSkill ? 'required' : 'preferred';

            if (isset($resolved[$classification][$dupKey])) {
                $existing = $resolved[$classification][$dupKey];
                $existing['evidence'][] = $suggestion->source_evidence;
                $existing['suggestion_ids'][] = $suggestion->id;
                $resolved[$classification][$dupKey] = $existing;
            } else {
                $resolved[$classification][$dupKey] = [
                    'skill_id' => $resolution['skill_id'],
                    'state' => $resolution['state'],
                    'evidence' => [$suggestion->source_evidence],
                    'suggestion_ids' => [$suggestion->id],
                ];
            }
        }
    }

    private function getLabel(JobOpportunitySuggestion $suggestion): ?string
    {
        $value = $suggestion->extracted_value;
        $label = $value['label'] ?? $value['name'] ?? null;

        return is_string($label) ? $label : null;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label)));
    }

    /**
     * @return array{state: SkillResolutionState, skill_id: int|null}
     */
    private function resolveSkill(
        string $normalized,
        JobOpportunitySuggestion $suggestion,
        Collection $allSkills,
        Collection $allAliases,
    ): array {
        $exact = $allSkills->get($normalized);

        if ($exact !== null) {
            return ['state' => SkillResolutionState::Exact, 'skill_id' => $exact->id];
        }

        $alias = $allAliases->get($normalized);

        if ($alias !== null) {
            return ['state' => SkillResolutionState::Alias, 'skill_id' => $alias->skill_id];
        }

        $extracted = $suggestion->extracted_value;
        $extractedLabel = $extracted['label'] ?? $extracted['name'] ?? null;
        $label = is_string($extractedLabel) ? $extractedLabel : $normalized;

        if (DB::getDriverName() === 'mysql') {
            $partialMatches = Skill::whereFullText(['normalized_name', 'name'], $label, ['mode' => 'natural'])
                ->limit(5)
                ->get();
        } else {
            $partialMatches = collect();
        }

        if ($partialMatches->count() === 0) {
            $partialMatches = $allSkills->filter(
                fn (Skill $s) => str_contains($s->normalized_name, $normalized)
            )->take(5)->values();
        }

        if ($partialMatches->count() === 1) {
            return ['state' => SkillResolutionState::Alias, 'skill_id' => $partialMatches->first()->id];
        }

        if ($partialMatches->count() > 1) {
            return ['state' => SkillResolutionState::Ambiguous, 'skill_id' => null];
        }

        return ['state' => SkillResolutionState::Unknown, 'skill_id' => null];
    }
}

<?php

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\SkillResolutionState;
use App\Domain\Opportunities\Enums\SuggestionType;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\Skill;
use App\Models\SkillAlias;

class ResolveJobSkillsAction
{
    public function execute(JobOpportunityIngestion $ingestion): void
    {
        $skillSuggestions = $ingestion->suggestions()
            ->whereIn('type', [SuggestionType::RequiredSkill->value, SuggestionType::PreferredSkill->value])
            ->whereNull('resolution')
            ->get();

        $resolved = [];

        foreach ($skillSuggestions as $suggestion) {
            $label = $this->getLabel($suggestion);

            if ($label === null) {
                continue;
            }

            $normalized = $this->normalizeLabel($label);

            $resolution = $this->resolveSkill($normalized, $suggestion, $resolved);

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

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $value['label'] ?? $value['name'] ?? null;
        }

        return null;
    }

    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label)));
    }

    private function resolveSkill(string $normalized, JobOpportunitySuggestion $suggestion, array &$resolved): array
    {
        $exact = Skill::where('normalized_name', $normalized)->first();

        if ($exact !== null) {
            return ['state' => SkillResolutionState::Exact, 'skill_id' => $exact->id];
        }

        $alias = SkillAlias::where('alias', $normalized)->first();

        if ($alias !== null) {
            return ['state' => SkillResolutionState::Alias, 'skill_id' => $alias->skill_id];
        }

        $extracted = $suggestion->extracted_value;
        $label = is_array($extracted) ? ($extracted['label'] ?? $extracted['name'] ?? $normalized) : $normalized;
        $partialMatches = Skill::where('normalized_name', 'like', "%{$normalized}%")
            ->orWhere('name', 'like', "%{$label}%")
            ->limit(5)
            ->get();

        if ($partialMatches->count() === 1) {
            return ['state' => SkillResolutionState::Alias, 'skill_id' => $partialMatches->first()->id];
        }

        if ($partialMatches->count() > 1) {
            return ['state' => SkillResolutionState::Ambiguous, 'skill_id' => null];
        }

        return ['state' => SkillResolutionState::Unknown, 'skill_id' => null];
    }
}

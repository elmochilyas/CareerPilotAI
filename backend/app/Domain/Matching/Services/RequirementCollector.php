<?php

namespace App\Domain\Matching\Services;

use App\Domain\Matching\Data\MatchRequirement;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\MatchImportance;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Models\JobOpportunity;

final class RequirementCollector
{
    /**
     * @return list<MatchRequirement>
     */
    public function collect(JobOpportunity $opportunity): array
    {
        $requirements = [];
        $order = 0;

        $opportunity->loadMissing('requirements', 'skills.skill');

        foreach ($opportunity->requirements()->orderBy('display_order')->get() as $jobRequirement) {
            $category = $this->categoryForRequirement($jobRequirement->category);

            if ($category === null) {
                continue;
            }

            $requirements[] = new MatchRequirement(
                sourceType: RequirementSourceType::JobRequirement,
                sourceId: $jobRequirement->id,
                text: $this->truncate((string) $jobRequirement->content),
                label: null,
                importance: $jobRequirement->classification === 'preferred'
                    ? MatchImportance::Preferred
                    : MatchImportance::Required,
                category: $category->value,
                sourceCategory: $jobRequirement->category,
                language: $jobRequirement->language,
                displayOrder: $order++,
            );
        }

        foreach ($opportunity->skills()->with('skill')->orderBy('display_order')->get() as $opportunitySkill) {
            $importance = $opportunitySkill->classification === 'preferred'
                ? MatchImportance::Preferred
                : MatchImportance::Required;

            $requirements[] = new MatchRequirement(
                sourceType: RequirementSourceType::JobOpportunitySkill,
                sourceId: $opportunitySkill->id,
                text: $this->truncate($opportunitySkill->original_label
                    ?? $opportunitySkill->skill->name
                    ?? TextNormalizer::normalize('')),
                label: $opportunitySkill->original_label,
                importance: $importance,
                category: $importance === MatchImportance::Preferred
                    ? MatchCategory::PreferredSkills->value
                    : MatchCategory::RequiredSkills->value,
                sourceCategory: $opportunitySkill->classification,
                language: null,
                displayOrder: $order++,
            );
        }

        $max = (int) config('matching.max_requirements_per_analysis');

        return array_slice($requirements, 0, $max);
    }

    private function categoryForRequirement(?string $category): ?MatchCategory
    {
        return match ($category) {
            'responsibility', 'required_certification', 'preferred_certification' => MatchCategory::Evidence,
            'required_experience', 'preferred_experience', 'education' => MatchCategory::ExperienceEducation,
            'language' => MatchCategory::LanguageSoft,
            default => null,
        };
    }

    /**
     * Keeps requirement text within the `match_findings.requirement_text`
     * column limit to avoid overflow on MySQL strict mode.
     */
    private function truncate(string $text): string
    {
        return mb_strimwidth($text, 0, (int) config('matching.max_requirement_text_length', 500), '', 'UTF-8');
    }
}

<?php

namespace App\Domain\Profile\Services;

use App\Domain\Profile\Enums\ProfileItemType;
use App\Models\CandidateProfile;

class ProfileCompletionService
{
    /** @return array{score: float, areas: list<array{key: string, earned: int, available: int, complete: bool, guidance: string|null}>, missing_areas: list<array{key: string, guidance: string}>} */
    public function calculate(CandidateProfile $profile): array
    {
        $profile->loadMissing('items');
        $areas = [
            $this->area('basic_information', $this->basicInformationPoints($profile), 10, 'Add your phone, city, and country.'),
            $this->area('headline', $this->hasText($profile->headline) ? 10 : 0, 10, 'Add a professional headline.'),
            $this->area('professional_summary', $this->hasText($profile->professional_summary) ? 15 : 0, 15, 'Add a professional summary.'),
            $this->area('professional_links', $this->hasProfessionalLink($profile) ? 10 : 0, 10, 'Add LinkedIn, GitHub, or a portfolio link.'),
            $this->area('target_roles', $this->hasArrayValues($profile->target_roles) ? 15 : 0, 15, 'Add at least one target role.'),
            $this->area('career_preferences', $this->careerPreferencePoints($profile), 10, 'Complete preferred locations, work modes, and contract types.'),
            $this->area('languages', $this->hasArrayValues($profile->languages) ? 10 : 0, 10, 'Add at least one language.'),
            $this->area('education', $profile->items->contains('type', ProfileItemType::Education) ? 10 : 0, 10, 'Add an education record.'),
            $this->area('practical_background', $profile->items->contains(fn ($item): bool => in_array($item->type, [ProfileItemType::Experience, ProfileItemType::Project], true)) ? 10 : 0, 10, 'Add an experience or project.'),
        ];
        $score = (float) min(100, max(0, array_sum(array_column($areas, 'earned'))));
        $missing = array_values(array_map(
            fn (array $area): array => ['key' => $area['key'], 'guidance' => (string) $area['guidance']],
            array_filter($areas, fn (array $area): bool => ! $area['complete']),
        ));

        return ['score' => $score, 'areas' => $areas, 'missing_areas' => $missing];
    }

    public function persist(CandidateProfile $profile): array
    {
        $result = $this->calculate($profile);
        $profile->forceFill(['profile_completion' => $result['score']])->save();

        return $result;
    }

    public function basicInformationPoints(CandidateProfile $profile): int
    {
        return ($this->hasText($profile->phone) ? 4 : 0) + ($this->hasText($profile->city) ? 3 : 0) + ($this->hasText($profile->country) ? 3 : 0);
    }

    public function careerPreferencePoints(CandidateProfile $profile): int
    {
        return ($this->hasArrayValues($profile->preferred_locations) ? 4 : 0) + ($this->hasArrayValues($profile->work_modes) ? 3 : 0) + ($this->hasArrayValues($profile->contract_types) ? 3 : 0);
    }

    public function hasProfessionalLink(CandidateProfile $profile): bool
    {
        return $this->hasText($profile->linkedin_url) || $this->hasText($profile->github_url) || $this->hasText($profile->portfolio_url);
    }

    /** @return array{key: string, earned: int, available: int, complete: bool, guidance: string|null} */
    private function area(string $key, int $earned, int $available, string $guidance): array
    {
        return ['key' => $key, 'earned' => $earned, 'available' => $available, 'complete' => $earned === $available, 'guidance' => $earned === $available ? null : $guidance];
    }

    private function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function hasArrayValues(?array $value): bool
    {
        return $value !== null && $value !== [];
    }
}

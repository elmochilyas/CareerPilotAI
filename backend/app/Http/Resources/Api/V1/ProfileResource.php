<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Profile\Enums\ProfileItemType;
use App\Domain\Profile\Services\ProfileCompletionService;
use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateProfile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('items');
        $completion = app(ProfileCompletionService::class)->calculate($this->resource);
        $groups = [];
        foreach (ProfileItemType::cases() as $type) {
            $groups[$type->value] = ProfileItemResource::collection($this->items->where('type', $type)->values());
        }

        return [
            'id' => $this->id,
            'full_name' => $request->user()?->full_name,
            'headline' => $this->headline,
            'professional_summary' => $this->professional_summary,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'linkedin_url' => $this->linkedin_url,
            'github_url' => $this->github_url,
            'portfolio_url' => $this->portfolio_url,
            'availability_status' => $this->availability_status?->value,
            'availability_date' => $this->availability_date?->toDateString(),
            'target_roles' => $this->target_roles ?? [],
            'preferred_locations' => $this->preferred_locations ?? [],
            'work_modes' => $this->work_modes ?? [],
            'contract_types' => $this->contract_types ?? [],
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,
            'salary_period' => $this->salary_period,
            'languages' => $this->languages ?? [],
            'profile_completion' => $completion['score'],
            'completion_details' => [
                'areas' => $completion['areas'],
                'missing_areas' => $completion['missing_areas'],
            ],
            'items' => $groups,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin JobOpportunity */
class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company_name' => $this->company_name,
            'department' => $this->department,
            'external_reference' => $this->external_reference,
            'summary' => $this->summary,
            'application_url' => $this->application_url,
            'personal_label' => $this->personal_label,
            'source_url' => $this->source_url,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'work_mode' => $this->work_mode,
            'contract_type' => $this->contract_type,
            'seniority_level' => $this->seniority_level,
            'working_hours' => $this->working_hours,
            'travel_required' => $this->travel_required,
            'relocation_required' => $this->relocation_required,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,
            'salary_period' => $this->salary_period,
            'compensation_text' => $this->compensation_text,
            'benefits' => $this->benefits,
            'publication_date' => $this->publication_date?->toISOString(),
            'application_deadline' => $this->application_deadline?->toISOString(),
            'expected_start_date' => $this->expected_start_date?->toISOString(),
            'employment_duration' => $this->employment_duration,
            'additional_requirements' => $this->additional_requirements,
            'requirements' => RequirementResource::collection($this->whenLoaded('requirements')),
            'skills' => OpportunitySkillResource::collection($this->whenLoaded('skills')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'saved_at' => $this->saved_at->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

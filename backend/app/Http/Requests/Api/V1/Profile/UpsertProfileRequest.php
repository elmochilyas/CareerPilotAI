<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Domain\Profile\Enums\AvailabilityStatus;
use App\Domain\Profile\Enums\ContractType;
use App\Domain\Profile\Enums\LanguageProficiency;
use App\Domain\Profile\Enums\WorkMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'professional_summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[+\d\s\-()]+$/'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'linkedin_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'github_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'portfolio_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'availability_status' => ['sometimes', 'nullable', Rule::enum(AvailabilityStatus::class)],
            'availability_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'target_roles' => ['sometimes', 'nullable', 'array', 'max:10'],
            'target_roles.*' => ['string', 'max:100', 'distinct'],
            'preferred_locations' => ['sometimes', 'nullable', 'array', 'max:10'],
            'preferred_locations.*' => ['string', 'max:100', 'distinct'],
            'work_modes' => ['sometimes', 'nullable', 'array', 'min:0', 'max:5'],
            'work_modes.*' => [Rule::enum(WorkMode::class)],
            'contract_types' => ['sometimes', 'nullable', 'array', 'max:5'],
            'contract_types.*' => [Rule::enum(ContractType::class), 'distinct'],
            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'salary_period' => ['sometimes', 'nullable', Rule::in(['monthly', 'annual'])],
            'languages' => ['sometimes', 'nullable', 'array', 'max:10'],
            'languages.*.language' => ['required', 'string', 'max:50'],
            'languages.*.proficiency' => ['required', Rule::enum(LanguageProficiency::class)],
            'updated_at' => ['sometimes', 'nullable', 'date'],
            'user_id' => ['prohibited'],
            'candidate_profile_id' => ['prohibited'],
            'profile_completion' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $minimum = $this->input('salary_min');
            $maximum = $this->input('salary_max');
            if ($minimum !== null && $maximum !== null && (float) $maximum < (float) $minimum) {
                $validator->errors()->add('salary_max', 'The salary maximum must be greater than or equal to the salary minimum.');
            }
        }];
    }
}

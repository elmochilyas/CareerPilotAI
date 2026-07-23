<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Domain\Profile\Enums\ProfileItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProfileItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProfileItemType::class)],
            'title' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_current' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array', 'max:15'],
            'metadata.degree' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.employment_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'metadata.project_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'metadata.repository_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'metadata.role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.technologies' => ['sometimes', 'nullable', 'array', 'max:20'],
            'metadata.technologies.*' => ['string', 'max:50', 'distinct'],
            'metadata.issuer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.credential_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata.credential_url' => ['sometimes', 'nullable', 'url:http,https', 'starts_with:https://', 'max:500'],
            'metadata.expiry_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'candidate_profile_id' => ['prohibited'],
            'display_order' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->input('type');
            $isCurrent = (bool) $this->input('is_current', false);
            $endDate = $this->input('end_date');
            $startDate = $this->input('start_date');
            $expiryDate = $this->input('metadata.expiry_date');
            $issueDate = $this->input('start_date');

            if ($isCurrent && $endDate !== null) {
                $validator->errors()->add('end_date', 'End date must be null when the item is current.');
            }

            if ($type === 'certification' && $expiryDate && $issueDate && $expiryDate < $issueDate) {
                $validator->errors()->add('metadata.expiry_date', 'Expiration date must be after or equal to the issue date.');
            }

        }];
    }
}

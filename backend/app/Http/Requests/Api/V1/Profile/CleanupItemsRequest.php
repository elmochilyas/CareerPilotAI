<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Models\CandidateProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CleanupItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $profileId = CandidateProfile::where('user_id', $this->user()?->id)->value('id');

        return [
            'keep_id' => ['required', 'integer', Rule::exists('profile_items', 'id')->where('candidate_profile_id', $profileId)],
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['required', 'integer', 'distinct', Rule::exists('profile_items', 'id')->where('candidate_profile_id', $profileId)],
            'allow_possible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $data = $validator->validated();

                if (! isset($data['keep_id'], $data['duplicate_ids'])) {
                    return;
                }

                if (in_array($data['keep_id'], $data['duplicate_ids'], true)) {
                    $validator->errors()->add('keep_id', 'The keep id must not be included in duplicate ids.');
                    $validator->errors()->add('duplicate_ids', 'The keep id must not be included in duplicate ids.');
                }

                // Guard against client-supplied profile_id injection
                if ($this->has('candidate_profile_id') || $this->has('profile_id')) {
                    $validator->errors()->add('candidate_profile_id', 'Profile ownership is derived from the authenticated user.');
                }
            },
        ];
    }
}

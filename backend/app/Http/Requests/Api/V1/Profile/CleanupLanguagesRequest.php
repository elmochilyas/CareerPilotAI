<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CleanupLanguagesRequest extends FormRequest
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
        return [
            'keep_language' => ['sometimes', 'string', 'max:50'],
            'duplicate_languages' => ['sometimes', 'array', 'min:1'],
            'duplicate_languages.*' => ['required', 'string', 'max:50', 'distinct'],
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

                if (isset($data['keep_language'], $data['duplicate_languages'])) {
                    $normKeep = mb_strtolower(trim($data['keep_language']));
                    foreach ($data['duplicate_languages'] as $dup) {
                        if (mb_strtolower(trim($dup)) === $normKeep) {
                            $validator->errors()->add('keep_language', 'The keep language must not be included in duplicate languages.');
                            $validator->errors()->add('duplicate_languages', 'The keep language must not be included in duplicate languages.');
                            break;
                        }
                    }
                }

                if ($this->has('candidate_profile_id') || $this->has('profile_id')) {
                    $validator->errors()->add('candidate_profile_id', 'Profile ownership is derived from the authenticated user.');
                }
            },
        ];
    }
}

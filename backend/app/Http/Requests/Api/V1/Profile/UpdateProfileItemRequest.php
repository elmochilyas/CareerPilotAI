<?php

namespace App\Http\Requests\Api\V1\Profile;

class UpdateProfileItemRequest extends StoreProfileItemRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['type'] = ['prohibited'];
        $rules['title'][0] = 'sometimes';
        $rules['updated_at'] = ['sometimes', 'nullable', 'date'];

        return $rules;
    }
}

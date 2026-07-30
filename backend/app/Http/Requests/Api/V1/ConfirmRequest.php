<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'version_token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }
}

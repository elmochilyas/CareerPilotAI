<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Domain\Profile\Enums\ProfileItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderProfileItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(ProfileItemType::class)], 'item_ids' => ['required', 'array'], 'item_ids.*' => ['required', 'integer', 'distinct']];
    }
}

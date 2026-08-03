<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

class StoreIngestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $maxDesc = Config::integer('job-ingestion.max_description_length', 100000);
        $minDesc = Config::integer('job-ingestion.min_description_length', 50);
        $maxUrl = Config::integer('job-ingestion.max_source_url_length', 500);
        $maxLabel = Config::integer('job-ingestion.max_label_length', 255);

        return [
            'source_description' => [
                'required',
                'string',
                'min:'.$minDesc,
                'max:'.$maxDesc,
            ],
            'source_url' => [
                'nullable',
                'string',
                'max:'.$maxUrl,
                'regex:/^https?:\/\//i',
            ],
            'personal_label' => [
                'nullable',
                'string',
                'max:'.$maxLabel,
            ],
        ];
    }

    public function messages(): array
    {
        $minDesc = Config::integer('job-ingestion.min_description_length', 50);

        return [
            'source_description.required' => 'The job description is required.',
            'source_description.min' => "The description must be at least {$minDesc} characters.",
            'source_url.regex' => 'The source URL must be a valid HTTP or HTTPS URL.',
        ];
    }
}

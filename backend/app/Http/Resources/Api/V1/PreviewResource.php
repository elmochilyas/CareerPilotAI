<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this['data'],
            'version_token' => $this['version_token'],
            'schema_version' => $this['schema_version'],
        ];
    }
}

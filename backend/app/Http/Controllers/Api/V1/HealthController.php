<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HealthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function show(): HealthResource|JsonResponse
    {
        $dbStatus = 'up';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbStatus = 'down';
        }

        return new HealthResource([
            'status' => $dbStatus === 'up' ? 'pass' : 'fail',
            'services' => [
                'database' => $dbStatus,
            ],
        ]);
    }
}

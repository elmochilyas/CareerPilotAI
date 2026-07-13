<?php

namespace App\Http\Middleware;

use App\Support\RequestIdContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');

        if ($requestId === null) {
            $requestId = (string) Str::uuid();
        }

        RequestIdContext::set($requestId);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        if ($response instanceof JsonResponse) {
            $data = json_decode($response->getContent(), true);

            if (is_array($data) && ! array_key_exists('request_id', $data)) {
                $data['request_id'] = $requestId;
                $response->setData($data);
            }
        }

        return $response;
    }
}

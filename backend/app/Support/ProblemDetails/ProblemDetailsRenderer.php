<?php

namespace App\Support\ProblemDetails;

use App\Support\RequestIdContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProblemDetailsRenderer
{
    public function render(Request $request, \Throwable $e): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        $debug = config('app.debug');

        if ($e instanceof ValidationException) {
            return $this->buildResponse(422, 'Validation Error', $e->getMessage(), 'validation_error', $e->errors(), $debug, $e);
        }

        if ($e instanceof AuthenticationException) {
            return $this->buildResponse(401, 'Unauthenticated', 'Authentication is required.', 'unauthenticated', [], $debug, $e);
        }

        if ($e instanceof AuthorizationException) {
            return $this->buildResponse(403, 'Forbidden', 'You are not authorized to perform this action.', 'forbidden', [], $debug, $e);
        }

        if ($e instanceof TokenMismatchException) {
            return $this->buildResponse(419, 'Session Expired', 'Your session has expired. Please refresh the page.', 'session_expired', [], $debug, $e);
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->buildResponse(404, 'Not Found', 'The requested resource was not found.', 'not_found', [], $debug, $e);
        }

        if ($e instanceof AccessDeniedHttpException) {
            return $this->buildResponse(403, 'Forbidden', $e->getMessage() ?: 'You are not authorized to perform this action.', 'forbidden', [], $debug, $e);
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->buildResponse(429, 'Too Many Requests', 'Too many attempts. Please try again later.', 'too_many_requests', [], $debug, $e);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->buildResponse(405, 'Method Not Allowed', 'The HTTP method is not allowed for this endpoint.', 'method_not_allowed', [], $debug, $e);
        }

        if ($e instanceof ProblemDetailsException) {
            return $this->buildResponse(
                $e->getStatusCode(),
                class_basename($e),
                $e->getMessage() ?: $e->getMessage(),
                $e->getErrorCode(),
                $e->getErrorBag(),
                $debug,
                $e,
            );
        }

        if ($debug) {
            return $this->buildResponse(500, 'Server Error', $e->getMessage(), 'internal_error', [], true, $e);
        }

        return $this->buildResponse(500, 'Server Error', 'An unexpected error occurred.', 'internal_error', [], false, $e);
    }

    private function buildResponse(
        int $status,
        string $title,
        string $detail,
        string $code,
        array $errors,
        bool $debug,
        ?\Throwable $e = null,
    ): JsonResponse {
        $body = [
            'type' => "https://careerpilot.example/problems/$code",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => url()->current(),
            'code' => $code,
            'errors' => (object) $errors,
            'request_id' => RequestIdContext::get() ?? request()->header('X-Request-ID', (string) Str::uuid()),
        ];

        if ($debug && $e !== null) {
            $body['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        return response()->json($body, $status);
    }
}

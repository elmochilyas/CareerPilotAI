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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProblemDetailsRenderer
{
    public function render(Request $request, \Throwable $e): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->buildResponse(422, 'Validation Error', $e->getMessage(), 'validation_error', $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return $this->buildResponse(401, 'Unauthenticated', 'Authentication is required.', 'unauthenticated');
        }

        if ($e instanceof AuthorizationException) {
            return $this->buildResponse(403, 'Forbidden', 'You are not authorized to perform this action.', 'forbidden');
        }

        if ($e instanceof TokenMismatchException) {
            return $this->buildResponse(419, 'Session Expired', 'Your session has expired. Please refresh the page.', 'session_expired');
        }

        if ($e instanceof HttpException && $e->getStatusCode() === 419) {
            return $this->buildResponse(419, 'Session Expired', 'Your session has expired. Please refresh the page.', 'session_expired');
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->buildResponse(404, 'Not Found', 'The requested resource was not found.', 'not_found');
        }

        if ($e instanceof AccessDeniedHttpException) {
            return $this->buildResponse(403, 'Forbidden', $e->getMessage() ?: 'You are not authorized to perform this action.', 'forbidden');
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->buildResponse(429, 'Too Many Requests', 'Too many attempts. Please try again later.', 'too_many_requests');
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->buildResponse(405, 'Method Not Allowed', 'The HTTP method is not allowed for this endpoint.', 'method_not_allowed');
        }

        if ($e instanceof ProblemDetailsException) {
            return $this->buildResponse(
                $e->getStatusCode(),
                class_basename($e),
                $e->getMessage() ?: class_basename($e),
                $e->getErrorCode(),
                $e->getErrorBag(),
            );
        }

        return $this->buildResponse(500, 'Server Error', 'An unexpected error occurred.', 'internal_error');
    }

    private function buildResponse(
        int $status,
        string $title,
        string $detail,
        string $code,
        array $errors = [],
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

        return response()->json($body, $status);
    }
}

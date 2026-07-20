<?php

use App\Http\Middleware\AddRequestId;
use App\Support\ProblemDetails\ProblemDetailsException;
use App\Support\ProblemDetails\ProblemDetailsRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AddRequestId::class);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $renderer = new ProblemDetailsRenderer;

        $exceptions->render(function (ValidationException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (ProblemDetailsException $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($renderer) {
            return $renderer->render($request, $e);
        });
    })->create();

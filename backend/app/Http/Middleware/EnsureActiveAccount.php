<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\UserAccountStatus;
use App\Support\ProblemDetails\ProblemDetailsException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->account_status !== UserAccountStatus::Active) {
            throw new ProblemDetailsException(
                403,
                __('Your account is not active. Please contact support.'),
                'account_disabled',
            );
        }

        return $next($request);
    }
}

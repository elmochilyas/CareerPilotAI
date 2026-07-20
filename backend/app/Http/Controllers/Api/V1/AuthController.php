<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\LoginUserAction;
use App\Domain\Identity\Actions\LogoutUserAction;
use App\Domain\Identity\Actions\RegisterUserAction;
use App\Domain\Identity\Actions\ResendVerificationNotificationAction;
use App\Domain\Identity\Actions\ResetPasswordAction;
use App\Domain\Identity\Actions\SendPasswordResetLinkAction;
use App\Domain\Identity\Actions\ShowAuthenticatedUserAction;
use App\Domain\Identity\Actions\VerifyEmailAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUserAction $action,
    ): JsonResponse {
        $user = $action->execute(
            $request->input('full_name'),
            $request->input('email'),
            $request->input('password'),
        );

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    public function login(
        LoginRequest $request,
        LoginUserAction $action,
    ): JsonResponse {
        $user = $action->execute(
            $request->input('email'),
            $request->input('password'),
        );

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function logout(LogoutUserAction $action): Response
    {
        $action->execute();

        return response()->noContent();
    }

    public function me(ShowAuthenticatedUserAction $action): JsonResponse
    {
        $user = $action->execute();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        SendPasswordResetLinkAction $action,
    ): JsonResponse {
        $status = $action->execute($request->input('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 202);
        }

        return response()->json(['message' => __($status)], 202);
    }

    public function resetPassword(
        ResetPasswordRequest $request,
        ResetPasswordAction $action,
    ): JsonResponse {
        $status = $action->execute(
            $request->input('email'),
            $request->input('token'),
            $request->input('password'),
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], 200);
        }

        return response()->json(['message' => __($status)], 422);
    }

    public function verifyEmail(
        Request $request,
        VerifyEmailAction $action,
    ): JsonResponse {
        $user = User::findOrFail($request->route('id'));

        $action->execute($user, $request->route('hash'));

        return response()->json(['message' => 'Email verified.'], 200);
    }

    public function resendVerification(
        Request $request,
        ResendVerificationNotificationAction $action,
    ): JsonResponse {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException;
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        $action->execute($user);

        return response()->json(['message' => 'Verification email sent.'], 202);
    }
}

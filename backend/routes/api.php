<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show']);

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');

    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::delete('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:3,1');
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/items', [ProfileController::class, 'storeItem']);
        Route::patch('/profile/items/reorder', [ProfileController::class, 'reorderItems']);
        Route::patch('/profile/items/{profileItem}', [ProfileController::class, 'updateItem']);
        Route::delete('/profile/items/{profileItem}', [ProfileController::class, 'destroyItem']);
    });
});

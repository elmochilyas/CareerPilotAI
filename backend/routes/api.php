<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CandidateSkillController;
use App\Http\Controllers\Api\V1\CvIngestion\CvDocumentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SkillController;
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

        Route::get('/skills', [SkillController::class, 'index']);
        Route::get('/skills/{skill}', [SkillController::class, 'show']);

        Route::get('/candidate/skills', [CandidateSkillController::class, 'index']);
        Route::post('/candidate/skills', [CandidateSkillController::class, 'store']);
        Route::get('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'show']);
        Route::patch('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'update']);
        Route::post('/candidate/skills/{candidateSkill}/archive', [CandidateSkillController::class, 'archive']);
        Route::post('/candidate/skills/{candidateSkill}/restore', [CandidateSkillController::class, 'restore']);
        Route::delete('/candidate/skills/{candidateSkill}', [CandidateSkillController::class, 'destroy']);
        Route::post('/candidate/skills/{candidateSkill}/evidence', [CandidateSkillController::class, 'storeEvidence']);
        Route::patch('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'updateEvidence']);
        Route::delete('/candidate/skills/{candidateSkill}/evidence/{evidenceKey}', [CandidateSkillController::class, 'destroyEvidence']);

        Route::prefix('cv')->group(function (): void {
            Route::get('/', [CvDocumentController::class, 'index']);
            Route::post('/', [CvDocumentController::class, 'store'])
                ->middleware('throttle:10,1');
            Route::get('/{cvDocument}', [CvDocumentController::class, 'show']);
            Route::get('/{cvDocument}/download', [CvDocumentController::class, 'download'])
                ->middleware('throttle:60,1');
            Route::post('/{cvDocument}/retry', [CvDocumentController::class, 'retry']);
            Route::delete('/{cvDocument}', [CvDocumentController::class, 'destroy']);
            Route::get('/{cvDocument}/suggestions', [CvDocumentController::class, 'suggestions']);
            Route::patch('/{cvDocument}/suggestions/{cvSuggestion}', [CvDocumentController::class, 'updateSuggestion']);
            Route::post('/{cvDocument}/suggestions/batch', [CvDocumentController::class, 'batchUpdateSuggestions']);
            Route::get('/{cvDocument}/import-preview', [CvDocumentController::class, 'importPreview']);
            Route::post('/{cvDocument}/apply', [CvDocumentController::class, 'apply'])
                ->middleware('throttle:5,1,cv_apply');
            Route::get('/{cvDocument}/import-result', [CvDocumentController::class, 'importResult']);
        });
    });
});

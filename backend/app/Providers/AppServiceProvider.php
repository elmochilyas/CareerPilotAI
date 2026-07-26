<?php

namespace App\Providers;

use App\Domain\CvIngestion\Policies\CvDocumentPolicy;
use App\Domain\CvIngestion\Policies\CvImportBatchPolicy;
use App\Domain\CvIngestion\Policies\CvSuggestionPolicy;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;
use App\Domain\CvIngestion\Services\OpenAiCvAnalyzer;
use App\Domain\Profile\Policies\ProfileItemPolicy;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use App\Models\ProfileItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CvAnalyzer::class, OpenAiCvAnalyzer::class);
    }

    public function boot(): void
    {
        Gate::policy(ProfileItem::class, ProfileItemPolicy::class);
        Gate::policy(CvDocument::class, CvDocumentPolicy::class);
        Gate::policy(CvSuggestion::class, CvSuggestionPolicy::class);
        Gate::policy(CvImportBatch::class, CvImportBatchPolicy::class);

        Route::bind('cvDocument', function (string $value): CvDocument {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return CvDocument::where('user_id', $userId)->findOrFail($value);
        });

        Route::bind('cvSuggestion', function (string $value): CvSuggestion {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return CvSuggestion::whereHas('cvDocument', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });
    }
}

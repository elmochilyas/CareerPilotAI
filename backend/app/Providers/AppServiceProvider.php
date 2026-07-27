<?php

namespace App\Providers;

use App\Domain\CvIngestion\Policies\CvDocumentPolicy;
use App\Domain\CvIngestion\Policies\CvImportBatchPolicy;
use App\Domain\CvIngestion\Policies\CvSuggestionPolicy;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;
use App\Domain\CvIngestion\Services\OpenAiCvAnalyzer;
use App\Domain\Opportunities\Policies\JobOpportunityIngestionPolicy;
use App\Domain\Opportunities\Policies\JobOpportunityPolicy;
use App\Domain\Opportunities\Policies\JobOpportunitySuggestionPolicy;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Domain\Opportunities\Services\OpenAiJobAnalyzer;
use App\Domain\Profile\Policies\ProfileItemPolicy;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
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
        $this->app->bind(JobAnalyzer::class, OpenAiJobAnalyzer::class);
    }

    public function boot(): void
    {
        Gate::policy(ProfileItem::class, ProfileItemPolicy::class);
        Gate::policy(CvDocument::class, CvDocumentPolicy::class);
        Gate::policy(CvSuggestion::class, CvSuggestionPolicy::class);
        Gate::policy(CvImportBatch::class, CvImportBatchPolicy::class);
        Gate::policy(JobOpportunityIngestion::class, JobOpportunityIngestionPolicy::class);
        Gate::policy(JobOpportunitySuggestion::class, JobOpportunitySuggestionPolicy::class);
        Gate::policy(JobOpportunity::class, JobOpportunityPolicy::class);

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

        Route::bind('ingestion', function (string $value): JobOpportunityIngestion {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return JobOpportunityIngestion::where('user_id', $userId)->findOrFail($value);
        });

        Route::bind('suggestion', function (string $value): JobOpportunitySuggestion {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return JobOpportunitySuggestion::whereHas('ingestion', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });

        Route::bind('opportunity', function (string $value): JobOpportunity {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return JobOpportunity::whereHas('candidateProfile', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });
    }
}

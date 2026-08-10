<?php

namespace App\Providers;

use App\Domain\Clarification\Events\ProposalAccepted;
use App\Domain\Clarification\Listeners\WriteClarificationAuditEventListener;
use App\Domain\Clarification\Policies\ClarificationAnswerPolicy;
use App\Domain\Clarification\Policies\ClarificationProposalPolicy;
use App\Domain\Clarification\Policies\ClarificationQuestionPolicy;
use App\Domain\Clarification\Services\Contracts\ClarificationAssistant;
use App\Domain\Clarification\Services\OpenAiClarificationAssistant;
use App\Domain\CvIngestion\Policies\CvDocumentPolicy;
use App\Domain\CvIngestion\Policies\CvImportBatchPolicy;
use App\Domain\CvIngestion\Policies\CvSuggestionPolicy;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;
use App\Domain\CvIngestion\Services\OpenAiCvAnalyzer;
use App\Domain\Matching\Policies\MatchAnalysisPolicy;
use App\Domain\Matching\Services\Contracts\RequirementClassifier;
use App\Domain\Matching\Services\OpenAiRequirementClassifier;
use App\Domain\Opportunities\Policies\JobOpportunityIngestionPolicy;
use App\Domain\Opportunities\Policies\JobOpportunityPolicy;
use App\Domain\Opportunities\Policies\JobOpportunitySuggestionPolicy;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;
use App\Domain\Opportunities\Services\OpenAiJobAnalyzer;
use App\Domain\Profile\Policies\ProfileItemPolicy;
use App\Domain\Resumes\Policies\ResumePolicy;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\CvDocument;
use App\Models\CvImportBatch;
use App\Models\CvSuggestion;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\MatchAnalysis;
use App\Models\ProfileItem;
use App\Models\Resume;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CvAnalyzer::class, OpenAiCvAnalyzer::class);
        $this->app->bind(JobAnalyzer::class, OpenAiJobAnalyzer::class);
        $this->app->bind(RequirementClassifier::class, OpenAiRequirementClassifier::class);
        $this->app->bind(ClarificationAssistant::class, OpenAiClarificationAssistant::class);
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
        Gate::policy(MatchAnalysis::class, MatchAnalysisPolicy::class);
        Gate::policy(ClarificationQuestion::class, ClarificationQuestionPolicy::class);
        Gate::policy(ClarificationAnswer::class, ClarificationAnswerPolicy::class);
        Gate::policy(ClarificationProposal::class, ClarificationProposalPolicy::class);
        Gate::policy(Resume::class, ResumePolicy::class);

        Event::listen(ProposalAccepted::class, WriteClarificationAuditEventListener::class);

        RateLimiter::for('opportunity-ingestion-create', fn (Request $request): Limit => Limit::perHour(10)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-ingestion-retry', fn (Request $request): Limit => Limit::perHour(5)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-ingestion-reanalyze', fn (Request $request): Limit => Limit::perHour(5)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-ingestion-delete', fn (Request $request): Limit => Limit::perHour(10)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-suggestion-update', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-suggestion-batch', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-preview', fn (Request $request): Limit => Limit::perHour(10)
            ->by($this->rateLimitKey($request)));
        RateLimiter::for('opportunity-confirm', fn (Request $request): Limit => Limit::perHour(30)
            ->by($this->rateLimitKey($request)));

        RateLimiter::for('matching-create', fn (Request $request): Limit => $this->matchingLimit($request, 'create_rate_limit'));
        RateLimiter::for('matching-recalculate', fn (Request $request): Limit => $this->matchingLimit($request, 'recalculate_rate_limit'));
        RateLimiter::for('clarification-write', fn (Request $request): Limit => $this->clarificationWriteLimit($request));
        RateLimiter::for('clarification-generate', fn (Request $request): Limit => $this->clarificationGenerateLimit($request));

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

        Route::bind('resume', function (string $value): Resume {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return Resume::whereHas('candidateProfile', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });

        Route::bind('matchAnalysis', function (string $value): MatchAnalysis {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return MatchAnalysis::whereHas('candidateProfile', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });

        Route::bind('clarificationQuestion', function (string $value): ClarificationQuestion {
            $userId = request()->user()?->id;

            if ($userId === null) {
                throw new ModelNotFoundException;
            }

            return ClarificationQuestion::whereHas('matchAnalysis.candidateProfile', fn ($q) => $q->where('user_id', $userId))
                ->findOrFail($value);
        });
    }

    private function matchingLimit(Request $request, string $configKey): Limit
    {
        [$maxAttempts, $decayMinutes] = explode(',', (string) config("matching.$configKey", '10,1'));

        return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
            ->by($this->rateLimitKey($request));
    }

    private function clarificationWriteLimit(Request $request): Limit
    {
        [$maxAttempts, $decayMinutes] = explode(',', (string) config('clarification.write_rate_limit', '60,1'));

        return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
            ->by($this->rateLimitKey($request));
    }

    private function clarificationGenerateLimit(Request $request): Limit
    {
        [$maxAttempts, $decayMinutes] = explode(',', (string) config('clarification.generate_rate_limit', '10,1'));

        return Limit::perMinutes((int) $decayMinutes, (int) $maxAttempts)
            ->by($this->rateLimitKey($request));
    }

    private function rateLimitKey(Request $request): string
    {
        if ($request->user() !== null) {
            return (string) $request->user()->id;
        }

        return $request->ip();
    }
}

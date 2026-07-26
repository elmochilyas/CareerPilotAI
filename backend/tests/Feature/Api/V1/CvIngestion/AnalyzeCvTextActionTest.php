<?php

use App\Domain\CvIngestion\Actions\AnalyzeCvTextAction;
use App\Domain\CvIngestion\Data\CvAnalysisResult;
use App\Domain\CvIngestion\Enums\CvDocumentStatus;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer as CvAnalyzerContract;
use App\Domain\CvIngestion\Services\CvAnalysisSchemaValidator;
use App\Models\CvDocument;
use App\Models\CvProcessingRun;
use App\Models\CvSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCvAnalyzer;

uses(RefreshDatabase::class)->group('api', 'cv-ingestion', 'analysis');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->analyzer = new FakeCvAnalyzer;
    $this->validator = new CvAnalysisSchemaValidator;
    $this->action = new AnalyzeCvTextAction($this->analyzer, $this->validator);
});

it('creates suggestions from valid AI analysis output with entity grouping', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
        'status' => 'processing',
    ]);

    $this->action->execute($document, $run, 'Extracted text from CV');

    $document->refresh();
    $run->refresh();

    expect($document->status)->toBe(CvDocumentStatus::ReadyForReview);
    expect($run->status->value)->toBe('completed');
    expect($run->ai_provider)->toBe('fake');

    $suggestions = CvSuggestion::where('cv_document_id', $document->id)->get();

    // 5 basic info + 1 headline + 1 summary + 2 social links + 1 exp + 3 projects + 3 edu + 4 languages + 10 skills = 30
    expect($suggestions)->toHaveCount(30);

    $types = $suggestions->pluck('type')->map->value->toArray();
    expect($types)->toContain('basic_information');
    expect($types)->toContain('headline');
    expect($types)->toContain('summary');
    expect($types)->toContain('social_link');
    expect($types)->toContain('experience');
    expect($types)->toContain('project');
    expect($types)->toContain('education');
    expect($types)->toContain('language');
    expect($types)->toContain('skill');

    // Verify entity grouping: one complete experience
    $experienceSuggestions = $suggestions->filter(fn ($s) => $s->type->value === 'experience');
    expect($experienceSuggestions)->toHaveCount(1);
    $expValue = $experienceSuggestions->first()->suggested_value;
    expect($expValue['title'])->toBe('Mobile Developer Intern');
    expect($expValue['organization'])->toBe('3LM Solutions');
    expect($expValue['technologies'])->toContain('React Native');

    // Verify three complete projects
    $projectSuggestions = $suggestions->filter(fn ($s) => $s->type->value === 'project');
    expect($projectSuggestions)->toHaveCount(3);
    $projectNames = $projectSuggestions->pluck('suggested_value.name')->toArray();
    expect($projectNames)->toContain('LaraSkills');
    expect($projectNames)->toContain('TalentMatch');
    expect($projectNames)->toContain('Noter-Solution');

    // Verify all four languages
    $languageSuggestions = $suggestions->filter(fn ($s) => $s->type->value === 'language');
    expect($languageSuggestions)->toHaveCount(4);
    $languageNames = $languageSuggestions->pluck('suggested_value.language')->toArray();
    expect($languageNames)->toContain('Arabic');
    expect($languageNames)->toContain('French');
    expect($languageNames)->toContain('English');
    expect($languageNames)->toContain('German');
});

it('rejects invalid AI analysis and sets document to failed', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
        'status' => 'processing',
    ]);

    $faultyAnalyzer = new class implements CvAnalyzerContract
    {
        public function analyze(string $extractedText): CvAnalysisResult
        {
            return new CvAnalysisResult(
                basicInformation: [],
                headline: null,
                professionalSummary: null,
                professionalLinks: [],
                experiences: [],
                projects: [],
                education: [],
                certifications: [],
                languages: [],
                skills: [],
                warnings: [],
                provider: 'fake',
                model: 'fake-faulty',
                promptVersion: '1.1.0',
                latencyMs: 50,
                tokensPrompt: 100,
                tokensCompletion: 50,
                responseId: 'faulty_'.uniqid(),
                schemaVersion: '1.1.0',
            );
        }
    };

    $action = new AnalyzeCvTextAction($faultyAnalyzer, $this->validator);

    expect(fn () => $action->execute($document, $run, 'Extracted text'))
        ->toThrow(RuntimeException::class, 'AI analysis returned no valid suggestions');

    $document->refresh();
    $run->refresh();

    expect($document->status)->toBe(CvDocumentStatus::Failed);
    expect($document->failure_code)->toBe('ai_no_suggestions');
    expect($run->status->value)->toBe('failed');
    expect($run->failure_code)->toBe('ai_no_suggestions');
});

it('skips duplicate suggestion types', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
        'status' => 'processing',
    ]);

    CvSuggestion::factory()->create([
        'cv_document_id' => $document->id,
        'cv_processing_run_id' => $run->id,
        'type' => 'headline',
        'field_name' => 'headline',
    ]);

    $this->action->execute($document, $run, 'Extracted text');

    $suggestions = CvSuggestion::where('cv_document_id', $document->id)->get();
    // Still creates all but skips the existing headline → still 30 total (1 existing + 29 new)
    expect($suggestions)->toHaveCount(30);
});

it('handles empty result from analyzer', function () {
    $document = CvDocument::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $run = CvProcessingRun::factory()->create([
        'cv_document_id' => $document->id,
        'status' => 'processing',
    ]);

    $emptyAnalyzer = new class implements CvAnalyzerContract
    {
        public function analyze(string $extractedText): CvAnalysisResult
        {
            return new CvAnalysisResult(
                basicInformation: [],
                headline: null,
                professionalSummary: null,
                professionalLinks: [],
                experiences: [],
                projects: [],
                education: [],
                certifications: [],
                languages: [],
                skills: [],
                warnings: [],
                provider: 'fake',
                model: 'fake-empty',
                promptVersion: '1.1.0',
                latencyMs: 50,
                tokensPrompt: 100,
                tokensCompletion: 50,
                responseId: 'empty_'.uniqid(),
                schemaVersion: '1.1.0',
            );
        }
    };

    $action = new AnalyzeCvTextAction($emptyAnalyzer, $this->validator);

    expect(fn () => $action->execute($document, $run, 'Extracted text'))
        ->toThrow(RuntimeException::class, 'AI analysis returned no valid suggestions');

    $document->refresh();
    expect($document->status)->toBe(CvDocumentStatus::Failed);
});

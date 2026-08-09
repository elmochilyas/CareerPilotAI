<?php

use App\Domain\Matching\Enums\MatchAnalysisStatus;
use App\Jobs\ObserveClarificationStalenessJob;
use App\Models\CandidateProfile;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationQuestion;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class)->group('clarifications', 'integration');

const CLARIFICATION_TABLES = [
    'clarification_questions',
    'clarification_answers',
    'clarification_proposals',
    'clarification_audit_events',
];

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create();
});

it('enforces a unique answer per question and rejects foreign key violations', function () {
    $question = ClarificationQuestion::factory()->create();

    ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]);

    expect(fn () => ClarificationAnswer::factory()->create([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
    ]))->toThrow(QueryException::class);

    $unique = collect(Schema::getIndexes('clarification_answers'))
        ->first(fn (array $index): bool => in_array('question_id', $index['columns'], true));

    expect($unique)->not->toBeNull()
        ->and($unique['unique'])->toBeTrue();

    $otherQuestion = ClarificationQuestion::factory()->create();

    expect(fn () => ClarificationAnswer::factory()->create([
        'user_id' => 999999,
        'question_id' => $otherQuestion->id,
    ]))->toThrow(QueryException::class);
});

it('enforces the MySQL answer_type and status check constraints', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-specific CHECK constraints are only enforced by the MySQL driver.');
    }

    $checks = DB::select('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?', [
        DB::getDatabaseName(),
        'clarification_answers',
        'CHECK',
    ]);
    $names = collect($checks)->pluck('CONSTRAINT_NAME')->all();

    expect($names)->toContain('clarification_answers_answer_type_check')
        ->and($names)->toContain('clarification_answers_status_check');

    $question = ClarificationQuestion::factory()->create();

    expect(fn () => DB::table('clarification_answers')->insert([
        'user_id' => $this->user->id,
        'question_id' => $question->id,
        'answer_type' => 'yes',
        'value' => 'yes',
        'acknowledged_no_evidence' => false,
        'status' => 'not_a_real_status',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('processes the stale-marking observation through the database queue', function () {
    config()->set('queue.default', 'database');

    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    ObserveClarificationStalenessJob::dispatch($analysis->id, 'req_integration');

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('queue'))->toBe('clarification');

    $this->artisan('queue:work', [
        '--once' => true,
        '--queue' => 'clarification',
        '--tries' => 1,
    ])->assertExitCode(0);

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(MatchAnalysis::find($analysis->id)->status)->toBe(MatchAnalysisStatus::Completed);
});

it('supports rollback and forward-fix for the clarification migrations', function () {
    foreach (CLARIFICATION_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach (CLARIFICATION_TABLES as $table) {
        Schema::dropIfExists($table);
    }

    $clarificationMigrations = [
        '2026_08_07_001804_create_clarification_questions_table',
        '2026_08_07_001805_create_clarification_answers_table',
        '2026_08_07_001806_create_clarification_proposals_table',
        '2026_08_07_001807_create_clarification_audit_events_table',
    ];
    DB::table('migrations')->whereIn('migration', $clarificationMigrations)->delete();

    foreach (CLARIFICATION_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $this->artisan('migrate')->assertExitCode(0);

    foreach (CLARIFICATION_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

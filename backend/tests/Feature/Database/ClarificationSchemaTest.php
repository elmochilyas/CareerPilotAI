<?php

use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionType;
use App\Domain\Clarification\Enums\ClarificationTargetType;
use App\Models\CandidateProfile;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationAuditEvent;
use App\Models\ClarificationProposal;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('database', 'clarifications', 'schema');

it('creates a clarification question with defaults and relations', function () {
    $analysis = MatchAnalysis::factory()->create();
    $finding = MatchFinding::factory()->create(['match_analysis_id' => $analysis->id]);

    $question = ClarificationQuestion::factory()->create([
        'match_analysis_id' => $analysis->id,
        'match_finding_id' => $finding->id,
        'question_no' => 1,
    ]);

    expect($question->status)->toBe(ClarificationQuestionStatus::Pending)
        ->and($question->question_type)->toBe(ClarificationQuestionType::YesNo)
        ->and($question->matchAnalysis->is($analysis))->toBeTrue()
        ->and($question->matchFinding->is($finding))->toBeTrue();
});

it('cascades questions when the owning analysis is deleted', function () {
    $analysis = MatchAnalysis::factory()->create();
    $question = ClarificationQuestion::factory()->create(['match_analysis_id' => $analysis->id]);

    $analysis->delete();

    expect(ClarificationQuestion::whereKey($question->id)->exists())->toBeFalse();
});

it('enforces one answer per question', function () {
    $question = ClarificationQuestion::factory()->create();

    ClarificationAnswer::factory()->create(['question_id' => $question->id]);

    expect(fn () => ClarificationAnswer::factory()->create(['question_id' => $question->id]))
        ->toThrow(QueryException::class);
});

it('cascades answers when the question is deleted', function () {
    $question = ClarificationQuestion::factory()->create();
    $answer = ClarificationAnswer::factory()->create(['question_id' => $question->id]);

    $question->delete();

    expect(ClarificationAnswer::whereKey($answer->id)->exists())->toBeFalse();
});

it('cascades proposals when the answer is deleted', function () {
    $proposal = ClarificationProposal::factory()->create();

    $proposal->answer->delete();

    expect(ClarificationProposal::whereKey($proposal->id)->exists())->toBeFalse();
});

it('enforces one proposal per answer', function () {
    $answer = ClarificationAnswer::factory()->create();

    ClarificationProposal::factory()->create(['answer_id' => $answer->id]);

    expect(fn () => ClarificationProposal::factory()->create(['answer_id' => $answer->id]))
        ->toThrow(QueryException::class);
});

it('stores the default proposal status as proposed', function () {
    $proposal = ClarificationProposal::factory()->create([
        'target_type' => ClarificationTargetType::CandidateSkill,
    ]);

    expect(DB::table('clarification_proposals')->where('id', $proposal->id)->value('status'))->toBe('proposed');
});

it('requires a matching analysis for a question', function () {
    expect(fn () => ClarificationQuestion::factory()->create(['match_analysis_id' => 999999]))
        ->toThrow(QueryException::class);
});

it('requires an owning user and question for an answer', function () {
    $user = User::factory()->create();
    $question = ClarificationQuestion::factory()->create();

    expect(fn () => ClarificationAnswer::factory()->create(['user_id' => 999999, 'question_id' => $question->id]))
        ->toThrow(QueryException::class)
        ->and(fn () => ClarificationAnswer::factory()->create(['user_id' => $user->id, 'question_id' => 999999]))
        ->toThrow(QueryException::class);
});

it('stores enum strings in the database', function () {
    $question = ClarificationQuestion::factory()->yesNoWithDetails()->create();
    $answer = ClarificationAnswer::factory()->withAck()->create(['question_id' => $question->id]);

    expect(DB::table('clarification_questions')->where('id', $question->id)->value('question_type'))->toBe('yes_no_with_details')
        ->and(DB::table('clarification_answers')->where('id', $answer->id)->value('answer_type'))->toBe('no_with_ack')
        ->and(DB::table('clarification_answers')->where('id', $answer->id)->value('acknowledged_no_evidence'))->toBe(1);
});

it('creates an audit event with defaults and relations', function () {
    $user = User::factory()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
    $analysis = MatchAnalysis::factory()->create(['candidate_profile_id' => $profile->id]);
    $answer = ClarificationAnswer::factory()->create(['user_id' => $user->id]);
    $proposal = ClarificationProposal::factory()->create(['answer_id' => $answer->id]);

    $audit = ClarificationAuditEvent::factory()->create([
        'user_id' => $user->id,
        'match_analysis_id' => $analysis->id,
        'proposal_id' => $proposal->id,
        'answer_id' => $answer->id,
    ]);

    expect($audit->user->is($user))->toBeTrue()
        ->and($audit->matchAnalysis->is($analysis))->toBeTrue()
        ->and($audit->proposal->is($proposal))->toBeTrue()
        ->and($audit->answer->is($answer))->toBeTrue()
        ->and($audit->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($audit->after_value)->toBe(['state' => 'verified']);
});

it('cascades audit events when the owning user is hard deleted', function () {
    $user = User::factory()->create();
    $audit = ClarificationAuditEvent::factory()->create(['user_id' => $user->id]);

    $user->forceDelete();

    expect(ClarificationAuditEvent::whereKey($audit->id)->exists())->toBeFalse();
});

it('keeps audit events when the proposal or analysis is deleted', function () {
    $proposal = ClarificationProposal::factory()->create();
    $audit = ClarificationAuditEvent::factory()->forProposal($proposal)->create();

    $proposal->delete();

    expect(ClarificationAuditEvent::whereKey($audit->id)->exists())->toBeTrue()
        ->and($audit->refresh()->proposal_id)->toBeNull()
        ->and($audit->target_type)->toBe(ClarificationTargetType::CandidateSkill)
        ->and($audit->after_value)->toBe(['state' => 'verified']);
});

it('requires an owning user for an audit event', function () {
    expect(fn () => ClarificationAuditEvent::factory()->create(['user_id' => 999999]))
        ->toThrow(QueryException::class);
});

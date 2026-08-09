<?php

use App\Domain\Clarification\Actions\SkipQuestionAction;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\ClarificationAnswer;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('clarifications', 'actions');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->analysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $this->question = ClarificationQuestion::factory()->create([
        'match_analysis_id' => $this->analysis->id,
        'question_no' => 1,
    ]);
    $this->action = app(SkipQuestionAction::class);
});

it('skips an open question without creating an answer or proposal', function () {
    $this->action->execute($this->question);

    expect($this->question->refresh()->status)->toBe(ClarificationQuestionStatus::Skipped)
        ->and(ClarificationAnswer::where('question_id', $this->question->id)->count())->toBe(0)
        ->and($this->profile->refresh()->candidateSkills()->count())->toBe(0);
});

it('is idempotent for an already-skipped question', function () {
    $this->question->update(['status' => ClarificationQuestionStatus::Skipped]);

    $this->action->execute($this->question);

    expect($this->question->refresh()->status)->toBe(ClarificationQuestionStatus::Skipped);
});

it('does not skip a question that already has an answer', function () {
    ClarificationAnswer::factory()->create([
        'question_id' => $this->question->id,
        'user_id' => $this->user->id,
    ]);
    $this->question->update(['status' => ClarificationQuestionStatus::Answered]);

    $this->action->execute($this->question);
})->throws(ConflictException::class, 'This question already has an answer.');

it('reports skippability only for open questions without answers', function () {
    expect($this->action->isSkippable($this->question))->toBeTrue();

    $this->action->execute($this->question);

    expect($this->action->isSkippable($this->question->fresh()))->toBeFalse();
});

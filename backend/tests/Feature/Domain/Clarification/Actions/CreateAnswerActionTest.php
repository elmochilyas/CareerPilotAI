<?php

use App\Domain\Clarification\Actions\CreateAnswerAction;
use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationAnswerType;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Exceptions\Api\ConflictException;
use App\Exceptions\Api\UnprocessableEntityException;
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
    $this->action = app(CreateAnswerAction::class);
});

it('creates a pending answer and marks the question answered', function () {
    $answer = $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );

    expect($answer->user_id)->toBe($this->user->id)
        ->and($answer->question_id)->toBe($this->question->id)
        ->and($answer->answer_type)->toBe(ClarificationAnswerType::Yes)
        ->and($answer->value)->toBe('yes')
        ->and($answer->acknowledged_no_evidence)->toBeFalse()
        ->and($answer->status)->toBe(ClarificationAnswerStatus::Pending)
        ->and($answer->question->is($this->question))->toBeTrue();

    expect($this->question->refresh()->status)->toBe(ClarificationQuestionStatus::Answered);
});

it('stores the no-evidence acknowledgement flag', function () {
    $answer = $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::NoWithAck,
        'no',
        acknowledgedNoEvidence: true,
    );

    expect($answer->acknowledged_no_evidence)->toBeTrue()
        ->and($answer->answer_type)->toBe(ClarificationAnswerType::NoWithAck);
});

it('returns the existing pending answer on a duplicate submit', function () {
    $first = $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );

    $second = $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );

    expect($second->id)->toBe($first->id);
    expect(ClarificationAnswer::where('question_id', $this->question->id)->count())->toBe(1);
});

it('rejects a duplicate submit when the existing answer is not pending', function () {
    $existing = ClarificationAnswer::factory()->create([
        'question_id' => $this->question->id,
        'user_id' => $this->user->id,
        'status' => ClarificationAnswerStatus::Accepted,
    ]);
    $this->question->update(['status' => ClarificationQuestionStatus::Answered]);

    $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );
})->throws(ConflictException::class, 'This question already has an answer.');

it('rejects answering an expired question', function () {
    $this->question->update(['status' => ClarificationQuestionStatus::Expired]);

    $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );
})->throws(UnprocessableEntityException::class, 'This clarification session has expired. Start a fresh session.');

it('rejects answering a skipped question', function () {
    $this->question->update(['status' => ClarificationQuestionStatus::Skipped]);

    $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );
})->throws(ConflictException::class, 'This question is no longer open for answers.');

it('never mutates profile data while an answer is pending', function () {
    $this->action->execute(
        $this->user,
        $this->question,
        ClarificationAnswerType::Yes,
        'yes',
    );

    expect($this->profile->refresh()->candidateSkills()->count())->toBe(0);
});

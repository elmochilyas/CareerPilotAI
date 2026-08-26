<?php

use App\Domain\Clarification\Enums\ClarificationAnswerStatus;
use App\Domain\Clarification\Enums\ClarificationProposalStatus;
use App\Domain\Clarification\Enums\ClarificationQuestionStatus;
use App\Domain\Matching\Enums\MatchCategory;
use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Skills\Enums\SkillState;
use App\Jobs\ObserveClarificationStalenessJob;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\ClarificationAuditEvent;
use App\Models\ClarificationQuestion;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'clarifications');

if (! function_exists('clarifyApiFinding')) {
    function clarifyApiFinding(MatchAnalysis $analysis, ?CandidateSkill $candidateSkill = null): MatchFinding
    {
        return MatchFinding::factory()->partial()->create([
            'match_analysis_id' => $analysis->id,
            'category' => MatchCategory::RequiredSkills->value,
            'source_type' => RequirementSourceType::JobOpportunitySkill,
            'source_id' => 999999,
            'matched_candidate_skill_id' => $candidateSkill?->id,
        ]);
    }
}

if (! function_exists('clarifyApiQuestion')) {
    function clarifyApiQuestion(MatchAnalysis $analysis, MatchFinding $finding, int $questionNo = 1): ClarificationQuestion
    {
        return ClarificationQuestion::factory()->yesNoWithDetails()->create([
            'match_analysis_id' => $analysis->id,
            'match_finding_id' => $finding->id,
            'question_no' => $questionNo,
        ]);
    }
}

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->analysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $this->skill = Skill::factory()->create();
    $this->candidateSkill = CandidateSkill::factory()->claimed()->create([
        'candidate_profile_id' => $this->profile->id,
        'skill_id' => $this->skill->id,
    ]);
    $this->finding = clarifyApiFinding($this->analysis, $this->candidateSkill);
    $this->question = clarifyApiQuestion($this->analysis, $this->finding);
});

it('returns an empty clarification session with zero progress', function () {
    $emptyAnalysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$emptyAnalysis->id}/clarifications");

    $response->assertOk();
    $response->assertJsonPath('data.analysis_id', $emptyAnalysis->id);
    $response->assertJsonPath('data.questions', []);
    $response->assertJsonPath('data.progress', ['answered' => 0, 'total' => 0]);
});

it('generates questions for eligible findings through the API', function () {
    $fresh = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $finding = clarifyApiFinding($fresh);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$fresh->id}/clarifications");

    $response->assertStatus(201);
    $response->assertJsonPath('data.analysis_id', $fresh->id);
    $response->assertJsonPath('data.questions.0.status', ClarificationQuestionStatus::Pending->value);
    $response->assertJsonPath('data.questions.0.requirement.text', $finding->requirement_text);
    $response->assertJsonPath('data.generable_count', 0);
    $response->assertJsonStructure([
        'data' => [
            'analysis_id',
            'questions' => [[
                'id', 'question_no', 'question_type', 'prompt', 'detail', 'template_key',
                'options', 'unit', 'status',
                'requirement' => ['text', 'label'],
                'answer',
            ]],
            'progress' => ['answered', 'total'],
            'generable_count',
        ],
    ]);

    expect(ClarificationQuestion::where('match_analysis_id', $fresh->id)->count())->toBe(1);
});

it('regenerating the session never duplicates a question for a finding', function () {
    $fresh = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    $finding = clarifyApiFinding($fresh);

    $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$fresh->id}/clarifications")
        ->assertStatus(201);

    $second = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$fresh->id}/clarifications");

    $second->assertOk();
    $second->assertJsonCount(1, 'data.questions');
    expect(ClarificationQuestion::where('match_analysis_id', $fresh->id)->count())->toBe(1);
});

it('exposes how many questions a generate call would still create', function () {
    $fresh = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$fresh->id}/clarifications")
        ->assertOk()
        ->assertJsonPath('data.generable_count', 0);

    clarifyApiFinding($fresh);

    $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$fresh->id}/clarifications")
        ->assertOk()
        ->assertJsonPath('data.generable_count', 1);

    $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$fresh->id}/clarifications")
        ->assertStatus(201);

    $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$fresh->id}/clarifications")
        ->assertOk()
        ->assertJsonPath('data.generable_count', 0);
});

it('generating with no eligible findings returns an empty session', function () {
    $emptyAnalysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$emptyAnalysis->id}/clarifications");

    $response->assertOk();
    $response->assertJsonPath('data.questions', []);
    $response->assertJsonPath('data.progress', ['answered' => 0, 'total' => 0]);
});

it('returns 404 when generating a session for another candidate', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $otherAnalysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $otherProfile->id]);
    clarifyApiFinding($otherAnalysis);

    $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$otherAnalysis->id}/clarifications")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('returns 401 when generating a session unauthenticated', function () {
    $this->postJson("/api/v1/matches/{$this->analysis->id}/clarifications")->assertStatus(401);
});

it('rate limits session generation', function () {
    config()->set('clarification.generate_rate_limit', '2,1');

    $fresh = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $this->profile->id]);
    clarifyApiFinding($fresh);

    $this->actingAs($this->user)->postJson("/api/v1/matches/{$fresh->id}/clarifications")->assertStatus(201);
    $this->actingAs($this->user)->postJson("/api/v1/matches/{$fresh->id}/clarifications")->assertOk();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/matches/{$fresh->id}/clarifications");

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'too_many_requests');
});

it('lists the open questions with their type, evidence basis, and progress', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$this->analysis->id}/clarifications");

    $response->assertOk();
    $response->assertJsonPath('data.progress', ['answered' => 0, 'total' => 1]);
    $response->assertJsonPath('data.questions.0.id', $this->question->id);
    $response->assertJsonPath('data.questions.0.question_type', 'yes_no_with_details');
    $response->assertJsonPath('data.questions.0.status', ClarificationQuestionStatus::Pending->value);
    $response->assertJsonStructure([
        'data' => [
            'analysis_id',
            'questions' => [[
                'id', 'question_no', 'question_type', 'prompt', 'detail', 'template_key',
                'options', 'unit', 'status',
                'requirement' => ['text', 'label'],
                'answer',
            ]],
            'progress' => ['answered', 'total'],
            'generable_count',
        ],
    ]);
});

it('reports progress as answered over the full question total', function () {
    ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $this->finding->id,
        'question_no' => 2,
    ]);

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$this->analysis->id}/clarifications");

    $response->assertOk();
    $response->assertJsonPath('data.progress', ['answered' => 1, 'total' => 2]);
    $response->assertJsonCount(2, 'data.questions');
});

it('keeps the progress total fixed as questions are resolved', function () {
    $second = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $this->finding->id,
        'question_no' => 2,
    ]);

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/review", [
        'decision' => 'accept',
    ])->assertStatus(200);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/matches/{$this->analysis->id}/clarifications");

    $response->assertOk();
    $response->assertJsonCount(1, 'data.questions');
    $response->assertJsonPath('data.questions.0.id', $second->id);
    $response->assertJsonPath('data.progress', ['answered' => 1, 'total' => 2]);
});

it('answers a clarification question with 201 and the pending answer resource', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'yes',
            'value' => 'https://example.com/cert',
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.question_id', $this->question->id);
    $response->assertJsonPath('data.answer_type', 'yes');
    $response->assertJsonPath('data.value', 'https://example.com/cert');
    $response->assertJsonPath('data.acknowledged_no_evidence', false);
    $response->assertJsonPath('data.status', ClarificationAnswerStatus::Pending->value);
    $response->assertJsonPath('data.proposal', null);

    expect($this->question->refresh()->status)->toBe(ClarificationQuestionStatus::Answered);
});

it('stores the no-evidence acknowledgement for an answer without evidence', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'no_with_ack',
            'value' => 'no',
            'acknowledged_no_evidence' => true,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.acknowledged_no_evidence', true);
    $response->assertJsonPath('data.answer_type', 'no_with_ack');
});

it('accepts a plain no answer with an empty value', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'no',
            'value' => '',
            'acknowledged_no_evidence' => false,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.answer_type', 'no');
    $response->assertJsonPath('data.value', '');
});

it('accepts a no_with_ack answer without a value', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'no_with_ack',
            'acknowledged_no_evidence' => true,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.answer_type', 'no_with_ack');
    $response->assertJsonPath('data.acknowledged_no_evidence', true);
});

it('accepts a yes answer with a no-evidence acknowledgement and no value', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'yes',
            'acknowledged_no_evidence' => true,
        ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.answer_type', 'yes');
    $response->assertJsonPath('data.acknowledged_no_evidence', true);
});

it('validates the answer payload with an RFC 9457 problem detail', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'yes',
            'value' => 'not a url',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'validation_error');
    $response->assertJsonPath('errors.value', ['The value field must be a valid URL.']);
    $response->assertJsonStructure([
        'type', 'title', 'status', 'detail', 'instance', 'code', 'errors', 'request_id',
    ]);
});

it('rejects a duplicate answer once the existing answer is no longer pending', function () {
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $answer = $this->question->answer()->firstOrFail();
    $answer->update(['status' => ClarificationAnswerStatus::Accepted]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'yes',
            'value' => 'https://example.com/cert',
        ]);

    $response->assertStatus(409);
    $response->assertJsonPath('code', 'answer_already_exists');
    expect(ClarificationQuestion::find($this->question->id)->answer()->count())->toBe(1);
});

it('rejects answering an expired question with 422', function () {
    $this->question->update(['status' => ClarificationQuestionStatus::Expired]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
            'answer_type' => 'yes',
            'value' => 'https://example.com/cert',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'clarification_session_expired');
});

it('skips an open question and returns its new status', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/skip");

    $response->assertOk();
    $response->assertJsonPath('data.id', $this->question->id);
    $response->assertJsonPath('data.status', ClarificationQuestionStatus::Skipped->value);
    expect($this->question->refresh()->status)->toBe(ClarificationQuestionStatus::Skipped);
});

it('reviews an answer to preview the proposal without mutating the profile', function () {
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/review", []);

    $response->assertOk();
    $response->assertJsonPath('data.status', ClarificationAnswerStatus::Pending->value);
    $response->assertJsonPath('data.proposal.status', ClarificationProposalStatus::Proposed->value);
    $response->assertJsonPath('data.proposal.field', 'state');
    $response->assertJsonPath('data.proposal.target.type', 'candidate_skill');
    $response->assertJsonPath('data.proposal.after_value.state', SkillState::Verified->value);

    expect($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed);
});

it('accepts a verified proposal through the review endpoint', function () {
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/review", [
            'decision' => 'accept',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', ClarificationAnswerStatus::Accepted->value);
    $response->assertJsonPath('data.proposal.status', ClarificationProposalStatus::Accepted->value);

    $skill = $this->candidateSkill->fresh();
    expect($skill->state)->toBe(SkillState::Verified)
        ->and($skill->evidence)->toHaveCount(1)
        ->and($skill->evidence[0]['value'])->toBe('https://example.com/cert');

    Queue::assertPushed(ObserveClarificationStalenessJob::class, fn ($job) => $job->analysisId === $this->analysis->id);
    expect(ClarificationAuditEvent::where('event', 'proposal_accepted')->count())->toBe(1);
});

it('edits the years of experience through the review endpoint', function () {
    $this->candidateSkill->update(['years_experience' => 1.0]);
    $question = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $this->finding->id,
        'question_no' => 2,
    ]);

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$question->id}/answer", [
        'answer_type' => 'number',
        'value' => '3',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$question->id}/review", [
            'decision' => 'edit',
            'edited_value' => '5',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', ClarificationAnswerStatus::Accepted->value);
    expect($this->candidateSkill->fresh()->years_experience)->toEqual(5.0);
});

it('rejects a proposal through the review endpoint without mutating the profile', function () {
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/review", [
            'decision' => 'reject',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', ClarificationAnswerStatus::Rejected->value);
    $response->assertJsonPath('data.proposal.status', ClarificationProposalStatus::Rejected->value);
    expect($this->candidateSkill->fresh()->state)->toBe(SkillState::Claimed);
});

it('rejects an edit with a non-numeric value for a years proposal', function () {
    $this->candidateSkill->update(['years_experience' => 1.0]);
    $question = ClarificationQuestion::factory()->number()->create([
        'match_analysis_id' => $this->analysis->id,
        'match_finding_id' => $this->finding->id,
        'question_no' => 2,
    ]);
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$question->id}/answer", [
        'answer_type' => 'number',
        'value' => '3',
    ])->assertStatus(201);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$question->id}/review", [
            'decision' => 'edit',
            'edited_value' => 'not-a-number',
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'invalid_review_edit');
    expect($this->candidateSkill->fresh()->years_experience)->toEqual(1.0);
});

it('returns 404 for another candidate without leaking existence', function () {
    $otherUser = User::factory()->create();
    $otherProfile = CandidateProfile::factory()->create(['user_id' => $otherUser->id]);
    $otherAnalysis = MatchAnalysis::factory()->completed()->create(['candidate_profile_id' => $otherProfile->id]);
    $otherFinding = clarifyApiFinding($otherAnalysis);
    $otherQuestion = clarifyApiQuestion($otherAnalysis, $otherFinding);

    $this->actingAs($this->user)->getJson("/api/v1/matches/{$otherAnalysis->id}/clarifications")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$otherQuestion->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(404)->assertJsonPath('code', 'not_found');

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$otherQuestion->id}/review", [])
        ->assertStatus(404);

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$otherQuestion->id}/skip")
        ->assertStatus(404);
});

it('returns 401 for unauthenticated requests', function () {
    $this->getJson("/api/v1/matches/{$this->analysis->id}/clarifications")->assertStatus(401);
    $this->postJson("/api/v1/clarifications/{$this->question->id}/answer", [
        'answer_type' => 'yes',
        'value' => 'https://example.com/cert',
    ])->assertStatus(401);
    $this->postJson("/api/v1/clarifications/{$this->question->id}/review", [])->assertStatus(401);
    $this->postJson("/api/v1/clarifications/{$this->question->id}/skip")->assertStatus(401);
});

it('rate limits clarification writes', function () {
    config()->set('clarification.write_rate_limit', '2,1');

    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/skip")->assertOk();
    $this->actingAs($this->user)->postJson("/api/v1/clarifications/{$this->question->id}/skip")->assertOk();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/clarifications/{$this->question->id}/skip");

    $response->assertStatus(429);
    $response->assertJsonPath('code', 'too_many_requests');
});

it('enforces CSRF on stateful clarification writes', function () {
    config()->set('sanctum.stateful', [parse_url(config('app.url'), PHP_URL_HOST)]);

    $this->app['env'] = 'production';

    try {
        $response = $this->postJson(
            "/api/v1/clarifications/{$this->question->id}/answer",
            ['answer_type' => 'yes', 'value' => 'https://example.com/cert'],
            ['Referer' => config('app.url')],
        );
    } finally {
        $this->app['env'] = 'testing';
    }

    $response->assertStatus(419);
    $response->assertJsonPath('code', 'session_expired');
    expect(ClarificationQuestion::find($this->question->id)->answer()->count())->toBe(0);
});

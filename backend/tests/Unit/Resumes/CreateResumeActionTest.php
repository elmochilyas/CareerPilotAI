<?php

use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Resumes\Actions\CreateResumeAction;
use App\Domain\Resumes\Enums\ResumeStatus;
use App\Exceptions\Api\ConflictException;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('unit', 'resumes', 'create-resume-action');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
    $this->action = new CreateResumeAction(
        new FingerprintService,
    );
});

it('creates a resume with correct defaults', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->candidate_profile_id)->toBe($this->profile->id);
    expect($resume->opportunity_id)->toBe($this->opportunity->id);
    expect($resume->status)->toBe(ResumeStatus::Draft);
    expect($resume->version_no)->toBe(1);
    expect($resume->generated_by)->toBe('manual');
    expect($resume->content)->toBe([]);
});

it('creates profile and opportunity snapshots', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->profile_snapshot)->not->toBeNull();
    expect($resume->profile_snapshot)->toHaveKeys(['fingerprint', 'data', 'snapshot_at']);
    expect($resume->opportunity_snapshot)->not->toBeNull();
    expect($resume->opportunity_snapshot)->toHaveKeys(['fingerprint', 'data', 'snapshot_at']);
});

it('creates a fingerprint in snapshots', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->profile_snapshot['fingerprint'])->toBeString();
    expect($resume->profile_snapshot['fingerprint'])->not->toBeEmpty();
    expect($resume->opportunity_snapshot['fingerprint'])->toBeString();
    expect($resume->opportunity_snapshot['fingerprint'])->not->toBeEmpty();
});

it('sets status to draft', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->status)->toBe(ResumeStatus::Draft);
});

it('sets version to 1', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->version_no)->toBe(1);
});

it('throws ConflictException when draft already exists for same profile and opportunity', function () {
    Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'status' => ResumeStatus::Draft,
    ]);

    $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );
})->throws(ConflictException::class);

it('allows creating a new resume after approved one is deleted', function () {
    Resume::factory()->approved()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    Resume::where('job_opportunity_id', $this->opportunity->id)->delete();

    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->status)->toBe(ResumeStatus::Draft);
});

it('uses custom title when provided', function () {
    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
        title: 'My Custom Resume',
    );

    expect($resume->title)->toBe('My Custom Resume');
});

it('generates default title from opportunity when no title provided', function () {
    $this->opportunity->update(['title' => 'Senior Laravel Developer']);

    $resume = $this->action->execute(
        user: $this->user,
        opportunityId: $this->opportunity->id,
    );

    expect($resume->title)->toBe('Resume for Senior Laravel Developer');
});

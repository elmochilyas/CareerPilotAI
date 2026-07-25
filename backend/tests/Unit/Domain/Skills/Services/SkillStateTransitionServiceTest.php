<?php

use App\Domain\Skills\Enums\SkillState;
use App\Domain\Skills\Services\SkillStateTransitionService;

beforeEach(function () {
    $this->service = new SkillStateTransitionService;
});

it('allows claimed to verified', function () {
    expect($this->service->isTransitionAllowed(SkillState::Claimed, SkillState::Verified))->toBeTrue();
});

it('requires evidence for claimed to verified', function () {
    expect($this->service->requiresEvidence(SkillState::Claimed, SkillState::Verified))->toBeTrue();
});

it('allows claimed to learning', function () {
    expect($this->service->isTransitionAllowed(SkillState::Claimed, SkillState::Learning))->toBeTrue();
});

it('does not require evidence for claimed to learning', function () {
    expect($this->service->requiresEvidence(SkillState::Claimed, SkillState::Learning))->toBeFalse();
});

it('allows claimed to archived', function () {
    expect($this->service->isTransitionAllowed(SkillState::Claimed, SkillState::Archived))->toBeTrue();
});

it('allows verified to claimed', function () {
    expect($this->service->isTransitionAllowed(SkillState::Verified, SkillState::Claimed))->toBeTrue();
});

it('allows verified to archived', function () {
    expect($this->service->isTransitionAllowed(SkillState::Verified, SkillState::Archived))->toBeTrue();
});

it('allows learning to verified', function () {
    expect($this->service->isTransitionAllowed(SkillState::Learning, SkillState::Verified))->toBeTrue();
});

it('requires evidence for learning to verified', function () {
    expect($this->service->requiresEvidence(SkillState::Learning, SkillState::Verified))->toBeTrue();
});

it('allows learning to archived', function () {
    expect($this->service->isTransitionAllowed(SkillState::Learning, SkillState::Archived))->toBeTrue();
});

it('allows rejected to claimed', function () {
    expect($this->service->isTransitionAllowed(SkillState::Rejected, SkillState::Claimed))->toBeTrue();
});

it('allows rejected to archived', function () {
    expect($this->service->isTransitionAllowed(SkillState::Rejected, SkillState::Archived))->toBeTrue();
});

it('allows rejected to learning', function () {
    expect($this->service->isTransitionAllowed(SkillState::Rejected, SkillState::Learning))->toBeTrue();
});

it('allows archived to claimed', function () {
    expect($this->service->isTransitionAllowed(SkillState::Archived, SkillState::Claimed))->toBeTrue();
});

it('allows archived to learning', function () {
    expect($this->service->isTransitionAllowed(SkillState::Archived, SkillState::Learning))->toBeTrue();
});

it('allows archived to verified', function () {
    expect($this->service->isTransitionAllowed(SkillState::Archived, SkillState::Verified))->toBeTrue();
});

it('requires evidence for archived to verified', function () {
    expect($this->service->requiresEvidence(SkillState::Archived, SkillState::Verified))->toBeTrue();
});

it('allows claimed to rejected', function () {
    expect($this->service->isTransitionAllowed(SkillState::Claimed, SkillState::Rejected))->toBeTrue();
});

it('allows verified to learning', function () {
    expect($this->service->isTransitionAllowed(SkillState::Verified, SkillState::Learning))->toBeTrue();
});

it('does not require evidence for verified to learning', function () {
    expect($this->service->requiresEvidence(SkillState::Verified, SkillState::Learning))->toBeFalse();
});

it('allows verified to rejected', function () {
    expect($this->service->isTransitionAllowed(SkillState::Verified, SkillState::Rejected))->toBeTrue();
});

it('allows archived to rejected', function () {
    expect($this->service->isTransitionAllowed(SkillState::Archived, SkillState::Rejected))->toBeTrue();
});

it('allows learning to claimed', function () {
    expect($this->service->isTransitionAllowed(SkillState::Learning, SkillState::Claimed))->toBeTrue();
});

it('allows learning to rejected', function () {
    expect($this->service->isTransitionAllowed(SkillState::Learning, SkillState::Rejected))->toBeTrue();
});

it('forbids rejected to verified', function () {
    expect($this->service->isTransitionAllowed(SkillState::Rejected, SkillState::Verified))->toBeFalse();
});

it('returns all allowed transitions for a given state', function () {
    $transitions = $this->service->getAllowedTransitions(SkillState::Claimed);
    $toStates = array_column($transitions, 'to');
    expect($toStates)->toContain('verified', 'learning', 'archived', 'rejected');
});

it('returns all allowed transitions for all states', function () {
    $all = $this->service->getAllowedTransitions();
    expect($all)->toHaveKeys(['claimed', 'verified', 'learning', 'rejected', 'archived']);
});

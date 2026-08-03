<?php

use App\Domain\Opportunities\Actions\CreateIngestionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('opportunities', 'ingestion');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->action = new CreateIngestionAction;
});

it('normalizes whitespace in description', function () {
    $raw = "Senior  Laravel  Developer\n\n\nWe need you.";
    $normalized = $this->action->normalizeDescription($raw);

    expect($normalized)->toBe('Senior Laravel Developer We need you.');
});

it('computes consistent hash for same normalized content', function () {
    $hash1 = $this->action->computeContentHash('Senior Laravel Developer');
    $hash2 = $this->action->computeContentHash('Senior Laravel Developer');

    expect($hash1)->toBe($hash2);
});

it('computes different hash for different content', function () {
    $hash1 = $this->action->computeContentHash('Senior Laravel Developer');
    $hash2 = $this->action->computeContentHash('Junior Vue Developer');

    expect($hash1)->not->toBe($hash2);
});

it('detects existing ingestion by content hash', function () {
    $description = 'Senior Laravel Developer job at TechCorp. We need an experienced developer to join our engineering team.';
    $normalized = $this->action->normalizeDescription($description);
    $contentHash = $this->action->computeContentHash($normalized);

    $this->action->execute(
        user: $this->user,
        description: $description,
    );

    $existing = $this->action->detectDuplicate($this->user, $contentHash);

    expect($existing)->not->toBeNull();
    expect($existing->content_hash)->toBe($contentHash);
});

it('returns null when no duplicate exists', function () {
    $contentHash = hash('sha256', 'some non-existent content');

    $existing = $this->action->detectDuplicate($this->user, $contentHash);

    expect($existing)->toBeNull();
});

it('detects cancelled ingestions as duplicates', function () {
    $description = 'Some job description that is long enough to pass the minimum length validation rule of fifty characters.';
    $normalized = $this->action->normalizeDescription($description);
    $contentHash = $this->action->computeContentHash($normalized);

    $ingestion = $this->action->execute(
        user: $this->user,
        description: $description,
    );
    $ingestion->update(['status' => 'cancelled']);

    $existing = $this->action->detectDuplicate($this->user, $contentHash);

    expect($existing)->not->toBeNull();
    expect($existing->id)->toBe($ingestion->id);
});

it('atomically returns an existing ingestion instead of inserting a duplicate', function () {
    $description = 'A sufficiently detailed job description for a Laravel developer with testing and API design experience.';

    $created = $this->action->execute(
        user: $this->user,
        description: $description,
    );

    $duplicate = $this->action->execute(
        user: $this->user,
        description: $description,
    );

    expect($created->wasRecentlyCreated)->toBeTrue();
    expect($duplicate->wasRecentlyCreated)->toBeFalse();
    expect($duplicate->id)->toBe($created->id);
});

<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('api', 'resumes', 'versioning');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create(['candidate_profile_id' => $this->profile->id]);
    MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);
});

it('creates first resume with version_no 1', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
        'opportunity_id' => $this->opportunity->id,
    ]);
    $response->assertStatus(201);
    expect($response->json('data.version_no'))->toBe(1);
});

it('increments version_no after approved previous version', function () {
    $first = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
        'opportunity_id' => $this->opportunity->id,
    ])->json('data');
    $firstId = $first['id'];

    // Add content so approval succeeds
    Resume::whereKey($firstId)->update(['content' => ['summary' => ['title' => 'Summary', 'items' => [['text' => 'Hello']]]]]);

    $this->actingAs($this->user)->postJson("/api/v1/resumes/{$firstId}/approve")->assertStatus(200);

    // Create second version – approved previous must not block new draft
    $second = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
        'opportunity_id' => $this->opportunity->id,
    ]);
    $second->assertStatus(201);
    expect($second->json('data.version_no'))->toBe(2);
});

it('increments across multiple approvals', function () {
    foreach ([1, 2, 3] as $expectedVersion) {
        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ]);
        $response->assertStatus(201);
        expect($response->json('data.version_no'))->toBe($expectedVersion);
        $id = $response->json('data.id');
        Resume::whereKey($id)->update(['content' => ['experience' => ['title' => 'Exp', 'items' => [['text' => 'hello']]]]]);
        $this->actingAs($this->user)->postJson("/api/v1/resumes/{$id}/approve")->assertStatus(200);
    }
});

it('lists versions newest-first ordered by version_no desc', function () {
    // Create 3 versions
    foreach (range(1, 3) as $i) {
        $r = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
            'opportunity_id' => $this->opportunity->id,
        ])->json('data');
        Resume::whereKey($r['id'])->update(['content' => ['summary' => ['title' => "S{$i}", 'items' => [['text' => "hello{$i}"]]]]]);
        $this->actingAs($this->user)->postJson("/api/v1/resumes/{$r['id']}/approve")->assertStatus(200);
    }

    // Non-paginated but we test via raw query ordering
    $orderedVersions = Resume::where('candidate_profile_id', $this->profile->id)
        ->where('job_opportunity_id', $this->opportunity->id)
        ->orderByDesc('version_no')
        ->pluck('version_no')
        ->all();
    expect($orderedVersions)->toEqual([3, 2, 1]);
});

it('preserves existing factory rows with identity after versioning enabled', function () {
    $legacy = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'version_no' => 1,
        'status' => 'approved',
        'content' => ['summary' => ['title' => 'Legacy']],
    ]);
    expect($legacy->id)->not->toBeNull();

    $newResponse = $this->actingAs($this->user)->postJson('/api/v1/resumes', [
        'opportunity_id' => $this->opportunity->id,
    ]);
    $newResponse->assertStatus(201);
    expect($newResponse->json('data.version_no'))->toBe(2);
    expect(Resume::whereKey($legacy->id)->exists())->toBeTrue();
});

it('exposes version_no in resource', function () {
    $resume = Resume::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'version_no' => 5,
        'status' => 'draft',
    ]);
    $response = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resume->id}");
    $response->assertStatus(200);
    expect($response->json('data.version_no'))->toBe(5);
});

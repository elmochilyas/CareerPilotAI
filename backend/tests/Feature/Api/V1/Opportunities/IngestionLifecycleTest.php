<?php

use App\Jobs\ExtractJobInformationJob;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'lifecycle');

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
});

it('lists ingestions paginated', function () {
    JobOpportunityIngestion::factory()->count(3)->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/opportunities/ingestions');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);
    $response->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
    ]);
});

it('filters ingestions by status', function () {
    JobOpportunityIngestion::factory()->draft()->create(['user_id' => $this->user->id]);
    JobOpportunityIngestion::factory()->reviewReady()->create(['user_id' => $this->user->id]);
    JobOpportunityIngestion::factory()->failed()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/opportunities/ingestions?status=draft');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('draft');
});

it('shows only own ingestions in list', function () {
    $otherUser = User::factory()->create();
    JobOpportunityIngestion::factory()->count(3)->create(['user_id' => $otherUser->id]);
    JobOpportunityIngestion::factory()->count(2)->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/opportunities/ingestions');

    expect($response->json('data'))->toHaveCount(2);
});

it('shows a single ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($ingestion->id);
    expect($response->json('data.confirmed_opportunity_id'))->toBeNull();
});

it('returns the confirmed opportunity id in ingestion list and detail responses', function () {
    $profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);
    $opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $profile->id,
        'ingestion_id' => $ingestion->id,
        'company_id' => null,
    ]);

    $listResponse = $this->actingAs($this->user)
        ->getJson('/api/v1/opportunities/ingestions');

    $listResponse->assertOk()
        ->assertJsonPath('data.0.confirmed_opportunity_id', $opportunity->id);

    $detailResponse = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $detailResponse->assertOk()
        ->assertJsonPath('data.confirmed_opportunity_id', $opportunity->id);
});

it('retries a failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/retry");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('queued');
    Queue::assertPushed(
        ExtractJobInformationJob::class,
        fn (ExtractJobInformationJob $job): bool => $job->ingestionId === $ingestion->id
            && $job->expectedVersion === $response->json('data.version'),
    );
});

it('rejects retry for non-failed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/retry");

    $response->assertStatus(409);
});

it('cancels an ingestion from every non-terminal state', function (string $status) {
    $ingestion = JobOpportunityIngestion::factory()->create([
        'user_id' => $this->user->id,
        'status' => $status,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('cancelled');
})->with([
    'draft' => 'draft',
    'queued' => 'queued',
    'processing' => 'processing',
    'review ready' => 'review_ready',
    'failed' => 'failed',
]);

it('rejects cancel of confirmed ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->confirmed()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('already_confirmed');
});

it('rejects cancel of an already cancelled ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/opportunities/ingestions/{$ingestion->id}");

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('ingestion_not_modifiable');
});

it('reanalyzes a cancelled ingestion in place with a clean versioned attempt', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create([
        'user_id' => $this->user->id,
        'failure_reason' => 'Old failure',
        'failure_code' => 'permanent_failure',
        'retry_count' => 2,
        'version' => 4,
    ]);
    JobOpportunitySuggestion::factory()->count(2)->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/reanalyze");

    $response->assertOk()
        ->assertJsonPath('data.id', $ingestion->id)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.failure_reason', null)
        ->assertJsonPath('data.failure_code', null)
        ->assertJsonPath('data.retry_count', 3)
        ->assertJsonPath('data.version', 5);

    expect($ingestion->fresh()->suggestions()->count())->toBe(0);
    expect(JobOpportunityIngestion::query()->where('user_id', $this->user->id)->count())->toBe(1);

    Queue::assertPushed(
        ExtractJobInformationJob::class,
        fn (ExtractJobInformationJob $job): bool => $job->ingestionId === $ingestion->id
            && $job->expectedVersion === 5,
    );
});

it('rejects reanalysis unless the ingestion is cancelled', function () {
    $ingestion = JobOpportunityIngestion::factory()->failed()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/reanalyze");

    $response->assertStatus(409);
    expect($response->json('code'))->toBe('ingestion_not_cancelled');
    Queue::assertNothingPushed();
});

it('does not allow another candidate to reanalyze the ingestion', function () {
    $ingestion = JobOpportunityIngestion::factory()->cancelled()->create();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/reanalyze");

    $response->assertStatus(404);
    expect($ingestion->fresh()->status->value)->toBe('cancelled');
    Queue::assertNothingPushed();
});

it('shows source description', function () {
    $ingestion = JobOpportunityIngestion::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}/source");

    $response->assertStatus(200);
    expect($response->json('data.source_description'))->toBe($ingestion->source_description);
});

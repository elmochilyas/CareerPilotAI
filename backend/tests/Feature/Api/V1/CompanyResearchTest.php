<?php

use App\Jobs\ResearchCompanyJob;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class)->group('api', 'company-research');

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->company = Company::factory()->create([
        'name' => 'Acme Corp',
        'website' => 'https://acme.example.com',
        'name_normalized' => 'acme',
        'website_canonical' => 'acme.example.com',
    ]);
    $this->opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => $this->company->id,
        'company_name' => 'Acme Corp',
        'title' => 'Backend Developer',
        'summary' => 'Build reliable Laravel services.',
    ]);
});

it('allows owned opportunity to start research and returns 202 processing', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research", [
            'company_website' => 'https://acme.example.com',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'processing');

    expect($response->headers->get('Location'))->toContain("/api/v1/opportunities/{$this->opportunity->id}/company-research");

    Queue::assertPushed(ResearchCompanyJob::class, function ($job) {
        return $job->companyId === $this->company->id && $job->opportunityId === $this->opportunity->id;
    });

    $this->company->refresh();
    expect($this->company->research_status)->toBe('processing');
});

it('allows research with pasted content fallback', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research", [
            'pasted_content' => 'Acme builds a platform for developers.',
        ]);

    $response->assertStatus(202);
    Queue::assertPushed(ResearchCompanyJob::class, 1);
});

it('prevents concurrent research for same company', function () {
    Queue::fake();

    $this->company->update(['research_status' => 'processing']);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research");

    $response->assertStatus(409)
        ->assertJsonPath('code', 'research_in_progress');

    Queue::assertNotPushed(ResearchCompanyJob::class);
});

it('returns research status via GET', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$this->opportunity->id}/company-research");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'not_researched');
});

it('returns completed research via GET when company has research', function () {
    $company = Company::factory()->researched()->create([
        'name' => 'Acme',
    ]);
    $opp = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opp->id}/company-research");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.researched_at', $company->researched_at->toISOString());
});

it('marks stale after 30 days', function () {
    $company = Company::factory()->create([
        'name' => 'Stale Co',
        'research' => [
            'version' => 1,
            'status' => 'completed',
            'overview' => ['name' => 'Stale Co', 'website' => null, 'industry' => null, 'headquarters' => null, 'description' => 'Test'],
            'products' => [],
            'technology_context' => [],
            'role_context' => [],
            'recent_information' => [],
            'candidate_preparation' => [],
            'sources' => [],
            'generated_at' => now()->subDays(31)->toISOString(),
            'fallback_reason' => null,
            'ai_meta' => null,
        ],
        'research_status' => 'completed',
        'researched_at' => now()->subDays(31),
    ]);

    $opp = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opp->id}/company-research");

    $response->assertStatus(200)
        ->assertJsonPath('data.stale', true)
        ->assertJsonPath('data.stale_reason', 'research_outdated');
});

it('allows refresh to requeue when completed', function () {
    Queue::fake();

    $this->company->update([
        'research_status' => 'completed',
        'research' => [
            'version' => 1,
            'status' => 'completed',
            'overview' => ['name' => 'Acme', 'website' => null, 'industry' => null, 'headquarters' => null, 'description' => 'Test'],
            'products' => [],
            'technology_context' => [],
            'role_context' => [],
            'recent_information' => [],
            'candidate_preparation' => [],
            'sources' => [],
            'generated_at' => now()->toISOString(),
            'fallback_reason' => null,
            'ai_meta' => null,
        ],
        'researched_at' => now()->subDays(10),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research/refresh");

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'processing');

    Queue::assertPushed(ResearchCompanyJob::class, 1);
});

it('blocks cross-user access to company research', function () {
    $otherUser = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->getJson("/api/v1/opportunities/{$this->opportunity->id}/company-research")
        ->assertStatus(404);

    $this->actingAs($otherUser)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research")
        ->assertStatus(404);

    $this->actingAs($otherUser)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research/refresh")
        ->assertStatus(404);
});

it('rejects unauthenticated access', function () {
    $this->getJson("/api/v1/opportunities/{$this->opportunity->id}/company-research")
        ->assertStatus(401);

    $this->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research")
        ->assertStatus(401);
});

it('returns 404 for invalid opportunity id', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/opportunities/999999/company-research')
        ->assertStatus(404);
});

it('creates company when opportunity has no company yet', function () {
    Queue::fake();

    $oppWithoutCompany = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => null,
        'company_name' => 'NewCo Ltd.',
        'title' => 'Developer',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$oppWithoutCompany->id}/company-research");

    $response->assertStatus(202);

    $oppWithoutCompany->refresh();
    expect($oppWithoutCompany->company_id)->not->toBeNull();

    $company = Company::find($oppWithoutCompany->company_id);
    expect($company)->not->toBeNull()
        ->and($company->research_status)->toBe('processing');
});

it('reuses existing company for variant names', function () {
    Queue::fake();

    $existing = Company::factory()->create([
        'name' => 'OpenAI',
        'name_normalized' => 'openai',
        'website' => null,
        'website_canonical' => null,
    ]);

    $opp = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => null,
        'company_name' => 'OpenAI, Inc.',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$opp->id}/company-research")
        ->assertStatus(202);

    $opp->refresh();
    expect($opp->company_id)->toBe($existing->id);
});

it('does not auto-merge ambiguous similar names', function () {
    Company::factory()->create([
        'name' => 'Atlas AI',
        'name_normalized' => 'atlas ai',
        'website' => 'https://atlas-ai.example.com',
        'website_canonical' => 'atlas-ai.example.com',
    ]);

    $opp = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => null,
        'company_name' => 'Atlas Systems',
    ]);

    Queue::fake();

    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$opp->id}/company-research")
        ->assertStatus(202);

    $opp->refresh();
    $newCompany = Company::find($opp->company_id);
    expect($newCompany->name)->toBe('Atlas Systems');
    expect(Company::where('name_normalized', 'atlas ai')->count())->toBe(1);
});

it('validates company_website url format', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research", [
            'company_website' => 'not-a-url',
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('validates pasted_content max length', function () {
    $long = str_repeat('a', 20001);

    $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/{$this->opportunity->id}/company-research", [
            'pasted_content' => $long,
        ])
        ->assertStatus(422);
});

it('returns not_researched for opportunity without company', function () {
    $opp = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
        'company_id' => null,
        'company_name' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/{$opp->id}/company-research");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'not_researched');
});

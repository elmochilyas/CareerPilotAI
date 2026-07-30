<?php

use App\Models\CandidateProfile;
use App\Models\JobOpportunityIngestion;
use App\Models\JobOpportunitySuggestion;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class)->group('api', 'opportunities', 'confirm');

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->user = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $this->user->id]);
});

it('previews a review_ready ingestion with all decisions made', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['value' => 'Senior Laravel Developer'],
    ]);
    JobOpportunitySuggestion::factory()->rejected()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'company',
        'extracted_value' => ['value' => 'TechCorp'],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");

    $response->assertStatus(200);
    expect($response->json('data.data'))->toHaveKey('overview');
});

it('rejects preview when suggestions are pending', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");

    $response->assertStatus(409);
});

it('confirms a review_ready ingestion with all decisions made', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['value' => 'Senior Laravel Developer'],
    ]);
    JobOpportunitySuggestion::factory()->rejected()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'company',
        'extracted_value' => ['value' => 'TechCorp'],
    ]);

    $preview = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");
    $preview->assertStatus(200);
    $versionToken = $preview->json('data.version_token');

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('Senior Laravel Developer');

    $this->actingAs($this->user)
        ->getJson("/api/v1/opportunities/ingestions/{$ingestion->id}")
        ->assertOk()
        ->assertJsonPath('data.confirmed_opportunity_id', $response->json('data.id'));
});

it('rejects confirm when ingestion is not review_ready', function () {
    $ingestion = JobOpportunityIngestion::factory()->draft()->create([
        'user_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => str_repeat('a', 64),
        ]);

    $response->assertStatus(409);
});

it('rejects confirm when suggestions are pending', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->pending()->create([
        'ingestion_id' => $ingestion->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => str_repeat('a', 64),
        ]);

    $response->assertStatus(409);
});

it('is idempotent on repeated confirm', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    JobOpportunitySuggestion::factory()->accepted()->create([
        'ingestion_id' => $ingestion->id,
        'type' => 'job_title',
        'extracted_value' => ['value' => 'Senior Laravel Developer'],
    ]);

    $preview = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview");
    $versionToken = $preview->json('data.version_token');

    $firstResponse = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $versionToken,
        ]);

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($firstResponse->json('data.id'));
    $this->assertDatabaseCount('job_opportunities', 1);
});

it('confirms the exact production-shaped preview and excludes rejected suggestions', function () {
    $ingestion = JobOpportunityIngestion::factory()->reviewReady()->create([
        'user_id' => $this->user->id,
    ]);
    $skill = Skill::factory()->create([
        'name' => 'Laravel',
        'normalized_name' => 'laravel',
    ]);

    $createSuggestion = function (array $attributes) use ($ingestion): JobOpportunitySuggestion {
        return JobOpportunitySuggestion::factory()->create([
            'ingestion_id' => $ingestion->id,
            'review_decision' => 'accepted',
            'reviewed_at' => now(),
            ...$attributes,
        ]);
    };

    $createSuggestion([
        'type' => 'job_title',
        'group_key' => 'overview',
        'extracted_value' => ['value' => 'Laravel Engineer'],
    ]);
    $createSuggestion([
        'type' => 'company',
        'group_key' => 'overview',
        'extracted_value' => ['value' => 'Old Company'],
        'review_decision' => 'edited',
        'edited_value' => ['value' => 'CareerPilot'],
    ]);
    $createSuggestion([
        'type' => 'work_mode',
        'group_key' => 'work_details',
        'extracted_value' => ['value' => 'remote'],
        'review_decision' => 'rejected',
    ]);
    $createSuggestion([
        'type' => 'travel_required',
        'group_key' => 'work_details',
        'extracted_value' => ['value' => false],
    ]);
    $createSuggestion([
        'type' => 'responsibility',
        'group_key' => 'responsibilities',
        'extracted_value' => ['text' => 'Maintain legacy code'],
        'review_decision' => 'edited',
        'edited_value' => ['text' => 'Build reliable Laravel services'],
        'source_evidence' => 'Build and maintain Laravel services.',
    ]);
    $createSuggestion([
        'type' => 'responsibility',
        'group_key' => 'responsibilities',
        'extracted_value' => ['text' => 'Rejected responsibility'],
        'review_decision' => 'rejected',
    ]);
    $createSuggestion([
        'type' => 'required_experience',
        'group_key' => 'experience',
        'extracted_value' => ['summary' => 'Five years', 'years' => 5],
        'review_decision' => 'edited',
        'edited_value' => ['summary' => 'Professional backend experience', 'years' => 3],
        'source_evidence' => 'At least three years of backend experience.',
    ]);
    $createSuggestion([
        'type' => 'certification',
        'group_key' => 'languages_certifications',
        'extracted_value' => ['name' => 'AWS Associate', 'required' => false],
        'source_evidence' => 'AWS certification is preferred.',
    ]);
    $createSuggestion([
        'type' => 'compensation',
        'group_key' => 'compensation',
        'extracted_value' => [
            'salary_min' => 60000,
            'salary_max' => 80000,
            'currency' => 'USD',
            'period' => 'yearly',
            'text' => '$60k-$80k',
        ],
        'review_decision' => 'edited',
        'edited_value' => [
            'salary_min' => 70000,
            'salary_max' => 90000,
            'currency' => 'USD',
            'period' => 'yearly',
            'text' => '$70k-$90k',
        ],
    ]);
    $createSuggestion([
        'type' => 'benefit',
        'group_key' => 'compensation',
        'extracted_value' => ['name' => 'Health insurance'],
    ]);
    $createSuggestion([
        'type' => 'benefit',
        'group_key' => 'compensation',
        'extracted_value' => ['name' => 'Rejected benefit'],
        'review_decision' => 'rejected',
    ]);
    $createSuggestion([
        'type' => 'application_deadline',
        'group_key' => 'dates',
        'extracted_value' => ['value' => '2026-08-01'],
        'review_decision' => 'edited',
        'edited_value' => ['value' => '2026-08-15'],
    ]);
    $createSuggestion([
        'type' => 'additional_requirement',
        'group_key' => 'additional',
        'extracted_value' => ['text' => 'Old requirement'],
        'review_decision' => 'edited',
        'edited_value' => ['text' => 'Must be based in Morocco'],
        'source_evidence' => 'Candidates must be based in Morocco.',
    ]);
    $createSuggestion([
        'type' => 'required_skill',
        'group_key' => 'required_skills',
        'extracted_value' => ['label' => 'Laravel', 'proficiency' => 'advanced'],
        'resolution' => 'exact',
        'resolved_skill_id' => $skill->id,
        'source_evidence' => 'Advanced Laravel knowledge.',
    ]);
    $createSuggestion([
        'type' => 'required_skill',
        'group_key' => 'required_skills',
        'extracted_value' => ['label' => 'Laravel'],
        'resolution' => 'exact',
        'resolved_skill_id' => $skill->id,
        'source_evidence' => 'Laravel is required.',
    ]);

    $preview = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/preview")
        ->assertOk();

    expect($preview->json('data.data.overview.company'))->toBe('CareerPilot')
        ->and($preview->json('data.data.work_details.travel_required'))->toBeFalse()
        ->and($preview->json('data.data.required_skills'))->toHaveCount(1)
        ->and($preview->json('data.data.compensation.benefits'))->toBe(['Health insurance']);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/opportunities/ingestions/{$ingestion->id}/confirm", [
            'version_token' => $preview->json('data.version_token'),
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Laravel Engineer')
        ->assertJsonPath('data.company_name', 'CareerPilot')
        ->assertJsonPath('data.work_mode', null)
        ->assertJsonPath('data.travel_required', false)
        ->assertJsonPath('data.salary_min', '70000.00')
        ->assertJsonPath('data.salary_max', '90000.00')
        ->assertJsonPath('data.benefits.0', 'Health insurance')
        ->assertJsonPath('data.additional_requirements.0.text', 'Must be based in Morocco');

    $opportunityId = $response->json('data.id');

    $this->assertDatabaseHas('job_requirements', [
        'job_opportunity_id' => $opportunityId,
        'category' => 'responsibility',
        'content' => 'Build reliable Laravel services',
        'source_evidence' => 'Build and maintain Laravel services.',
    ]);
    $this->assertDatabaseHas('job_requirements', [
        'job_opportunity_id' => $opportunityId,
        'category' => 'required_experience',
        'content' => 'Professional backend experience (3 years)',
        'classification' => 'required',
    ]);
    $this->assertDatabaseHas('job_requirements', [
        'job_opportunity_id' => $opportunityId,
        'category' => 'preferred_certification',
        'content' => 'AWS Associate',
        'classification' => 'preferred',
    ]);
    $this->assertDatabaseMissing('job_requirements', [
        'job_opportunity_id' => $opportunityId,
        'content' => 'Rejected responsibility',
    ]);
    $this->assertDatabaseHas('job_opportunity_skills', [
        'job_opportunity_id' => $opportunityId,
        'skill_id' => $skill->id,
        'original_label' => 'Laravel',
        'classification' => 'required',
        'source_evidence' => "Advanced Laravel knowledge.\nLaravel is required.",
    ]);
    $this->assertDatabaseCount('job_opportunity_skills', 1);
});

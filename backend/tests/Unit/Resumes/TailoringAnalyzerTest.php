<?php

use App\Domain\Matching\Enums\RequirementSourceType;
use App\Domain\Matching\Services\FingerprintService;
use App\Domain\Matching\Services\OpportunitySnapshot;
use App\Domain\Matching\Services\ProfileSnapshot;
use App\Domain\Matching\Services\StalenessService;
use App\Domain\Resumes\Services\TailoringAnalyzer;
use App\Models\CandidateProfile;
use App\Models\JobOpportunity;
use App\Models\MatchAnalysis;
use App\Models\MatchFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('unit', 'resumes', 'tailoring-analyzer');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->profile = CandidateProfile::factory()->create(['user_id' => $this->user->id]);
    $this->opportunity = JobOpportunity::factory()->create([
        'candidate_profile_id' => $this->profile->id,
    ]);
    $this->analyzer = new TailoringAnalyzer(
        new StalenessService(app(FingerprintService::class)),
    );
});

it('returns relevant items grouped by section type', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 1,
        'requirement_text' => 'PHP proficiency',
        'importance' => 'required',
        'match_state' => 'matched',
        'factor' => 1.00,
        'tailoring_relevance' => 'high',
        'category' => null,
        'display_order' => 0,
        'evidence_refs' => [['type' => 'profile_item', 'id' => 10]],
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobRequirement,
        'source_id' => 2,
        'requirement_text' => '3+ years backend experience',
        'importance' => 'required',
        'match_state' => 'matched',
        'factor' => 0.85,
        'category' => 'responsibility',
        'tailoring_relevance' => 'medium',
        'evidence_refs' => [['type' => 'profile_item', 'id' => 20]],
        'display_order' => 1,
    ]);

    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    expect($result)->toHaveKeys(['skills', 'experience', 'education', 'projects', 'certifications', 'languages']);
    expect($result['skills'])->toHaveCount(1);
    expect($result['experience'])->toHaveCount(1);
    expect($result['skills']->first()['relevance'])->toBe('high');
    expect($result['experience']->first()['relevance'])->toBe('medium');
});

it('excludes items with low relevance', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 1,
        'requirement_text' => 'Nice to have Go',
        'importance' => 'preferred',
        'match_state' => 'gap',
        'factor' => 0.00,
        'tailoring_relevance' => 'low',
        'category' => null,
        'display_order' => 0,
    ]);

    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    expect($result['skills'])->toHaveCount(0);
});

it('excludes items with excluded relevance', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 1,
        'requirement_text' => 'Irrelevant skill',
        'importance' => 'preferred',
        'match_state' => 'gap',
        'factor' => 0.00,
        'tailoring_relevance' => 'excluded',
        'category' => null,
        'display_order' => 0,
    ]);

    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    expect($result['skills'])->toHaveCount(0);
});

it('includes items with high and medium relevance', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 1,
        'requirement_text' => 'Critical skill',
        'importance' => 'required',
        'match_state' => 'matched',
        'factor' => 1.00,
        'tailoring_relevance' => 'high',
        'category' => null,
        'display_order' => 0,
        'evidence_refs' => [['type' => 'profile_item', 'id' => 1]],
    ]);

    MatchFinding::create([
        'match_analysis_id' => $analysis->id,
        'source_type' => RequirementSourceType::JobOpportunitySkill,
        'source_id' => 2,
        'requirement_text' => 'Important skill',
        'importance' => 'required',
        'match_state' => 'partial',
        'factor' => 0.50,
        'tailoring_relevance' => 'medium',
        'category' => null,
        'display_order' => 1,
        'evidence_refs' => [['type' => 'profile_item', 'id' => 2]],
    ]);

    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    expect($result['skills'])->toHaveCount(2);
});

it('returns empty sections when no match_findings exist', function () {
    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
    ]);

    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    foreach ($result as $section) {
        expect($section)->toHaveCount(0);
    }
});

it('returns empty sections when no completed analysis exists', function () {
    $result = $this->analyzer->analyzeRelevance($this->profile, $this->opportunity);

    foreach ($result as $section) {
        expect($section)->toHaveCount(0);
    }
});

it('computes staleness as fresh when fingerprints match', function () {
    $fingerprintService = app(FingerprintService::class);
    $profileSnapshot = ProfileSnapshot::fromCandidateProfile($this->profile);
    $opportunitySnapshot = OpportunitySnapshot::fromJobOpportunity($this->opportunity);

    $analysis = MatchAnalysis::factory()->completed()->create([
        'candidate_profile_id' => $this->profile->id,
        'job_opportunity_id' => $this->opportunity->id,
        'profile_fingerprint' => $fingerprintService->profile($profileSnapshot),
        'opportunity_fingerprint' => $fingerprintService->opportunity($opportunitySnapshot),
        'profile_updated_at' => $this->profile->updated_at,
        'opportunity_updated_at' => $this->opportunity->updated_at,
    ]);

    $result = $this->analyzer->computeStaleness($this->profile, $this->opportunity);

    expect($result)->toBe('fresh');
});

it('computes staleness as unknown when no analysis exists', function () {
    $result = $this->analyzer->computeStaleness($this->profile, $this->opportunity);

    expect($result)->toBe('unknown');
});

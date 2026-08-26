<?php

use App\Domain\CompanyResearch\Services\CompanyResolver;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('company-research', 'resolver');

beforeEach(function () {
    $this->resolver = app(CompanyResolver::class);
});

it('normalizes variant names to same key', function () {
    expect($this->resolver->normalizeName('OpenAI'))->toBe('openai')
        ->and($this->resolver->normalizeName('OpenAI, Inc.'))->toBe('openai')
        ->and($this->resolver->normalizeName(' openai '))->toBe('openai')
        ->and($this->resolver->normalizeName('OpenAI, Inc'))->toBe('openai');
});

it('canonicalizes domains', function () {
    expect($this->resolver->canonicalizeDomain('https://www.Example.COM/about'))->toBe('example.com')
        ->and($this->resolver->canonicalizeDomain('example.com'))->toBe('example.com')
        ->and($this->resolver->canonicalizeDomain('http://sub.example.com:8080/path'))->toBe('sub.example.com');
});

it('reuses company by normalized name', function () {
    $user = User::factory()->create();
    CandidateProfile::factory()->create(['user_id' => $user->id]);

    $existing = Company::factory()->create([
        'name' => 'OpenAI',
        'name_normalized' => 'openai',
        'website_canonical' => null,
    ]);

    $found = $this->resolver->resolveOrCreate('OpenAI, Inc.', null);

    expect($found->id)->toBe($existing->id);
});

it('prefers domain over name variant', function () {
    $existing = Company::factory()->create([
        'name' => 'Acme Corp',
        'website' => 'https://acme.example.com',
        'website_canonical' => 'acme.example.com',
        'name_normalized' => 'acme',
    ]);

    $found = $this->resolver->resolveOrCreate('Acme Corporation', 'https://acme.example.com/team');

    expect($found->id)->toBe($existing->id);
});

it('does not merge ambiguous similar names', function () {
    Company::factory()->create([
        'name' => 'Atlas AI',
        'name_normalized' => 'atlas ai',
        'website_canonical' => 'atlas-ai.example.com',
        'website' => 'https://atlas-ai.example.com',
    ]);

    $result = $this->resolver->resolveOrCreate('Atlas Systems', 'https://atlas-systems.example.com');

    // Should create new, not reuse
    expect($result->name)->toBe('Atlas Systems');
    expect(Company::where('name_normalized', 'atlas ai')->count())->toBe(1);
    expect(Company::count())->toBe(2);
});

it('returns null for empty identity preserving opportunity', function () {
    $result = $this->resolver->resolveOrCreate(null, null);
    expect($result)->toBeNull();

    $result2 = $this->resolver->resolveOrCreate('', '');
    expect($result2)->toBeNull();
});

it('treats generic names as ambiguous without domain', function () {
    $result = $this->resolver->resolveOrCreate('Company', null);
    expect($result)->toBeNull();
});

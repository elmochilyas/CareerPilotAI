<?php

namespace Tests\Support;

use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;

class FakeJobAnalyzer implements JobAnalyzer
{
    public function analyze(string $jobDescription): JobAnalysisResult
    {
        return new JobAnalysisResult(
            schemaVersion: '1.0.0',
            job: [
                'title' => 'Senior Laravel Developer',
                'company' => 'TechCorp',
                'department' => 'Engineering',
                'external_reference' => null,
                'summary' => 'We are looking for a Senior Laravel Developer to join our growing team.',
                'application_url' => 'https://techcorp.example.com/apply',
                'location' => [
                    'city' => 'Casablanca',
                    'region' => 'Casablanca-Settat',
                    'country' => 'Morocco',
                ],
                'work_mode' => 'hybrid',
                'contract_type' => 'full-time',
                'seniority_level' => 'senior',
                'working_hours' => '40h/week',
                'travel_required' => false,
                'relocation_required' => false,
                'responsibilities' => [
                    [
                        'text' => 'Develop and maintain Laravel applications',
                        'source' => 'Develop and maintain Laravel applications.',
                    ],
                    [
                        'text' => 'Lead code reviews and mentor junior developers',
                        'source' => 'Lead code reviews and mentor junior developers.',
                    ],
                ],
                'required_experience' => [
                    [
                        'years' => 5,
                        'summary' => 'Experience with Laravel',
                        'source' => 'Five years of Laravel experience.',
                    ],
                ],
                'preferred_experience' => [
                    [
                        'years' => 2,
                        'summary' => 'Experience with DevOps',
                        'source' => 'DevOps experience is preferred.',
                    ],
                ],
                'education_requirements' => [
                    [
                        'degree' => 'Bachelor',
                        'field' => 'Computer Science',
                        'required' => true,
                    ],
                ],
                'required_skills' => [
                    [
                        'label' => 'Laravel',
                        'proficiency' => 'advanced',
                        'years_experience' => 5,
                        'source' => 'Advanced Laravel skills are required.',
                    ],
                    [
                        'label' => 'PHP',
                        'proficiency' => 'advanced',
                        'years_experience' => 5,
                        'source' => 'Advanced PHP skills are required.',
                    ],
                ],
                'preferred_skills' => [
                    [
                        'label' => 'Vue.js',
                        'proficiency' => 'intermediate',
                        'years_experience' => 2,
                        'source' => 'Vue.js experience is preferred.',
                    ],
                ],
                'languages' => [
                    [
                        'language' => 'English',
                        'required' => true,
                        'proficiency' => 'advanced',
                        'source' => 'Advanced English is required.',
                    ],
                ],
                'certifications' => [
                    [
                        'name' => 'AWS Certified Developer',
                        'required' => false,
                        'source' => 'AWS certification is preferred.',
                    ],
                ],
                'compensation' => [
                    'salary_min' => 60000,
                    'salary_max' => 90000,
                    'currency' => 'USD',
                    'period' => 'yearly',
                    'text' => 'Competitive salary and benefits.',
                ],
                'benefits' => ['Health insurance', 'Training budget'],
                'publication_date' => '2026-07-01',
                'application_deadline' => '2026-08-01',
                'expected_start_date' => '2026-09-01',
                'employment_duration' => 'permanent',
                'additional_requirements' => ['Must be based in Morocco'],
            ],
            warnings: [],
            provider: 'fake',
            model: 'fake-job-analyzer-v1',
            promptVersion: '1.0.0',
            latencyMs: 150,
            tokensPrompt: 800,
            tokensCompletion: 400,
            responseId: 'fake_job_'.uniqid(),
        );
    }
}

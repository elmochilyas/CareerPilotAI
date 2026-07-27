<?php

namespace Tests\Support;

use App\Domain\Opportunities\Data\JobAnalysisResult;
use App\Domain\Opportunities\Services\Contracts\JobAnalyzer;

class FakeJobAnalyzer implements JobAnalyzer
{
    public function analyze(string $jobDescription): JobAnalysisResult
    {
        return new JobAnalysisResult(
            title: 'Senior Laravel Developer',
            companyName: 'TechCorp',
            department: 'Engineering',
            externalReference: null,
            summary: 'We are looking for a Senior Laravel Developer to join our growing team.',
            applicationUrl: 'https://techcorp.example.com/apply',
            city: 'Casablanca',
            region: 'Casablanca-Settat',
            country: 'Morocco',
            workMode: 'hybrid',
            contractType: 'full_time',
            seniorityLevel: 'senior',
            workingHours: '40h/week',
            travelRequired: false,
            relocationRequired: false,
            salaryMin: 60000.00,
            salaryMax: 90000.00,
            salaryCurrency: 'USD',
            salaryPeriod: 'yearly',
            compensationText: 'Competitive salary + benefits',
            benefits: ['Health insurance', 'Remote allowance', 'Training budget'],
            publicationDate: '2026-07-01',
            applicationDeadline: '2026-08-01',
            expectedStartDate: '2026-09-01',
            employmentDuration: 'permanent',
            responsibilities: [
                ['description' => 'Develop and maintain Laravel applications', 'order' => 1],
                ['description' => 'Lead code reviews and mentor junior developers', 'order' => 2],
                ['description' => 'Design and implement RESTful APIs', 'order' => 3],
            ],
            requiredExperience: [
                ['years_required' => 5.0, 'description' => 'Experience with Laravel'],
                ['years_required' => 3.0, 'description' => 'Experience with Vue.js'],
            ],
            preferredExperience: [
                ['years_required' => 2.0, 'description' => 'Experience with DevOps'],
            ],
            educationRequirements: [
                ['degree' => 'Bachelor', 'field' => 'Computer Science'],
            ],
            requiredSkills: [
                ['name' => 'Laravel', 'classification' => 'required', 'proficiency' => 'advanced', 'years_experience' => 5.0],
                ['name' => 'PHP', 'classification' => 'required', 'proficiency' => 'advanced', 'years_experience' => 5.0],
                ['name' => 'MySQL', 'classification' => 'required', 'proficiency' => 'intermediate', 'years_experience' => 3.0],
            ],
            preferredSkills: [
                ['name' => 'Vue.js', 'classification' => 'preferred', 'proficiency' => 'intermediate', 'years_experience' => 2.0],
                ['name' => 'Docker', 'classification' => 'preferred', 'proficiency' => 'beginner', 'years_experience' => 1.0],
            ],
            languages: [
                ['language' => 'French', 'proficiency' => 'advanced'],
                ['language' => 'English', 'proficiency' => 'advanced'],
            ],
            certifications: [
                ['name' => 'AWS Certified Developer'],
            ],
            additionalRequirements: ['Must be based in Morocco'],
            warnings: [],
            provider: 'fake',
            model: 'fake-job-analyzer-v1',
            promptVersion: '1.0.0',
            latencyMs: 150,
            tokensPrompt: 800,
            tokensCompletion: 400,
            responseId: 'fake_job_'.uniqid(),
            schemaVersion: '1.0.0',
        );
    }
}

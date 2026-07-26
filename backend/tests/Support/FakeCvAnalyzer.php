<?php

namespace Tests\Support;

use App\Domain\CvIngestion\Data\CvAnalysisResult;
use App\Domain\CvIngestion\Services\Contracts\CvAnalyzer;

class FakeCvAnalyzer implements CvAnalyzer
{
    public function analyze(string $extractedText): CvAnalysisResult
    {
        return new CvAnalysisResult(
            basicInformation: [
                'full_name' => 'Ilyas El Moch',
                'email' => 'ilyas@example.com',
                'phone' => '+212600000000',
                'city' => 'Casablanca',
                'country' => 'Morocco',
            ],
            headline: 'Full Stack Developer & AI Enthusiast',
            professionalSummary: 'Passionate full-stack developer with experience building modern web applications using Laravel, Vue.js, and AI-powered solutions.',
            professionalLinks: [
                [
                    'type' => 'github',
                    'url' => 'https://github.com/ilyas-el-moch',
                    'source' => ['page' => 1, 'text' => 'github.com/ilyas-el-moch'],
                ],
                [
                    'type' => 'linkedin',
                    'url' => 'https://linkedin.com/in/ilyas-el-moch',
                    'source' => ['page' => 1, 'text' => 'linkedin.com/in/ilyas-el-moch'],
                ],
            ],
            experiences: [
                [
                    'title' => 'Mobile Developer Intern',
                    'organization' => '3LM Solutions',
                    'location' => 'Remote, Tunisia',
                    'start_date' => 'July 2026',
                    'end_date' => null,
                    'is_current' => true,
                    'description' => 'Developed the dashboard of a mobile document and expense management application. Built interactive charts and data visualization components.',
                    'technologies' => ['React Native', 'Expo', 'Victory Native', 'SheetJS'],
                    'source' => ['page' => 1, 'text' => 'Mobile Developer Intern at 3LM Solutions'],
                ],
            ],
            projects: [
                [
                    'name' => 'LaraSkills',
                    'role' => 'Full Stack Developer',
                    'description' => 'AI-powered skills assessment platform built with Laravel and Vue.js.',
                    'technologies' => ['Laravel', 'Vue.js', 'OpenAI', 'MySQL'],
                    'url' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'source' => ['page' => 2, 'text' => 'LaraSkills - AI-powered skills assessment'],
                ],
                [
                    'name' => 'TalentMatch',
                    'role' => 'Backend Developer',
                    'description' => 'Job matching platform using AI-driven candidate-opportunity alignment.',
                    'technologies' => ['Laravel', 'Python', 'PostgreSQL', 'Redis'],
                    'url' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'source' => ['page' => 2, 'text' => 'TalentMatch - Job matching platform'],
                ],
                [
                    'name' => 'Noter-Solution',
                    'role' => 'Full Stack Developer',
                    'description' => 'Note-taking and collaboration solution with real-time sync capabilities.',
                    'technologies' => ['Vue.js', 'Node.js', 'WebSocket', 'MongoDB'],
                    'url' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'source' => ['page' => 2, 'text' => 'Noter-Solution - Note-taking app'],
                ],
            ],
            education: [
                [
                    'degree' => 'Bachelor of Science in Computer Science',
                    'field_of_study' => 'Computer Science',
                    'institution' => 'University of Casablanca',
                    'location' => 'Casablanca, Morocco',
                    'start_date' => 'September 2023',
                    'end_date' => null,
                    'is_current' => true,
                    'description' => null,
                    'source' => ['page' => 2, 'text' => 'BSc Computer Science at University of Casablanca'],
                ],
                [
                    'degree' => 'High School Diploma',
                    'field_of_study' => 'Science',
                    'institution' => 'Lycée XYZ',
                    'location' => 'Casablanca, Morocco',
                    'start_date' => 'September 2020',
                    'end_date' => 'June 2023',
                    'is_current' => false,
                    'description' => null,
                    'source' => ['page' => 2, 'text' => 'Baccalaureate in Science'],
                ],
                [
                    'degree' => 'Online Certification',
                    'field_of_study' => 'Web Development',
                    'institution' => 'FreeCodeCamp',
                    'location' => 'Online',
                    'start_date' => null,
                    'end_date' => '2024',
                    'is_current' => false,
                    'description' => 'Responsive Web Design Certification',
                    'source' => ['page' => 2, 'text' => 'FreeCodeCamp Certification'],
                ],
            ],
            certifications: [],
            languages: [
                [
                    'language' => 'Arabic',
                    'proficiency' => 'Native',
                    'source' => ['page' => 3, 'text' => 'Arabic: Native'],
                ],
                [
                    'language' => 'French',
                    'proficiency' => 'Advanced',
                    'source' => ['page' => 3, 'text' => 'French: Advanced'],
                ],
                [
                    'language' => 'English',
                    'proficiency' => 'Advanced',
                    'source' => ['page' => 3, 'text' => 'English: Advanced'],
                ],
                [
                    'language' => 'German',
                    'proficiency' => 'Intermediate',
                    'source' => ['page' => 3, 'text' => 'German: Intermediate'],
                ],
            ],
            skills: [
                ['name' => 'Laravel', 'category' => 'Backend', 'source' => ['page' => 1, 'text' => 'Laravel']],
                ['name' => 'Vue.js', 'category' => 'Frontend', 'source' => ['page' => 1, 'text' => 'Vue.js']],
                ['name' => 'React Native', 'category' => 'Frontend', 'source' => ['page' => 1, 'text' => 'React Native']],
                ['name' => 'Expo', 'category' => 'Frontend', 'source' => ['page' => 1, 'text' => 'Expo']],
                ['name' => 'MySQL', 'category' => 'Databases', 'source' => ['page' => 1, 'text' => 'MySQL']],
                ['name' => 'PostgreSQL', 'category' => 'Databases', 'source' => ['page' => 1, 'text' => 'PostgreSQL']],
                ['name' => 'Python', 'category' => 'Backend', 'source' => ['page' => 1, 'text' => 'Python']],
                ['name' => 'Docker', 'category' => 'DevOps & Tools', 'source' => ['page' => 1, 'text' => 'Docker']],
                ['name' => 'Git', 'category' => 'DevOps & Tools', 'source' => ['page' => 1, 'text' => 'Git']],
                ['name' => 'OpenAI API', 'category' => 'AI & Workflow', 'source' => ['page' => 2, 'text' => 'OpenAI']],
            ],
            warnings: [],
            provider: 'fake',
            model: 'fake-analyzer-v1',
            promptVersion: '1.1.0',
            latencyMs: 100,
            tokensPrompt: 500,
            tokensCompletion: 200,
            responseId: 'fake_'.uniqid(),
            schemaVersion: '1.1.0',
        );
    }
}

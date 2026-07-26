<?php

use App\Domain\CvIngestion\Services\CvAnalysisSchemaValidator;

beforeEach(function () {
    $this->validator = new CvAnalysisSchemaValidator;
});

it('validates a complete structured response', function () {
    $response = [
        'schema_version' => '1.1.0',
        'basic_information' => [
            'full_name' => 'Ilyas El Moch',
            'email' => 'ilyas@example.com',
            'phone' => '+212600000000',
            'city' => 'Casablanca',
            'country' => 'Morocco',
        ],
        'headline' => 'Full Stack Developer',
        'professional_summary' => 'A passionate developer.',
        'professional_links' => [
            ['type' => 'github', 'url' => 'https://github.com/test', 'source' => ['page' => 1]],
            ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/test', 'source' => null],
        ],
        'experiences' => [
            [
                'title' => 'Developer',
                'organization' => 'Acme Corp',
                'location' => 'Remote',
                'start_date' => '2024-01',
                'end_date' => null,
                'is_current' => true,
                'description' => 'Built apps.',
                'technologies' => ['Laravel', 'Vue.js'],
                'source' => ['page' => 1, 'text' => 'Developer at Acme'],
            ],
        ],
        'projects' => [
            [
                'name' => 'My Project',
                'role' => 'Lead',
                'description' => 'A great project.',
                'technologies' => ['PHP', 'JS'],
                'url' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => false,
                'source' => null,
            ],
        ],
        'education' => [
            [
                'degree' => 'BSc',
                'field_of_study' => 'CS',
                'institution' => 'University',
                'location' => 'City',
                'start_date' => '2020-09',
                'end_date' => '2024-06',
                'is_current' => false,
                'description' => null,
                'source' => null,
            ],
        ],
        'certifications' => [],
        'languages' => [
            ['language' => 'Arabic', 'proficiency' => 'Native', 'source' => null],
            ['language' => 'English', 'proficiency' => 'Advanced', 'source' => null],
        ],
        'skills' => [
            ['name' => 'PHP', 'category' => 'Backend', 'source' => null],
            ['name' => 'Vue.js', 'category' => 'Frontend', 'source' => null],
        ],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result'])->not->toBeNull();
});

it('validates empty response as structurally valid with no entities', function () {
    $response = [
        'basic_information' => [],
        'headline' => null,
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result'])->not->toBeNull();
    expect($result['result']->experiences)->toHaveCount(0);
    expect($result['result']->skills)->toHaveCount(0);
});

it('silently skips invalid professional link type', function () {
    $response = [
        'basic_information' => [],
        'headline' => 'Test',
        'professional_summary' => null,
        'professional_links' => [
            ['type' => 'facebook', 'url' => 'https://fb.com'],
        ],
        'experiences' => [
            ['title' => 'Dev', 'organization' => 'Co', 'description' => 'Work'],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->professionalLinks)->toHaveCount(0);
});

it('validates experience entity grouping', function () {
    $response = [
        'basic_information' => [],
        'headline' => 'Test',
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [
            [
                'title' => 'Developer',
                'organization' => 'Acme Corp',
                'location' => 'Remote',
                'start_date' => '2024-01',
                'end_date' => null,
                'is_current' => true,
                'description' => 'Full description with all details.',
                'technologies' => ['Laravel', 'Vue.js', 'MySQL'],
                'source' => null,
            ],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->experiences)->toHaveCount(1);
    expect($result['result']->experiences[0]['title'])->toBe('Developer');
    expect($result['result']->experiences[0]['organization'])->toBe('Acme Corp');
    expect($result['result']->experiences[0]['technologies'])->toHaveCount(3);
});

it('skips experience without title without failing entire validation', function () {
    $response = [
        'basic_information' => [],
        'headline' => 'Test',
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [
            [
                'organization' => 'Acme Corp',
                'description' => 'Missing title.',
            ],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->experiences)->toHaveCount(0);
    expect($result['result']->headline)->toBe('Test');
});

it('validates project entity grouping', function () {
    $response = [
        'basic_information' => [],
        'headline' => null,
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [],
        'projects' => [
            [
                'name' => 'LaraSkills',
                'role' => 'Full Stack Developer',
                'description' => 'AI skills platform.',
                'technologies' => ['Laravel', 'Vue.js', 'OpenAI'],
                'url' => null,
                'start_date' => null,
                'end_date' => null,
                'is_current' => false,
                'source' => null,
            ],
        ],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->projects)->toHaveCount(1);
    expect($result['result']->projects[0]['technologies'])->toContain('OpenAI');
});

it('validates multiple languages', function () {
    $response = [
        'basic_information' => [],
        'headline' => 'Dev',
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [
            ['title' => 'Dev', 'organization' => 'Co', 'description' => 'Work'],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [
            ['language' => 'Arabic', 'proficiency' => 'Native', 'source' => null],
            ['language' => 'French', 'proficiency' => 'Advanced', 'source' => null],
            ['language' => 'English', 'proficiency' => 'Fluent', 'source' => null],
            ['language' => 'German', 'proficiency' => 'Intermediate', 'source' => null],
        ],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->languages)->toHaveCount(4);
});

it('validates skills with categories', function () {
    $response = [
        'basic_information' => [],
        'headline' => 'Dev',
        'professional_summary' => null,
        'professional_links' => [],
        'experiences' => [
            ['title' => 'Dev', 'organization' => 'Co', 'description' => 'Work'],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [
            ['name' => 'Laravel', 'category' => 'Backend', 'source' => null],
            ['name' => 'Vue.js', 'category' => 'Frontend', 'source' => null],
            ['name' => 'MySQL', 'category' => 'Databases', 'source' => null],
        ],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->skills)->toHaveCount(3);
    expect($result['result']->skills[0]['category'])->toBe('Backend');
});

it('handles null values in optional fields', function () {
    $response = [
        'basic_information' => [],
        'headline' => null,
        'professional_summary' => 'Summary only.',
        'professional_links' => [],
        'experiences' => [
            [
                'title' => 'Dev',
                'organization' => null,
                'description' => 'Worked on stuff.',
                'technologies' => [],
            ],
        ],
        'projects' => [],
        'education' => [],
        'certifications' => [],
        'languages' => [],
        'skills' => [],
        'warnings' => [],
    ];

    $result = $this->validator->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['result']->headline)->toBeNull();
    expect($result['result']->professionalSummary)->toBe('Summary only.');
});

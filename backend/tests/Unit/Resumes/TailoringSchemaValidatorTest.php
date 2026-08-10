<?php

use App\Domain\Resumes\Services\TailoringSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('unit', 'resumes', 'tailoring-schema-validator');

beforeEach(function () {
    $this->validator = new TailoringSchemaValidator;
});

it('validates correct schema structure', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => 'Original experience text.',
                'proposed_text' => 'Tailored experience text.',
                'change_type' => 'reword',
            ],
        ],
    ];

    $result = $this->validator->validate($output);

    expect($result)->toBeTrue();
});

it('rejects missing proposals key', function () {
    $this->validator->validate(['not_proposals' => []]);
})->throws(InvalidArgumentException::class, 'missing or non-array "proposals" key');

it('rejects missing required fields in proposal', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
            ],
        ],
    ];

    $this->validator->validate($output);
})->throws(InvalidArgumentException::class, 'missing or invalid');

it('rejects invalid change_type', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => 'Original text.',
                'proposed_text' => 'Proposed text.',
                'change_type' => 'invalid_type',
            ],
        ],
    ];

    $this->validator->validate($output);
})->throws(InvalidArgumentException::class, 'invalid change_type');

it('rejects empty original_text for reword change type', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => '',
                'proposed_text' => 'Proposed text.',
                'change_type' => 'reword',
            ],
        ],
    ];

    $this->validator->validate($output);
})->throws(InvalidArgumentException::class, 'original_text cannot be empty');

it('rejects empty proposed_text for reword change type', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => 'Original text.',
                'proposed_text' => '',
                'change_type' => 'reword',
            ],
        ],
    ];

    $this->validator->validate($output);
})->throws(InvalidArgumentException::class, 'proposed_text cannot be empty');

it('accepts valid minimal output with include change type', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => '',
                'proposed_text' => 'New item to include.',
                'change_type' => 'include',
            ],
        ],
    ];

    $result = $this->validator->validate($output);

    expect($result)->toBeTrue();
});

it('accepts valid minimal output with exclude change type', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 1,
                'original_text' => 'Item to exclude.',
                'proposed_text' => '',
                'change_type' => 'exclude',
            ],
        ],
    ];

    $result = $this->validator->validate($output);

    expect($result)->toBeTrue();
});

it('rejects source_id not in valid source ids when provided', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 99,
                'original_text' => 'Original text.',
                'proposed_text' => 'Proposed text.',
                'change_type' => 'reword',
            ],
        ],
    ];

    $this->validator->validate($output, [1, 2, 3]);
})->throws(InvalidArgumentException::class, 'is not a valid profile item ID');

it('accepts source_id that is in valid source ids', function () {
    $output = [
        'proposals' => [
            [
                'source_type' => 'profile_item',
                'source_id' => 2,
                'original_text' => 'Original text.',
                'proposed_text' => 'Proposed text.',
                'change_type' => 'reword',
            ],
        ],
    ];

    $result = $this->validator->validate($output, [1, 2, 3]);

    expect($result)->toBeTrue();
});

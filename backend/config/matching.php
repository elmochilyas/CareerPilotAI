<?php

return [
    'algorithm_version' => '1.0.0',
    'scoring_version' => '1.0.0',
    'classifier_schema_version' => '1.0.0',

    'weights' => [
        'required_skills' => 0.500,
        'preferred_skills' => 0.200,
        'evidence' => 0.150,
        'experience_education' => 0.100,
        'language_soft' => 0.050,
    ],

    'factors' => [
        'verified' => 1.00,
        'claimed' => 0.50,
        'learning' => 0.20,
        'missing' => 0.00,
    ],

    'neutral_score' => 100,

    'language_factors' => [
        'native' => 1.00,
        'fluent' => 1.00,
        'advanced' => 0.50,
        'intermediate' => 0.50,
        'basic' => 0.20,
        'unknown' => 0.50,
    ],

    'text_overlap' => [
        'matched' => 0.40,
        'partial' => 0.15,
    ],

    'queue' => env('MATCHING_QUEUE', 'matching'),
    'processing_timeout' => 300,
    'retry_attempts' => 3,
    'retry_backoff' => [5, 15, 30],

    'max_requirements_per_analysis' => env('MATCHING_MAX_REQUIREMENTS', 100),
    'max_requirement_text_length' => env('MATCHING_MAX_REQUIREMENT_TEXT', 500),

    'insufficient_profile' => [
        'min_profile_completion' => env('MATCHING_MIN_PROFILE_COMPLETION', 50),
        'min_trusted_skills' => env('MATCHING_MIN_TRUSTED_SKILLS', 1),
    ],

    'create_rate_limit' => env('MATCH_CREATE_RATE_LIMIT', '10,1'),
    'recalculate_rate_limit' => env('MATCH_RECALCULATE_RATE_LIMIT', '10,1'),

    'classifier' => [
        'model' => env('MATCHING_CLASSIFIER_MODEL', 'gpt-4o-mini'),
        'timeout' => 120,
        'prompt_version' => env('MATCHING_CLASSIFIER_PROMPT_VERSION', '1.0.0'),
        'max_input_text_length' => env('MATCHING_CLASSIFIER_MAX_TEXT', 30000),
    ],
];

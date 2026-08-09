<?php

return [
    'write_rate_limit' => env('CLARIFICATION_WRITE_RATE_LIMIT', '60,1'),

    'generate_rate_limit' => env('CLARIFICATION_GENERATE_RATE_LIMIT', '10,1'),

    'session' => [
        'max_questions' => env('CLARIFICATION_MAX_QUESTIONS', 3),
    ],

    'assistant' => [
        'enabled' => env('CLARIFICATION_ASSISTANT_ENABLED', false),
        'schema_version' => env('CLARIFICATION_ASSISTANT_SCHEMA_VERSION', '1.0.0'),
        'prompt_version' => env('CLARIFICATION_ASSISTANT_PROMPT_VERSION', '1.0.0'),
        'model' => env('CLARIFICATION_ASSISTANT_MODEL', 'gpt-4o-mini'),
        'timeout' => env('CLARIFICATION_ASSISTANT_TIMEOUT', 120),
        'max_input_text_length' => env('CLARIFICATION_ASSISTANT_MAX_INPUT_TEXT_LENGTH', 30000),
    ],

    'queue' => env('CLARIFICATION_QUEUE', 'clarification'),

    'staleness_job' => [
        'timeout' => 30,
        'tries' => 3,
        'backoff' => [5, 15, 30],
    ],
];

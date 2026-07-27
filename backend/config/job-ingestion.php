<?php

return [
    'max_description_length' => (int) env('JOB_MAX_DESCRIPTION', 100000),
    'min_description_length' => 50,
    'max_source_url_length' => 500,
    'max_label_length' => 255,
    'queue' => env('JOB_INGESTION_QUEUE', 'job-ingestion'),
    'retry_attempts' => 3,
    'retry_backoff' => [10, 30, 60],
    'pipeline_version' => '1.0.0',
    'analysis_schema_version' => '1.0.0',
    'processing_timeout' => 300,
    'create_rate_limit' => env('JOB_CREATE_RATE_LIMIT', '10,1'),
    'retry_rate_limit' => env('JOB_RETRY_RATE_LIMIT', '5,1'),
    'confirm_rate_limit' => env('JOB_CONFIRM_RATE_LIMIT', '5,1'),
    'preview_rate_limit' => env('JOB_PREVIEW_RATE_LIMIT', '10,1'),
    'mutation_rate_limit' => env('JOB_MUTATION_RATE_LIMIT', '60,1'),
    'read_rate_limit' => env('JOB_READ_RATE_LIMIT', '120,1'),
];

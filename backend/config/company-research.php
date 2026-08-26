<?php

return [
    'queue' => env('COMPANY_RESEARCH_QUEUE', 'company-research'),
    'stale_days' => (int) env('COMPANY_RESEARCH_STALE_DAYS', 30),
    'timeout' => (int) env('COMPANY_RESEARCH_TIMEOUT', 60),
    'tries' => (int) env('COMPANY_RESEARCH_TRIES', 3),
    'backoff' => [10, 30, 60],
    'prompt_version' => env('COMPANY_RESEARCH_PROMPT_VERSION', '1.0.0'),
    'schema_version' => env('COMPANY_RESEARCH_SCHEMA_VERSION', '1.0.0'),
    'max_pasted_content_length' => 20000,
    'max_website_url_length' => 500,
    'throttle_create' => env('COMPANY_RESEARCH_CREATE_RATE_LIMIT', '10,1'),
    'throttle_refresh' => env('COMPANY_RESEARCH_REFRESH_RATE_LIMIT', '10,1'),
    'throttle_read' => env('COMPANY_RESEARCH_READ_RATE_LIMIT', '120,1'),
];

<?php

return [
    'timeout' => (int) env('SAFE_FETCH_TIMEOUT', 5),
    'max_bytes' => (int) env('SAFE_FETCH_MAX_BYTES', 1500000),
    'max_redirects' => (int) env('SAFE_FETCH_MAX_REDIRECTS', 3),
    // When true, HTTPS downgrade redirects (https -> http) are blocked, but initial http URLs are still allowed
    // per AGENTS.md "Allow public HTTP(S) only" — set to true to enforce upgrade, false to allow downgrade (not recommended).
    'enforce_https' => (bool) env('SAFE_FETCH_ENFORCE_HTTPS', true),
    'user_agent' => env('SAFE_FETCH_USER_AGENT', 'CareerPilotAI/1.0 (+https://careerpilot.example)'),
    'allowed_content_types' => [
        'text/html',
        'text/plain',
        'application/xhtml+xml',
        'application/json',
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Route Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Local development bypasses Laravel's route throttles by default so long
    | test sessions are not interrupted. Every other environment enforces the
    | configured numeric and named route limits unless explicitly overridden.
    |
    */
    'enabled' => env(
        'RATE_LIMITING_ENABLED',
        env('APP_ENV', 'production') !== 'local',
    ),
];

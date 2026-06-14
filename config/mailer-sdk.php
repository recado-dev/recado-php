<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The fully qualified base URL of the Mailer REST API v1, including the
    | "/api/v1" suffix. A trailing slash is allowed and stripped internally.
    |
    */
    'base_url' => env('MAILER_BASE_URL', 'https://api.mailer.test/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | The project API key (a Sanctum personal access token) used as the Bearer
    | token on every request.
    |
    */
    'token' => env('MAILER_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HTTP transport / resilience
    |--------------------------------------------------------------------------
    |
    | Tuning for the Guzzle client the SDK builds for you. `timeout` is the
    | per-request timeout (seconds). The retry settings drive the automatic
    | exponential-backoff middleware (delays in milliseconds); retries only
    | apply to requests that are safe to repeat (see the SDK README).
    |
    */
    'timeout' => (int) env('MAILER_TIMEOUT', 10),
    'retries' => (int) env('MAILER_RETRIES', 2),
    'retry_base_delay' => (int) env('MAILER_RETRY_BASE_DELAY', 200),
    'retry_max_delay' => (int) env('MAILER_RETRY_MAX_DELAY', 5000),

    /*
    |--------------------------------------------------------------------------
    | Mail transport (MAIL_MAILER=mailer)
    |--------------------------------------------------------------------------
    |
    | Behavior of the "mailer" Laravel mail driver registered by this package.
    |
    | attachments: how to handle a message that carries attachments (the
    |   platform /send API has no attachment support).
    |     'fail'   — throw an UnsupportedFeatureException (the send fails).
    |     'ignore' — log a warning and send the message without the attachments.
    |
    | idempotency: how the per-send Idempotency-Key is derived (a message can
    |   still override it with the X-Mailer-Idempotency-Key header).
    |     'content' — deterministic hash of the content, so a queue retry of the
    |                 same job never duplicates the send.
    |     'random'  — a fresh UUID per send attempt (disables retry dedup).
    |     'off'     — no idempotency key.
    |
    */
    'mail' => [
        'attachments' => env('MAILER_MAIL_ATTACHMENTS', 'fail'),
        'idempotency' => env('MAILER_MAIL_IDEMPOTENCY', 'content'),
    ],
];

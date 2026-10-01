<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI categorization (Phase 8) + insights (Phase 9) + assistant (AI-1)
    |--------------------------------------------------------------------------
    |
    | AI is optional. Missing credentials must not break the app.
    | Credentials are server-side only — never expose API keys to Vite.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', true),

    'provider' => env('AI_PROVIDER', 'openai'),

    'api_key' => env('AI_API_KEY'),

    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    /** OpenAI-compatible chat completions base URL (no trailing slash). */
    'base_url' => rtrim((string) env('AI_BASE_URL', 'https://api.openai.com/v1'), '/'),

    /** HTTP timeout for a single provider request (seconds). */
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 8),

    /** Max categorization requests per authenticated user per minute. */
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 20),

    /** Max descriptions sent in one CSV batch provider call. */
    'csv_batch_size' => (int) env('AI_CSV_BATCH_SIZE', 20),

    /** Minimum description length before suggesting. */
    'min_description_length' => 3,

    /** Corrections required before user-specific learning short-circuits AI. */
    'learning_min_corrections' => 2,

    /** Short-lived cache TTL for identical suggestion requests (seconds). */
    'cache_ttl_seconds' => 120,

    /** Max monthly insight generations per user per minute. */
    'insight_rate_limit_per_minute' => (int) env('AI_INSIGHT_RATE_LIMIT_PER_MINUTE', 5),

    /** Max Campus Coin AI assistant messages per user per minute. */
    'assistant_rate_limit_per_minute' => (int) env('AI_ASSISTANT_RATE_LIMIT_PER_MINUTE', 10),

    /** Max characters for a single assistant user message. */
    'assistant_max_message_length' => (int) env('AI_ASSISTANT_MAX_MESSAGE_LENGTH', 500),

    /*
    |--------------------------------------------------------------------------
    | Google Gemini (AI-1) — used when AI_PROVIDER=gemini
    |--------------------------------------------------------------------------
    |
    | Model is locked to gemini-3.5-flash for this phase.
    | Transport: Laravel HTTP → generativelanguage.googleapis.com/{version}/models/{model}:generateContent
    |
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'api_version' => env('GEMINI_API_VERSION', 'v1beta'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'), '/'),
    ],
];

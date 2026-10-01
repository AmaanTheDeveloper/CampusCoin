<?php

return [
    'csv_import' => [
        /** Maximum upload size in kilobytes (512 KB). */
        'max_size_kb' => 512,
        /** Maximum data rows processed per import (excluding header). */
        'max_rows' => 500,
        /** Temporary disk for uploaded CSV files. */
        'disk' => 'local',
        'directory' => 'csv-imports',
    ],

    'document_scan' => [
        /** Max image upload size in kilobytes (8 MB). */
        'max_size_kb' => (int) env('DOCUMENT_SCAN_MAX_SIZE_KB', 8192),
        'disk' => 'local',
        'directory' => 'document-scans',
        'token_ttl_seconds' => (int) env('DOCUMENT_SCAN_TOKEN_TTL', 900),
        'rate_limit_per_minute' => (int) env('DOCUMENT_SCAN_RATE_LIMIT', 10),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        /** Local/browser QA only — bind FakeDocumentScanExtractor when true (never production). */
        'fake_extractor' => (bool) env('DOCUMENT_SCAN_FAKE', false),
        /** Use local Tesseract OCR when Gemini is rate-limited / unavailable. */
        'fallback_enabled' => (bool) env('DOCUMENT_SCAN_FALLBACK_ENABLED', true),
        'tesseract_binary' => env('DOCUMENT_SCAN_TESSERACT_BINARY', 'tesseract'),
        'tesseract_timeout_seconds' => (int) env('DOCUMENT_SCAN_TESSERACT_TIMEOUT', 20),
        'tesseract_lang' => env('DOCUMENT_SCAN_TESSERACT_LANG', 'eng'),
    ],
];

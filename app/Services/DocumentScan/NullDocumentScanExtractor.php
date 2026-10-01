<?php

namespace App\Services\DocumentScan;

use App\Contracts\DocumentScanExtractor;

/**
 * Safe no-op when Gemini is unavailable — process returns a user-facing failure.
 */
class NullDocumentScanExtractor implements DocumentScanExtractor
{
    public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }
}

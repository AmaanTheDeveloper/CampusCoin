<?php

namespace App\Services\DocumentScan;

use RuntimeException;
use Throwable;

/**
 * Structured Smart Scan failure with a stable error_code for the API/UI.
 */
class DocumentScanException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

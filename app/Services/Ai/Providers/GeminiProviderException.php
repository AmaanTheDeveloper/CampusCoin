<?php

namespace App\Services\Ai\Providers;

use RuntimeException;
use Throwable;

/**
 * Typed Gemini transport/provider failure with a safe category for callers.
 */
class GeminiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category,
        public readonly ?int $retryAfterSeconds = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function isRetryable(): bool
    {
        return in_array($this->category, [
            'rate_limited',
            'high_demand',
            'server_error',
            'timeout',
        ], true);
    }
}

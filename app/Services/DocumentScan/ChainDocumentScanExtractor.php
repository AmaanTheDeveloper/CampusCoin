<?php

namespace App\Services\DocumentScan;

use App\Contracts\DocumentScanExtractor;
use App\Services\Ai\Providers\GeminiProviderException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tries a primary extractor, then a local fallback on retryable provider failures.
 */
class ChainDocumentScanExtractor implements DocumentScanExtractor
{
    private string $lastProcessor = 'none';

    /**
     * Categories where falling back to local OCR is appropriate.
     *
     * @var list<string>
     */
    private const FALLBACK_CATEGORIES = [
        'rate_limited',
        'high_demand',
        'timeout',
        'server_error',
        'transport_failure',
        'not_configured',
        'unauthorized',
        'forbidden',
    ];

    public function __construct(
        private readonly DocumentScanExtractor $primary,
        private readonly ?DocumentScanExtractor $fallback = null,
    ) {}

    public function isAvailable(): bool
    {
        if ($this->primary->isAvailable()) {
            return true;
        }

        return $this->fallback !== null && $this->fallback->isAvailable();
    }

    public function name(): string
    {
        if ($this->lastProcessor !== 'none') {
            return $this->lastProcessor;
        }

        if ($this->primary->isAvailable()) {
            return $this->primary->name();
        }

        if ($this->fallback !== null && $this->fallback->isAvailable()) {
            return $this->fallback->name();
        }

        return 'chain';
    }

    public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
    {
        $primaryError = null;

        if ($this->primary->isAvailable()) {
            try {
                $result = $this->primary->extract($imageBinary, $mimeType, $allowedCategoryNames, $mode);

                if ($result !== null) {
                    $this->lastProcessor = $this->primary->name();
                    Log::info('document_scan.processor', [
                        'processor' => $this->lastProcessor,
                    ]);

                    return $result;
                }
            } catch (GeminiProviderException $e) {
                $primaryError = $e;

                if (! $this->shouldFallback($e)) {
                    throw $this->mapProviderException($e, $mode);
                }

                Log::warning('document_scan.primary_failed', [
                    'processor' => $this->primary->name(),
                    'category' => $e->category,
                ]);
            } catch (DocumentScanException $e) {
                throw $e;
            } catch (Throwable $e) {
                $primaryError = $e;
                Log::warning('document_scan.primary_failed', [
                    'processor' => $this->primary->name(),
                    'category' => 'unknown',
                    'message' => class_basename($e),
                ]);
            }
        }

        if ($this->fallback !== null && $this->fallback->isAvailable()) {
            try {
                $result = $this->fallback->extract($imageBinary, $mimeType, $allowedCategoryNames, $mode);

                if ($result !== null) {
                    $this->lastProcessor = $this->fallback->name();
                    Log::info('document_scan.processor', [
                        'processor' => $this->lastProcessor,
                    ]);

                    return $result;
                }
            } catch (Throwable $e) {
                Log::warning('document_scan.fallback_failed', [
                    'processor' => $this->fallback->name(),
                    'message' => class_basename($e),
                ]);
            }
        }

        if ($primaryError instanceof GeminiProviderException && ! $this->fallbackAvailable()) {
            throw $this->mapProviderException($primaryError, $mode);
        }

        $message = $mode === 'card'
            ? 'We couldn\'t read the card details. Please try again with the card closer and reduce glare.'
            : 'We couldn\'t read this document. Please try another image or add the transaction manually.';

        throw new DocumentScanException($message, 'DOCUMENT_NOT_RECOGNIZED');
    }

    private function fallbackAvailable(): bool
    {
        return $this->fallback !== null && $this->fallback->isAvailable();
    }

    private function shouldFallback(GeminiProviderException $e): bool
    {
        return in_array($e->category, self::FALLBACK_CATEGORIES, true);
    }

    private function mapProviderException(GeminiProviderException $e, ?string $mode): DocumentScanException
    {
        return match ($e->category) {
            'rate_limited', 'high_demand' => new DocumentScanException(
                'Document scanning is temporarily unavailable. Please try again in a few minutes.',
                'OCR_RATE_LIMITED',
                0,
                $e,
            ),
            'timeout' => new DocumentScanException(
                'Document scanning timed out. Please try again.',
                'OCR_TIMEOUT',
                0,
                $e,
            ),
            'unauthorized', 'forbidden', 'not_configured' => new DocumentScanException(
                'Document scanning is temporarily unavailable. Please try again.',
                'OCR_PROVIDER_UNAVAILABLE',
                0,
                $e,
            ),
            'empty_content', 'parse_error' => new DocumentScanException(
                $mode === 'card'
                    ? 'We couldn\'t read the card details. Please try again with the card closer and reduce glare.'
                    : 'We couldn\'t find enough transaction information in this document.',
                $e->category === 'parse_error' ? 'OCR_INVALID_RESPONSE' : 'DOCUMENT_NOT_RECOGNIZED',
                0,
                $e,
            ),
            'bad_request' => new DocumentScanException(
                'This image could not be read. Please try another image.',
                'INVALID_FILE',
                0,
                $e,
            ),
            default => new DocumentScanException(
                'Document scanning is temporarily unavailable. Please try again.',
                'UNKNOWN_PROCESSING_ERROR',
                0,
                $e,
            ),
        };
    }
}

<?php

namespace App\Services\DocumentScan;

use App\Contracts\DocumentScanExtractor;

/**
 * Deterministic extractor for Pest / local tests.
 */
class FakeDocumentScanExtractor implements DocumentScanExtractor
{
    /**
     * @param  array<string, mixed>|null  $fixed
     */
    public function __construct(
        private readonly ?array $fixed = null,
        private readonly bool $available = true,
    ) {}

    public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
    {
        if (! $this->available) {
            return null;
        }

        if ($this->fixed !== null) {
            return SensitiveCardFieldScrubber::scrubExtraction($this->fixed);
        }

        if ($mode === 'card') {
            return [
                'document_type' => 'card',
                'merchant' => null,
                'amount' => null,
                'currency' => null,
                'date' => null,
                'description' => null,
                'suggested_category_name' => null,
                'suggested_type' => null,
                'confidence' => 0.9,
                'field_confidence' => [
                    'card_last4' => 0.95,
                    'card_brand' => 0.9,
                ],
                'needs_review' => [],
                'card_last4' => '4521',
                'cardholder_name' => 'Campus Student',
                'card_brand' => 'visa',
                'card_kind' => 'debit',
                'card_expiry_masked' => '••/••',
                'has_card_number' => true,
                'has_expiry' => true,
                'has_cvv_field' => true,
            ];
        }

        return [
            'document_type' => 'receipt',
            'merchant' => 'Campus Cafe',
            'amount' => '42.00',
            'currency' => 'PKR',
            'date' => '2026-09-10',
            'description' => 'Campus Cafe',
            'suggested_category_name' => $allowedCategoryNames[0] ?? 'Food',
            'suggested_type' => 'expense',
            'confidence' => 0.92,
            'field_confidence' => [
                'merchant' => 0.95,
                'amount' => 0.98,
                'date' => 0.9,
            ],
            'needs_review' => [],
            'card_last4' => null,
            'cardholder_name' => null,
            'card_brand' => null,
            'card_kind' => null,
            'card_expiry_masked' => null,
            'has_card_number' => null,
            'has_expiry' => null,
            'has_cvv_field' => null,
        ];
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function name(): string
    {
        return 'fake';
    }
}

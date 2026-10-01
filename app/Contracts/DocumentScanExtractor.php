<?php

namespace App\Contracts;

/**
 * Extracts structured fields from a receipt/bill/card image.
 *
 * Implementations must never invent high-confidence values; uncertain fields stay null.
 * For cards: never return CVV/PAN — only presence flags + masked fields.
 */
interface DocumentScanExtractor
{
    /**
     * @param  'card'|'document'|null  $mode  Optional client mode hint
     * @return array{
     *     document_type: 'receipt'|'bill'|'card'|'unknown',
     *     merchant: string|null,
     *     amount: string|null,
     *     currency: string|null,
     *     date: string|null,
     *     description: string|null,
     *     suggested_category_name: string|null,
     *     suggested_type: 'income'|'expense'|null,
     *     confidence: float,
     *     field_confidence: array<string, float>,
     *     needs_review: list<string>,
     *     card_last4: string|null,
     *     cardholder_name: string|null,
     *     card_brand: string|null,
     *     card_kind: 'debit'|'credit'|'unknown'|null,
     *     card_expiry_masked: string|null,
     *     has_card_number: bool|null,
     *     has_expiry: bool|null,
     *     has_cvv_field: bool|null
     * }|null
     */
    public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array;

    public function isAvailable(): bool;

    public function name(): string;
}

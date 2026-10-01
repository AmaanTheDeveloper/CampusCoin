<?php

namespace App\Services\DocumentScan;

/**
 * Conservative regex heuristics over OCR plain text. Never invents amounts or card digits.
 */
class DocumentOcrTextParser
{
    /**
     * @param  list<string>  $allowedCategoryNames
     * @param  'card'|'document'|null  $mode
     * @return array<string, mixed>|null
     */
    public function parse(string $text, array $allowedCategoryNames = [], ?string $mode = null): ?array
    {
        $normalized = trim(preg_replace("/\r\n|\r/", "\n", $text) ?? $text);
        if ($normalized === '') {
            return null;
        }

        if ($mode === 'card') {
            return $this->parseCard($normalized);
        }

        return $this->parseDocument($normalized, $allowedCategoryNames);
    }

    /**
     * @param  list<string>  $allowedCategoryNames
     * @return array<string, mixed>
     */
    private function parseDocument(string $text, array $allowedCategoryNames): array
    {
        $lower = strtolower($text);
        $documentType = 'receipt';
        if (str_contains($lower, 'bill') || str_contains($lower, 'invoice') || str_contains($lower, 'utility')) {
            $documentType = 'bill';
        }

        $amount = $this->extractAmount($text);
        $date = $this->extractDate($text);
        $merchant = $this->extractMerchant($text);
        $currency = $this->extractCurrency($text, $amount !== null);
        $category = $this->matchCategory($text, $allowedCategoryNames);

        $needsReview = [];
        $fieldConfidence = [];

        if ($merchant === null) {
            $needsReview[] = 'merchant';
        } else {
            $fieldConfidence['merchant'] = 0.55;
        }

        if ($amount === null) {
            $needsReview[] = 'amount';
        } else {
            $fieldConfidence['amount'] = 0.6;
        }

        if ($date === null) {
            $needsReview[] = 'date';
        } else {
            $fieldConfidence['date'] = 0.55;
        }

        $description = $merchant;
        if ($description === null) {
            $needsReview[] = 'description';
        }

        $confidence = 0.35;
        if ($amount !== null) {
            $confidence += 0.2;
        }
        if ($merchant !== null) {
            $confidence += 0.15;
        }
        if ($date !== null) {
            $confidence += 0.1;
        }
        $confidence = min(0.75, $confidence);

        return SensitiveCardFieldScrubber::scrubExtraction([
            'document_type' => $documentType,
            'merchant' => $merchant,
            'amount' => $amount,
            'currency' => $currency,
            'date' => $date,
            'description' => $description,
            'suggested_category_name' => $category,
            'suggested_type' => $amount !== null ? 'expense' : null,
            'confidence' => $confidence,
            'field_confidence' => $fieldConfidence,
            'needs_review' => array_values(array_unique($needsReview)),
            'card_last4' => null,
            'cardholder_name' => null,
            'card_brand' => null,
            'card_kind' => null,
            'card_expiry_masked' => null,
            'has_card_number' => null,
            'has_expiry' => null,
            'has_cvv_field' => null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseCard(string $text): ?array
    {
        $digitsOnlyRuns = [];
        if (preg_match_all('/(?:\d[ \-]*){13,19}/', $text, $matches)) {
            foreach ($matches[0] as $run) {
                $digits = preg_replace('/\D+/', '', $run) ?? '';
                if (strlen($digits) >= 13 && strlen($digits) <= 19) {
                    $digitsOnlyRuns[] = $digits;
                }
            }
        }

        $last4 = null;
        $hasCardNumber = $digitsOnlyRuns !== [];
        if ($hasCardNumber) {
            $pan = $digitsOnlyRuns[0];
            $last4 = substr($pan, -4);
            // Discard full PAN immediately — never stored beyond this scope.
            unset($pan, $digitsOnlyRuns);
        }

        $hasExpiry = (bool) preg_match('/\b(0[1-9]|1[0-2])\s*[\/\-]\s*(\d{2}|\d{4})\b/', $text);
        // Detect CVV/CVC field presence only; discard any nearby digits.
        $hasCvvField = (bool) preg_match('/\b(cvv|cvc|cid|security\s*code)\b/i', $text);

        $brand = null;
        $lower = strtolower($text);
        foreach (['visa', 'mastercard', 'master card', 'amex', 'american express', 'discover', 'unionpay'] as $name) {
            if (str_contains($lower, $name)) {
                $brand = str_replace(' ', '', $name === 'master card' ? 'mastercard' : $name);
                if ($brand === 'americanexpress') {
                    $brand = 'amex';
                }
                break;
            }
        }

        $cardholder = null;
        if (preg_match('/\b([A-Z][A-Z]+(?:\s+[A-Z][A-Z]+){1,3})\b/', $text, $m)) {
            $candidate = trim($m[1]);
            if (! preg_match('/\b(VISA|MASTER|CARD|DEBIT|CREDIT|VALID|THRU)\b/i', $candidate)) {
                $cardholder = mb_substr($candidate, 0, 80);
            }
        }

        $kind = null;
        if (str_contains($lower, 'debit')) {
            $kind = 'debit';
        } elseif (str_contains($lower, 'credit')) {
            $kind = 'credit';
        } else {
            $kind = 'unknown';
        }

        if (! $hasCardNumber && ! $hasExpiry && ! $hasCvvField) {
            return null;
        }

        $needsReview = [];
        if (! $hasCardNumber) {
            $needsReview[] = 'card_last4';
        }
        if (! $hasExpiry) {
            $needsReview[] = 'card_expiry';
        }
        if (! $hasCvvField) {
            $needsReview[] = 'card_cvv_field';
        }

        return SensitiveCardFieldScrubber::scrubExtraction([
            'document_type' => 'card',
            'merchant' => null,
            'amount' => null,
            'currency' => null,
            'date' => null,
            'description' => null,
            'suggested_category_name' => null,
            'suggested_type' => null,
            'confidence' => $hasCardNumber && $hasExpiry ? 0.65 : 0.4,
            'field_confidence' => array_filter([
                'card_last4' => $last4 !== null ? 0.7 : null,
                'card_brand' => $brand !== null ? 0.55 : null,
            ]),
            'needs_review' => $needsReview,
            'card_last4' => $last4,
            'cardholder_name' => $cardholder,
            'card_brand' => $brand,
            'card_kind' => $kind,
            'card_expiry_masked' => $hasExpiry ? '••/••' : null,
            'has_card_number' => $hasCardNumber,
            'has_expiry' => $hasExpiry,
            'has_cvv_field' => $hasCvvField,
        ]);
    }

    private function extractAmount(string $text): ?string
    {
        $candidates = [];

        $patterns = [
            '/(?:grand\s*)?total(?:\s*(?:due|amount))?[\s:]*[^\d]{0,8}((?:rs\.?|pkr|usd|\$|€)?\s*\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i',
            '/(?:amount\s*(?:due|paid)?)[\s:]*[^\d]{0,8}((?:rs\.?|pkr|usd|\$)?\s*\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i',
            '/(?:rs\.?|pkr|usd|\$)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $raw) {
                    $num = $this->normalizeMoney($raw);
                    if ($num !== null) {
                        $candidates[] = $num;
                    }
                }
            }
        }

        if ($candidates === []) {
            if (preg_match_all('/\b(\d{1,3}(?:,\d{3})+\.\d{2}|\d+\.\d{2})\b/', $text, $loose)) {
                foreach ($loose[1] as $raw) {
                    $num = $this->normalizeMoney($raw);
                    if ($num !== null) {
                        $candidates[] = $num;
                    }
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Prefer the largest plausible total (receipts often list line items then total).
        usort($candidates, fn (string $a, string $b) => (float) $b <=> (float) $a);

        return $candidates[0];
    }

    private function normalizeMoney(string $raw): ?string
    {
        $cleaned = preg_replace('/[^\d.,]/', '', $raw) ?? '';
        $cleaned = str_replace(',', '', $cleaned);
        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        $value = (float) $cleaned;
        if ($value <= 0 || $value > 10_000_000) {
            return null;
        }

        return number_format($value, 2, '.', '');
    }

    private function extractDate(string $text): ?string
    {
        if (preg_match('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $text, $m)) {
            return $this->formatDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})\b/', $text, $m)) {
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2000;
            }
            // Prefer D/M/Y for Campus Coin (PK) context.
            return $this->formatDate($year, (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function formatDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function extractMerchant(string $text): ?string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        $skip = '/\b(total|tax|vat|gst|amount|receipt|invoice|bill|thank|change|cash|card|visa|mastercard|subtotal|qty|item)\b/i';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || mb_strlen($trimmed) < 3) {
                continue;
            }
            if (preg_match($skip, $trimmed)) {
                continue;
            }
            if (preg_match('/^[\d\s.,RsPKR$€-]+$/i', $trimmed)) {
                continue;
            }

            return mb_substr($trimmed, 0, 120);
        }

        return null;
    }

    private function extractCurrency(string $text, bool $hasAmount): ?string
    {
        if (preg_match('/\b(pkr|rs\.?|rupees?)\b/i', $text)) {
            return 'PKR';
        }
        if (preg_match('/\b(usd|\$)\b/i', $text)) {
            return 'USD';
        }

        return $hasAmount ? 'PKR' : null;
    }

    /**
     * @param  list<string>  $allowedCategoryNames
     */
    private function matchCategory(string $text, array $allowedCategoryNames): ?string
    {
        foreach ($allowedCategoryNames as $name) {
            if ($name !== '' && preg_match('/\b'.preg_quote($name, '/').'\b/i', $text)) {
                return $name;
            }
        }

        return null;
    }
}

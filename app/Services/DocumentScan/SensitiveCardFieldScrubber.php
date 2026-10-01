<?php

namespace App\Services\DocumentScan;

/**
 * Defense-in-depth scrubber for payment-card secrets that must never leave the extractor.
 */
class SensitiveCardFieldScrubber
{
    /**
     * Strip PAN-like and CVV-like digit runs from free-text fields.
     * Never invents values; returns null for empty results.
     */
    public static function scrubText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Remove sequences that look like full/partial PANs (13–19 digit groups, optional spaces/dashes).
        $scrubbed = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', '[REDACTED]', $trimmed) ?? $trimmed;

        // Remove standalone 3–4 digit tokens that commonly represent CVV (not last4 in isolation context).
        // Prefer scrubbing labeled CVV patterns first.
        $scrubbed = preg_replace('/\b(?:cvv|cvc|cid|security\s*code)\s*[:#-]?\s*\d{3,4}\b/i', '', $scrubbed) ?? $scrubbed;

        $scrubbed = trim(preg_replace('/\s{2,}/', ' ', $scrubbed) ?? $scrubbed);

        return $scrubbed === '' ? null : mb_substr($scrubbed, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrubExtraction(array $payload): array
    {
        unset(
            $payload['cvv'],
            $payload['cvc'],
            $payload['cid'],
            $payload['security_code'],
            $payload['pan'],
            $payload['card_number'],
            $payload['full_pan'],
            $payload['primary_account_number'],
        );

        foreach (['merchant', 'description', 'cardholder_name', 'card_brand'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = self::scrubText(is_string($payload[$key] ?? null) ? $payload[$key] : null);
            }
        }

        // last4 must remain exactly 4 digits when present.
        if (isset($payload['card_last4']) && is_string($payload['card_last4'])) {
            $digits = preg_replace('/\D+/', '', $payload['card_last4']) ?? '';
            $payload['card_last4'] = strlen($digits) >= 4 ? substr($digits, -4) : null;
        }

        foreach (['has_card_number', 'has_expiry', 'has_cvv_field'] as $flag) {
            if (array_key_exists($flag, $payload) && $payload[$flag] !== null) {
                $payload[$flag] = filter_var($payload[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        return $payload;
    }
}

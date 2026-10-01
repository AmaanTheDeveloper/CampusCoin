<?php

namespace App\Services\DocumentScan;

use Illuminate\Validation\ValidationException;

/**
 * Authoritative post-capture card validation: presence of number, expiry, and CVV-field.
 * Never inspects or stores CVV digits — only boolean presence flags from the extractor.
 */
class CardFieldPresenceValidator
{
    /**
     * @param  array<string, mixed>  $extraction
     *
     * @throws ValidationException
     */
    public function assertValid(array $extraction, ?string $mode = null): void
    {
        $type = (string) ($extraction['document_type'] ?? 'unknown');
        $isCardMode = $mode === 'card' || $type === 'card';

        if (! $isCardMode) {
            return;
        }

        if ($type !== 'card') {
            throw ValidationException::withMessages([
                'card' => 'Invalid card — try again',
            ]);
        }

        $hasNumber = $this->truthy($extraction['has_card_number'] ?? null)
            || (is_string($extraction['card_last4'] ?? null) && preg_match('/^\d{4}$/', (string) $extraction['card_last4']));
        $hasExpiry = $this->truthy($extraction['has_expiry'] ?? null)
            || (is_string($extraction['card_expiry_masked'] ?? null) && trim((string) $extraction['card_expiry_masked']) !== '');
        $hasCvvField = $this->truthy($extraction['has_cvv_field'] ?? null);

        $errors = [];

        if (! $hasNumber) {
            $errors['card_number'] = 'Make sure the card number is visible';
        }

        if (! $hasExpiry) {
            $errors['card_expiry'] = 'Show the expiry date';
        }

        if (! $hasCvvField) {
            $errors['card_cvv_field'] = 'Make sure the card is fully visible';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }

        return false;
    }
}

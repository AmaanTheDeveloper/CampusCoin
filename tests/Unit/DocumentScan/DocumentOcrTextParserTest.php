<?php

use App\Services\DocumentScan\DocumentOcrTextParser;
use App\Services\DocumentScan\SensitiveCardFieldScrubber;

test('ocr text parser extracts receipt amount merchant and date without inventing fields', function () {
    $text = <<<'TXT'
Campus Cafe
Food Court
Date: 2026-09-10
Item Coffee 5.00
Total Rs 42.00
Thank you
TXT;

    $result = (new DocumentOcrTextParser)->parse($text, ['Food', 'Transport'], 'document');

    expect($result)->not->toBeNull()
        ->and($result['document_type'])->toBe('receipt')
        ->and($result['merchant'])->toBe('Campus Cafe')
        ->and($result['amount'])->toBe('42.00')
        ->and($result['currency'])->toBe('PKR')
        ->and($result['date'])->toBe('2026-09-10')
        ->and($result['suggested_type'])->toBe('expense')
        ->and($result['suggested_category_name'])->toBe('Food');
});

test('ocr text parser extracts card last4 presence flags without cvv digits', function () {
    $text = <<<'TXT'
VISA DEBIT
JOHN STUDENT
4532 1199 8877 4521
VALID THRU 09/28
CVV 123
TXT;

    $result = (new DocumentOcrTextParser)->parse($text, [], 'card');

    expect($result)->not->toBeNull()
        ->and($result['document_type'])->toBe('card')
        ->and($result['card_last4'])->toBe('4521')
        ->and($result['has_card_number'])->toBeTrue()
        ->and($result['has_expiry'])->toBeTrue()
        ->and($result['has_cvv_field'])->toBeTrue()
        ->and($result['card_expiry_masked'])->toBe('••/••')
        ->and($result['card_brand'])->toBe('visa')
        ->and(json_encode($result))->not->toContain('123')
        ->and(json_encode($result))->not->toContain('4532119988774521');
});

test('sensitive scrubber still strips pan and cvv-like free text', function () {
    $scrubbed = SensitiveCardFieldScrubber::scrubExtraction([
        'document_type' => 'receipt',
        'merchant' => 'Shop 4111 1111 1111 1111 CVV 999',
        'description' => 'paid with 4111111111111111',
        'amount' => '10.00',
        'currency' => 'PKR',
        'date' => null,
        'suggested_category_name' => null,
        'suggested_type' => 'expense',
        'confidence' => 0.5,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => null,
        'cardholder_name' => null,
        'card_brand' => null,
        'card_kind' => null,
        'card_expiry_masked' => null,
        'has_card_number' => null,
        'has_expiry' => null,
        'has_cvv_field' => null,
        'cvv' => '999',
        'pan' => '4111111111111111',
    ]);

    expect($scrubbed)->not->toHaveKey('cvv')
        ->and($scrubbed)->not->toHaveKey('pan')
        ->and($scrubbed['merchant'])->not->toContain('4111')
        ->and($scrubbed['description'])->toContain('[REDACTED]');
});

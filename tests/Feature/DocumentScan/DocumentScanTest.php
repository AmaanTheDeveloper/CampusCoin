<?php

use App\Contracts\DocumentScanExtractor;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\Providers\GeminiProviderException;
use App\Services\DocumentScan\ChainDocumentScanExtractor;
use App\Services\DocumentScan\FakeDocumentScanExtractor;
use App\Services\DocumentScan\GeminiDocumentScanExtractor;
use App\Services\DocumentScan\SensitiveCardFieldScrubber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedDefaultCategories();
    Storage::fake('local');
});

function bindFakeScanExtractor(?array $fixed = null, bool $available = true): void
{
    app()->instance(DocumentScanExtractor::class, new FakeDocumentScanExtractor($fixed, $available));
}

function tinyPngUpload(string $name = 'receipt.png'): UploadedFile
{
    // Minimal valid 1×1 PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, $png);
}

test('guests cannot process document scans', function () {
    $this->post(route('document-scan.process'), [
        'image' => tinyPngUpload(),
    ])->assertRedirect(route('login'));
});

test('unverified users cannot process document scans', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('process rejects invalid mime types', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('process does not create a transaction', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['scan_token', 'extraction', 'categories', 'today', 'available_balance']);

    expect(Transaction::count())->toBe(0);
});

test('process returns structured extraction from fake extractor', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertOk()
        ->assertJsonPath('extraction.document_type', 'receipt')
        ->assertJsonPath('extraction.merchant', 'Campus Cafe')
        ->assertJsonPath('extraction.amount', '42.00');
});

test('process fails safely when extractor unavailable', function () {
    bindFakeScanExtractor(null, false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error_code', 'OCR_PROVIDER_UNAVAILABLE')
        ->assertJsonValidationErrors(['image']);

    expect(Transaction::count())->toBe(0);
});

test('gemini rate limit returns OCR_RATE_LIMITED when fallback is unavailable', function () {
    config([
        'ai.enabled' => true,
        'ai.gemini.api_key' => 'test-gemini-key',
        'ai.gemini.model' => 'gemini-3.5-flash',
        'ai.gemini.api_version' => 'v1beta',
        'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com',
        'ai.timeout_seconds' => 2,
        'campuscoin.document_scan.fake_extractor' => false,
        'campuscoin.document_scan.fallback_enabled' => false,
    ]);

    app()->forgetInstance(DocumentScanExtractor::class);
    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor(
            new GeminiDocumentScanExtractor,
            new FakeDocumentScanExtractor(null, false),
        ),
    );

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Resource exhausted',
            ],
        ], 429),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ]);

    $response->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error_code', 'OCR_RATE_LIMITED')
        ->assertJsonPath('message', 'Document scanning is temporarily unavailable. Please try again in a few minutes.');

    expect($response->json('errors.image.0'))->not->toContain("We couldn't process this document");

    Http::assertSentCount(3);
    expect(Transaction::count())->toBe(0);
});

test('gemini rate limit falls back to secondary extractor and returns review payload', function () {
    config([
        'ai.enabled' => true,
        'ai.gemini.api_key' => 'test-gemini-key',
        'ai.gemini.model' => 'gemini-3.5-flash',
        'ai.gemini.api_version' => 'v1beta',
        'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com',
        'ai.timeout_seconds' => 2,
        'campuscoin.document_scan.fake_extractor' => false,
        'campuscoin.document_scan.fallback_enabled' => true,
    ]);

    app()->forgetInstance(DocumentScanExtractor::class);
    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor(
            new GeminiDocumentScanExtractor,
            new FakeDocumentScanExtractor,
        ),
    );

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'error' => [
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Resource exhausted',
            ],
        ], 429),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('extraction.merchant', 'Campus Cafe')
        ->assertJsonPath('extraction.amount', '42.00')
        ->assertJsonMissingPath('error_code');

    expect(Transaction::count())->toBe(0);
});

test('gemini timeout falls back to secondary extractor', function () {
    $primary = new class implements DocumentScanExtractor
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'gemini';
        }

        public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
        {
            throw new GeminiProviderException('Gemini provider timed out.', 'timeout');
        }
    };

    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor($primary, new FakeDocumentScanExtractor),
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('extraction.document_type', 'receipt');
});

test('both primary and fallback failure returns structured document error', function () {
    $primary = new class implements DocumentScanExtractor
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'gemini';
        }

        public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
        {
            throw new GeminiProviderException('Gemini rate limited.', 'rate_limited');
        }
    };

    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor($primary, new FakeDocumentScanExtractor(null, false)),
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error_code', 'OCR_RATE_LIMITED');

    expect(Transaction::count())->toBe(0);
});

test('both fail with null fallback returns document not recognized', function () {
    $primary = new class implements DocumentScanExtractor
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'gemini';
        }

        public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
        {
            return null;
        }
    };

    $fallback = new class implements DocumentScanExtractor
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'tesseract';
        }

        public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
        {
            return null;
        }
    };

    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor($primary, $fallback),
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error_code', 'DOCUMENT_NOT_RECOGNIZED')
        ->assertJsonPath('message', "We couldn't read this document. Please try another image or add the transaction manually.");

    expect(Transaction::count())->toBe(0);
});

test('primary success does not invoke fallback extractor', function () {
    $counter = (object) ['calls' => 0];
    $fallback = new class($counter) implements DocumentScanExtractor
    {
        public function __construct(private object $counter) {}

        public function isAvailable(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'tesseract';
        }

        public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
        {
            $this->counter->calls++;

            return null;
        }
    };

    app()->instance(
        DocumentScanExtractor::class,
        new ChainDocumentScanExtractor(new FakeDocumentScanExtractor, $fallback),
    );

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertOk()
        ->assertJsonPath('extraction.merchant', 'Campus Cafe');

    expect($counter->calls)->toBe(0);
});

test('confirm creates owned transaction from scan token', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();
    fundUser($user);

    $process = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertOk();

    $token = $process->json('scan_token');
    $categoryId = defaultCategoryId('expense', 'Food');

    $this->actingAs($user)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => $categoryId,
            'amount' => '42.00',
            'description' => 'Campus Cafe',
            'transaction_date' => '2026-09-10',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'Transaction added successfully');

    $transaction = Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->type)->toBe(TransactionType::Expense);
    expect((string) $transaction->amount)->toBe('42.00');
    expect($transaction->description)->toBe('Campus Cafe');
    expect($transaction->category_id)->toBe($categoryId);
});

test('card document type cannot be confirmed as a transaction', function () {
    bindFakeScanExtractor([
        'document_type' => 'card',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => null,
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.8,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => '4242',
        'cardholder_name' => 'TEST USER',
        'card_brand' => 'visa',
        'card_kind' => 'debit',
        'card_expiry_masked' => '••/••',
        'has_card_number' => true,
        'has_expiry' => true,
        'has_cvv_field' => true,
    ]);

    $user = User::factory()->create();
    fundUser($user);

    $token = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'card',
        ])
        ->assertOk()
        ->json('scan_token');

    $this->actingAs($user)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '10.00',
            'description' => 'Card attempt',
            'transaction_date' => '2026-09-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['document_type']);

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(0);
});

test('cross-user scan token is rejected', function () {
    bindFakeScanExtractor();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    fundUser($intruder);

    $token = $this->actingAs($owner)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertOk()
        ->json('scan_token');

    $this->actingAs($intruder)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '42.00',
            'description' => 'Campus Cafe',
            'transaction_date' => '2026-09-10',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['scan_token']);

    expect(Transaction::where('user_id', $intruder->id)->where('type', 'expense')->count())->toBe(0);
    expect(Cache::get('document-scan:'.$owner->id.':'.$token))->not->toBeNull();
});

test('confirm soft-warns on possible duplicate then creates when acknowledged', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();
    fundUser($user);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '42.00',
        'description' => 'Campus Cafe',
        'transaction_date' => '2026-09-10',
    ]);

    $token = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
        ])
        ->assertOk()
        ->json('scan_token');

    $this->actingAs($user)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '42.00',
            'description' => 'Campus Cafe',
            'transaction_date' => '2026-09-10',
        ])
        ->assertStatus(409)
        ->assertJsonPath('possible_duplicate', true);

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);

    $this->actingAs($user)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '42.00',
            'description' => 'Campus Cafe',
            'transaction_date' => '2026-09-10',
            'acknowledge_duplicate' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(2);
});

test('process with mode card returns card fields and creates no transaction', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('card.png'),
            'mode' => 'card',
        ])
        ->assertOk()
        ->assertJsonPath('extraction.document_type', 'card')
        ->assertJsonPath('extraction.card_last4', '4521')
        ->assertJsonPath('extraction.card_kind', 'debit')
        ->assertJsonPath('extraction.card_expiry_masked', '••/••')
        ->assertJsonMissingPath('extraction.cvv');

    expect(Transaction::count())->toBe(0);
});

test('process with mode document still extracts receipt for confirm', function () {
    bindFakeScanExtractor();
    $user = User::factory()->create();
    fundUser($user);

    $token = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload(),
            'mode' => 'document',
        ])
        ->assertOk()
        ->assertJsonPath('extraction.document_type', 'receipt')
        ->json('scan_token');

    $this->actingAs($user)
        ->postJson(route('document-scan.confirm'), [
            'scan_token' => $token,
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '42.00',
            'description' => 'Campus Cafe',
            'transaction_date' => '2026-09-10',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
});

test('sensitive scrubber strips pan and cvv-like text from free fields', function () {
    $scrubbed = SensitiveCardFieldScrubber::scrubExtraction([
        'document_type' => 'card',
        'description' => 'CVV 123 card 4111 1111 1111 1111 holder',
        'cardholder_name' => 'Jane Doe CVV: 999',
        'merchant' => null,
        'card_last4' => '1111',
        'cvv' => '123',
        'pan' => '4111111111111111',
    ]);

    expect($scrubbed)->not->toHaveKey('cvv');
    expect($scrubbed)->not->toHaveKey('pan');
    expect($scrubbed['card_last4'])->toBe('1111');
    expect($scrubbed['description'])->not->toContain('4111');
    expect($scrubbed['description'])->not->toMatch('/\bcvv\b.*\d{3}/i');
    expect($scrubbed['cardholder_name'])->not->toMatch('/\d{3}/');
});

test('process scrubs pan-like values from fixed fake extraction', function () {
    bindFakeScanExtractor([
        'document_type' => 'card',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => '4111111111111111 CVV 321',
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.8,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => '1111',
        'cardholder_name' => 'TEST USER',
        'card_brand' => 'visa',
        'card_kind' => 'credit',
        'card_expiry_masked' => '••/••',
        'has_card_number' => true,
        'has_expiry' => true,
        'has_cvv_field' => true,
        'cvv' => '321',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('card.png'),
            'mode' => 'card',
        ])
        ->assertOk();

    expect($response->json('extraction'))->not->toHaveKey('cvv');
    expect((string) $response->json('extraction.description'))->not->toContain('4111');
    expect($response->json('extraction.has_cvv_field'))->toBeTrue();
});

test('process rejects card missing expiry presence', function () {
    bindFakeScanExtractor([
        'document_type' => 'card',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => null,
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.8,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => '4521',
        'cardholder_name' => 'Test',
        'card_brand' => 'visa',
        'card_kind' => 'debit',
        'card_expiry_masked' => null,
        'has_card_number' => true,
        'has_expiry' => false,
        'has_cvv_field' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('card.png'),
            'mode' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['card_expiry']);

    expect(Transaction::count())->toBe(0);
});

test('process rejects card missing cvv field presence', function () {
    bindFakeScanExtractor([
        'document_type' => 'card',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => null,
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.8,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => '4521',
        'cardholder_name' => 'Test',
        'card_brand' => 'visa',
        'card_kind' => 'debit',
        'card_expiry_masked' => '••/••',
        'has_card_number' => true,
        'has_expiry' => true,
        'has_cvv_field' => false,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('card.png'),
            'mode' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['card_cvv_field']);
});

test('process rejects card missing card number presence', function () {
    bindFakeScanExtractor([
        'document_type' => 'card',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => null,
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.8,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => null,
        'cardholder_name' => 'Test',
        'card_brand' => 'visa',
        'card_kind' => 'debit',
        'card_expiry_masked' => '••/••',
        'has_card_number' => false,
        'has_expiry' => true,
        'has_cvv_field' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('card.png'),
            'mode' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['card_number']);
});

test('process rejects non-card document_type in card mode', function () {
    bindFakeScanExtractor([
        'document_type' => 'receipt',
        'merchant' => 'Cafe',
        'amount' => '10.00',
        'currency' => 'PKR',
        'date' => '2026-09-10',
        'description' => 'Lunch',
        'suggested_category_name' => 'Food',
        'suggested_type' => 'expense',
        'confidence' => 0.9,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => null,
        'cardholder_name' => null,
        'card_brand' => null,
        'card_kind' => null,
        'card_expiry_masked' => null,
        'has_card_number' => false,
        'has_expiry' => false,
        'has_cvv_field' => false,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('not-a-card.png'),
            'mode' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['card']);

    expect(Transaction::count())->toBe(0);
});

test('process rejects unknown document_type in card mode without coercion', function () {
    bindFakeScanExtractor([
        'document_type' => 'unknown',
        'merchant' => null,
        'amount' => null,
        'currency' => null,
        'date' => null,
        'description' => null,
        'suggested_category_name' => null,
        'suggested_type' => null,
        'confidence' => 0.2,
        'field_confidence' => [],
        'needs_review' => [],
        'card_last4' => '4521',
        'cardholder_name' => 'Test',
        'card_brand' => 'visa',
        'card_kind' => 'debit',
        'card_expiry_masked' => '••/••',
        'has_card_number' => true,
        'has_expiry' => true,
        'has_cvv_field' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('document-scan.process'), [
            'image' => tinyPngUpload('unknown.png'),
            'mode' => 'card',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['card']);
});

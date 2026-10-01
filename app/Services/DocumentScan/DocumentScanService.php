<?php

namespace App\Services\DocumentScan;

use App\Contracts\DocumentScanExtractor;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\BudgetService;
use App\Services\Intelligence\DuplicateDetectionService;
use App\Services\Intelligence\ExpenseAlertBridge;
use App\Services\Intelligence\TransactionSignalService;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentScanService
{
    public function __construct(
        private readonly DocumentScanExtractor $extractor,
        private readonly DuplicateDetectionService $duplicates,
        private readonly TransactionSignalService $signals,
        private readonly IncomePlanAlertService $incomePlans,
        private readonly ExpenseAlertBridge $expenseAlerts,
        private readonly BudgetService $budgets,
        private readonly CardFieldPresenceValidator $cardPresence = new CardFieldPresenceValidator,
    ) {}

    public function isAvailable(): bool
    {
        return $this->extractor->isAvailable();
    }

    /**
     * Validate, extract, cache review payload. Never creates a transaction.
     *
     * @param  'card'|'document'|null  $mode
     * @return array{
     *     scan_token: string,
     *     extraction: array<string, mixed>,
     *     categories: list<array{id: int, name: string, type: string, is_default: bool}>,
     *     today: string,
     *     available_balance: string
     * }
     */
    public function process(User $user, UploadedFile $file, ?string $mode = null): array
    {
        $this->assertRateLimit($user);

        $mime = $this->detectMime($file);
        $allowed = (array) config('campuscoin.document_scan.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'image' => 'Upload a JPG, PNG, or WebP image.',
            ]);
        }

        if (! $this->extractor->isAvailable()) {
            throw new DocumentScanException(
                'Smart Scan is temporarily unavailable. Add the transaction manually instead.',
                'OCR_PROVIDER_UNAVAILABLE',
            );
        }

        $directory = trim((string) config('campuscoin.document_scan.directory', 'document-scans'), '/');
        $relativePath = $directory.'/'.$user->id.'/'.Str::uuid()->toString().'.'.$this->extensionForMime($mime);
        $disk = (string) config('campuscoin.document_scan.disk', 'local');
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'image' => 'Could not read the uploaded image.',
            ]);
        }

        Storage::disk($disk)->put($relativePath, $binary);

        try {
            $categoryNames = CategoryAccess::forUser($user, TransactionType::Expense)
                ->pluck('name')
                ->map(fn ($n) => (string) $n)
                ->values()
                ->all();

            try {
                $extraction = $this->extractor->extract($binary, $mime, $categoryNames, $mode);
            } catch (DocumentScanException $e) {
                throw $e;
            } catch (Throwable) {
                $message = $mode === 'card'
                    ? 'We couldn\'t read the card details. Please try again with the card closer and reduce glare.'
                    : 'We couldn\'t read this document. Please try another image or add the transaction manually.';

                throw new DocumentScanException($message, 'DOCUMENT_NOT_RECOGNIZED');
            }

            if ($extraction === null) {
                $message = $mode === 'card'
                    ? 'We couldn\'t read the card details. Please try again with the card closer and reduce glare.'
                    : 'We couldn\'t read this document. Please try another image or add the transaction manually.';

                throw new DocumentScanException($message, 'DOCUMENT_NOT_RECOGNIZED');
            }

            $extraction = SensitiveCardFieldScrubber::scrubExtraction($extraction);
            $this->cardPresence->assertValid($extraction, $mode);
            $extraction = $this->enrichWithCategoryId($user, $extraction);

            $token = Str::uuid()->toString();
            $ttl = max(60, (int) config('campuscoin.document_scan.token_ttl_seconds', 900));

            Cache::put($this->cacheKey($user->id, $token), [
                'user_id' => $user->id,
                'extraction' => $extraction,
                'mode' => $mode,
                'created_at' => now()->toIso8601String(),
            ], $ttl);

            return [
                'scan_token' => $token,
                'extraction' => $extraction,
                'categories' => CategoryAccess::optionsForUser($user),
                'today' => AvailableBalance::today(),
                'available_balance' => AvailableBalance::forUser($user),
            ];
        } finally {
            Storage::disk($disk)->delete($relativePath);
        }
    }

    /**
     * Confirm reviewed fields and create a transaction (same semantics as TransactionController::store).
     *
     * @param  array<string, mixed>  $fields
     * @return array{
     *     transaction_id: int,
     *     possible_duplicate?: bool,
     *     budget_overrun?: bool,
     *     budget_impact?: array<string, mixed>|null,
     *     matches?: list<array<string, mixed>>,
     *     message?: string,
     *     spending_alert?: array<string, mixed>|null
     * }
     */
    public function confirm(User $user, string $scanToken, array $fields): array
    {
        $payload = Cache::get($this->cacheKey($user->id, $scanToken));

        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== $user->id) {
            throw ValidationException::withMessages([
                'scan_token' => 'This scan expired. Capture or upload the document again.',
            ]);
        }

        $extraction = is_array($payload['extraction'] ?? null) ? $payload['extraction'] : [];
        $documentType = (string) ($extraction['document_type'] ?? 'unknown');

        if ($documentType === 'card') {
            throw ValidationException::withMessages([
                'document_type' => 'Payment cards cannot be saved as transactions. Only receipts and bills can.',
            ]);
        }

        $type = TransactionType::from((string) $fields['type']);
        $amount = MoneyMath::normalize($fields['amount']);
        $description = isset($fields['description']) && is_string($fields['description'])
            ? (trim($fields['description']) ?: null)
            : null;
        $date = (string) $fields['transaction_date'];
        $categoryId = (int) $fields['category_id'];
        $acknowledgeDuplicate = (bool) ($fields['acknowledge_duplicate'] ?? false);
        $confirmBudgetOverrun = (bool) ($fields['confirm_budget_overrun'] ?? false);

        if ($type === TransactionType::Expense && ! $acknowledgeDuplicate) {
            $possible = $this->findPossibleDuplicates($user, $amount, $date, $description);
            if ($possible !== []) {
                return [
                    'possible_duplicate' => true,
                    'message' => 'A similar expense may already exist. Confirm to add anyway.',
                    'matches' => $possible,
                    'transaction_id' => 0,
                ];
            }
        }

        if ($type === TransactionType::Expense) {
            $available = AvailableBalance::forUser($user);
            if (MoneyMath::compare($amount, $available) > 0) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Insufficient Balance: Your available balance is Rs %s, but this expense is Rs %s. Please reduce the expense amount or add income first.',
                        number_format((float) $available, 2),
                        number_format((float) $amount, 2),
                    ),
                ]);
            }

            if (! $confirmBudgetOverrun) {
                $impact = $this->budgets->evaluateExpenseImpact(
                    $user,
                    $categoryId,
                    $amount,
                    $date,
                    null,
                    $available,
                );

                if ($impact !== null && ($impact['exceeds'] ?? false)) {
                    return [
                        'budget_overrun' => true,
                        'budget_impact' => $impact,
                        'message' => 'Budget limit exceeded. Confirm to continue or review your budget.',
                        'transaction_id' => 0,
                    ];
                }
            }
        }

        $transaction = $user->transactions()->create([
            'type' => $type,
            'category_id' => $categoryId,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date,
        ]);

        $spendingAlert = $this->signals->evaluateTransaction($user, $transaction);

        if ($transaction->type === TransactionType::Income) {
            $this->incomePlans->handle($user, $transaction);
        } else {
            $this->expenseAlerts->handle($user, $transaction, $spendingAlert);
        }

        Cache::forget($this->cacheKey($user->id, $scanToken));

        return [
            'transaction_id' => $transaction->id,
            'possible_duplicate' => false,
            'budget_overrun' => false,
            'message' => 'Transaction added successfully',
            'spending_alert' => $spendingAlert,
        ];
    }

    /**
     * Reject cache payloads belonging to another user (defense in depth).
     */
    public function peekForUser(User $user, string $scanToken): ?array
    {
        $payload = Cache::get($this->cacheKey($user->id, $scanToken));

        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== $user->id) {
            return null;
        }

        return $payload;
    }

    private function assertRateLimit(User $user): void
    {
        $limit = (int) config('campuscoin.document_scan.rate_limit_per_minute', 10);
        $rateKey = 'document-scan:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            throw ValidationException::withMessages([
                'image' => 'Too many scans. Please wait a minute and try again.',
            ]);
        }

        RateLimiter::hit($rateKey, 60);
    }

    private function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => 'Could not read the uploaded image.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : 'application/octet-stream';
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function cacheKey(int $userId, string $token): string
    {
        return 'document-scan:'.$userId.':'.$token;
    }

    /**
     * @param  array<string, mixed>  $extraction
     * @return array<string, mixed>
     */
    private function enrichWithCategoryId(User $user, array $extraction): array
    {
        $name = $extraction['suggested_category_name'] ?? null;
        $typeValue = $extraction['suggested_type'] ?? 'expense';
        $type = in_array($typeValue, ['income', 'expense'], true)
            ? TransactionType::from($typeValue)
            : TransactionType::Expense;

        $extraction['suggested_category_id'] = null;
        $extraction['suggested_type'] = $type->value;

        if (! is_string($name) || $name === '') {
            return $extraction;
        }

        $category = CategoryAccess::forUser($user, $type)
            ->first(fn ($c) => strcasecmp((string) $c->name, $name) === 0);

        if ($category !== null) {
            $extraction['suggested_category_id'] = $category->id;
            $extraction['suggested_category_name'] = $category->name;
        }

        return $extraction;
    }

    /**
     * Soft pre-create duplicate check (same amount + date + similar description).
     *
     * @return list<array{id: int, description: string|null, amount: string, transaction_date: string}>
     */
    private function findPossibleDuplicates(User $user, string $amount, string $date, ?string $description): array
    {
        $normalized = $this->duplicates->normalizeDescription($description);
        if ($normalized === '') {
            return [];
        }

        $windowDays = max(0, (int) config('intelligence.duplicate_date_window_days', 0));
        $carbon = Carbon::parse($date);
        $from = $carbon->copy()->subDays($windowDays)->toDateString();
        $to = $carbon->copy()->addDays($windowDays)->toDateString();

        $candidates = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->where('amount', $amount)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->orderBy('id')
            ->limit(10)
            ->get();

        return $candidates
            ->filter(fn (Transaction $t) => $this->duplicates->normalizeDescription($t->description) === $normalized)
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'description' => $t->description,
                'amount' => (string) $t->amount,
                'transaction_date' => $t->transaction_date->toDateString(),
            ])
            ->values()
            ->all();
    }
}

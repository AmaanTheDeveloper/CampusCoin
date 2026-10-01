<?php

namespace App\Services\Ai;

use App\Contracts\AiCategorizationProvider;
use App\Enums\TransactionType;
use App\Models\AiCategoryFeedback;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryAccess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class AiCategorizationService
{
    public function __construct(
        private readonly AiCategorizationProvider $provider,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) config('ai.enabled', true) && $this->provider->isAvailable();
    }

    /**
     * @return array{
     *     available: bool,
     *     suggestion: array{
     *         category_id: int,
     *         category_name: string,
     *         confidence: string,
     *         reason: string,
     *         source: string
     *     }|null,
     *     error: string|null
     * }
     */
    public function suggestForUser(User $user, TransactionType $type, string $description): array
    {
        $description = trim($description);
        $minLength = (int) config('ai.min_description_length', 3);

        if (mb_strlen($description) < $minLength) {
            return [
                'available' => $this->isAvailable(),
                'suggestion' => null,
                'error' => null,
            ];
        }

        $allowed = $this->allowedCategoryMaps($user, $type);

        if ($allowed['by_id'] === []) {
            return [
                'available' => $this->isAvailable(),
                'suggestion' => null,
                'error' => 'No categories available for this transaction type.',
            ];
        }

        if ($learned = $this->learnedSuggestion($user, $type, $description, $allowed['by_id'])) {
            return [
                'available' => true,
                'suggestion' => $learned,
                'error' => null,
            ];
        }

        if (! $this->isAvailable()) {
            return [
                'available' => false,
                'suggestion' => null,
                'error' => 'Category suggestion is temporarily unavailable. You can choose a category manually.',
            ];
        }

        $rateKey = 'ai-categorize:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateKey, (int) config('ai.rate_limit_per_minute', 20))) {
            return [
                'available' => true,
                'suggestion' => null,
                'error' => 'Too many category suggestions. Please wait a moment or choose a category manually.',
            ];
        }

        RateLimiter::hit($rateKey, 60);

        $cacheKey = $this->cacheKey($user, $type, $description, $allowed['names']);
        $ttl = (int) config('ai.cache_ttl_seconds', 120);

        try {
            if ($ttl <= 0) {
                $raw = $this->provider->suggest($user, $type, $description, $allowed['list']);
            } else {
                $raw = Cache::remember($cacheKey, $ttl, function () use ($user, $type, $description, $allowed) {
                    return $this->provider->suggest($user, $type, $description, $allowed['list']);
                });
            }
        } catch (Throwable $e) {
            Log::warning('ai.categorization.service_failure', [
                'provider' => $this->provider->name(),
                'error' => class_basename($e),
            ]);

            return [
                'available' => true,
                'suggestion' => null,
                'error' => 'Category suggestion is temporarily unavailable. You can choose a category manually.',
            ];
        }

        $validated = $this->validateProviderSuggestion($raw, $allowed['by_name'], $allowed['by_id'], 'ai');

        return [
            'available' => true,
            'suggestion' => $validated,
            'error' => $validated === null
                ? 'Category suggestion is temporarily unavailable. You can choose a category manually.'
                : null,
        ];
    }

    /**
     * Attach AI suggestions to CSV preview rows that lack a deterministic category match.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function enrichCsvRows(User $user, array $rows): array
    {
        $needsExpense = [];
        $needsIncome = [];

        foreach ($rows as $index => $row) {
            $rows[$index]['ai_suggestion'] = null;
            $rows[$index]['final_category_id'] = $row['category_id'] ?? null;

            if (($row['status'] ?? '') === 'error') {
                continue;
            }

            if (! empty($row['category_id'])) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));
            if (mb_strlen($description) < (int) config('ai.min_description_length', 3)) {
                continue;
            }

            $type = TransactionType::tryFrom((string) ($row['type'] ?? ''));
            if ($type === null) {
                continue;
            }

            if ($learned = $this->learnedSuggestion(
                $user,
                $type,
                $description,
                $this->allowedCategoryMaps($user, $type)['by_id']
            )) {
                $rows[$index]['ai_suggestion'] = $learned;

                continue;
            }

            if ($type === TransactionType::Expense) {
                $needsExpense[] = ['index' => $index, 'description' => $description];
            } else {
                $needsIncome[] = ['index' => $index, 'description' => $description];
            }
        }

        if (! $this->isAvailable()) {
            return $rows;
        }

        $rows = $this->applyBatchSuggestions($user, TransactionType::Expense, $needsExpense, $rows);
        $rows = $this->applyBatchSuggestions($user, TransactionType::Income, $needsIncome, $rows);

        return $rows;
    }

    /**
     * Record accept/correction feedback. Never stores prompts or provider payloads.
     */
    public function recordFeedback(
        User $user,
        TransactionType $type,
        ?string $description,
        ?int $suggestedCategoryId,
        int $selectedCategoryId,
        bool $wasAccepted,
    ): void {
        if ($suggestedCategoryId === null) {
            return;
        }

        $allowed = CategoryAccess::forUser($user, $type)->pluck('id')->all();

        if (! in_array($suggestedCategoryId, $allowed, true) || ! in_array($selectedCategoryId, $allowed, true)) {
            return;
        }

        AiCategoryFeedback::query()->create([
            'user_id' => $user->id,
            'transaction_type' => $type->value,
            'description' => Str::limit(trim((string) $description), 255, ''),
            'normalized_description' => $this->normalizeDescription((string) $description),
            'suggested_category_id' => $suggestedCategoryId,
            'selected_category_id' => $selectedCategoryId,
            'was_accepted' => $wasAccepted,
        ]);
    }

    /**
     * @param  list<array{index: int, description: string}>  $items
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyBatchSuggestions(User $user, TransactionType $type, array $items, array $rows): array
    {
        if ($items === []) {
            return $rows;
        }

        $allowed = $this->allowedCategoryMaps($user, $type);
        $batchSize = max(1, (int) config('ai.csv_batch_size', 20));

        foreach (array_chunk($items, $batchSize) as $chunk) {
            try {
                $results = $this->provider->suggestBatch($user, $type, $chunk, $allowed['list']);
            } catch (Throwable) {
                // Leave rows without AI suggestion; import remains usable.
                continue;
            }

            foreach ($chunk as $item) {
                $raw = $results[$item['index']] ?? null;
                $validated = $this->validateProviderSuggestion($raw, $allowed['by_name'], $allowed['by_id'], 'ai');
                if ($validated !== null) {
                    $rows[$item['index']]['ai_suggestion'] = $validated;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $byId
     * @return array{category_id: int, category_name: string, confidence: string, reason: string, source: string}|null
     */
    private function learnedSuggestion(User $user, TransactionType $type, string $description, array $byId): ?array
    {
        $normalized = $this->normalizeDescription($description);
        if ($normalized === '') {
            return null;
        }

        $min = (int) config('ai.learning_min_corrections', 2);

        $corrections = AiCategoryFeedback::query()
            ->where('user_id', $user->id)
            ->where('transaction_type', $type->value)
            ->where('normalized_description', $normalized)
            ->where('was_accepted', false)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($corrections->count() < $min) {
            return null;
        }

        $selectedId = (int) $corrections->first()->selected_category_id;
        $matching = $corrections->where('selected_category_id', $selectedId)->count();

        if ($matching < $min || ! isset($byId[$selectedId])) {
            return null;
        }

        return [
            'category_id' => $selectedId,
            'category_name' => $byId[$selectedId]['name'],
            'confidence' => 'high',
            'reason' => 'Based on your previous corrections for similar descriptions.',
            'source' => 'learned',
        ];
    }

    /**
     * @param  array{category_name?: mixed, confidence?: mixed, reason?: mixed}|null  $raw
     * @param  array<string, array{id: int, name: string}>  $byName
     * @param  array<int, array{id: int, name: string}>  $byId
     * @return array{category_id: int, category_name: string, confidence: string, reason: string, source: string}|null
     */
    private function validateProviderSuggestion(?array $raw, array $byName, array $byId, string $source): ?array
    {
        if ($raw === null) {
            return null;
        }

        $name = trim((string) ($raw['category_name'] ?? ''));
        $key = mb_strtolower($name);

        if ($name === '' || ! isset($byName[$key])) {
            return null;
        }

        $category = $byName[$key];
        if (! isset($byId[$category['id']])) {
            return null;
        }

        $confidence = strtolower(trim((string) ($raw['confidence'] ?? 'low')));
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'low';
        }

        return [
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'confidence' => $confidence,
            'reason' => mb_substr(strip_tags((string) ($raw['reason'] ?? 'Suggested match')), 0, 200),
            'source' => $source,
        ];
    }

    /**
     * @return array{
     *     list: list<array{id: int, name: string}>,
     *     by_id: array<int, array{id: int, name: string}>,
     *     by_name: array<string, array{id: int, name: string}>,
     *     names: list<string>
     * }
     */
    private function allowedCategoryMaps(User $user, TransactionType $type): array
    {
        $categories = CategoryAccess::forUser($user, $type);
        $list = [];
        $byId = [];
        $byName = [];
        $names = [];

        /** @var Category $category */
        foreach ($categories as $category) {
            $item = ['id' => $category->id, 'name' => $category->name];
            $list[] = $item;
            $byId[$category->id] = $item;
            $byName[mb_strtolower($category->name)] = $item;
            $names[] = mb_strtolower($category->name);
        }

        sort($names);

        return [
            'list' => $list,
            'by_id' => $byId,
            'by_name' => $byName,
            'names' => $names,
        ];
    }

    private function normalizeDescription(string $description): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $description) ?? ''));

        return mb_substr($normalized, 0, 255);
    }

    /**
     * @param  list<string>  $categoryNames
     */
    private function cacheKey(User $user, TransactionType $type, string $description, array $categoryNames): string
    {
        return 'ai-cat:'.$user->id.':'.$type->value.':'.hash('sha256', $this->normalizeDescription($description).'|'.implode(',', $categoryNames));
    }
}

<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiCategorizationProvider;
use App\Enums\TransactionType;
use App\Models\User;
use Throwable;

/**
 * Test double — never calls a remote provider.
 */
class FakeCategorizationProvider implements AiCategorizationProvider
{
    /** @var array{category_name: string, confidence: string, reason: string}|null|callable|null */
    public mixed $nextSuggestion = null;

    /** @var array<int, array{category_name: string, confidence: string, reason: string}>|callable */
    public mixed $batchSuggestions = [];

    public bool $available = true;

    public int $suggestCalls = 0;

    public int $batchCalls = 0;

    public ?Throwable $throwOnSuggest = null;

    public function suggest(User $user, TransactionType $type, string $description, array $allowedCategories): ?array
    {
        $this->suggestCalls++;

        if ($this->throwOnSuggest !== null) {
            throw $this->throwOnSuggest;
        }

        if (is_callable($this->nextSuggestion)) {
            return ($this->nextSuggestion)($user, $type, $description, $allowedCategories);
        }

        return $this->nextSuggestion;
    }

    public function suggestBatch(User $user, TransactionType $type, array $items, array $allowedCategories): array
    {
        $this->batchCalls++;

        if ($this->throwOnSuggest !== null) {
            throw $this->throwOnSuggest;
        }

        if (is_callable($this->batchSuggestions)) {
            return ($this->batchSuggestions)($user, $type, $items, $allowedCategories);
        }

        return is_array($this->batchSuggestions) ? $this->batchSuggestions : [];
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

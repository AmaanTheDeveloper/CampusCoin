<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiCategorizationProvider;
use App\Enums\TransactionType;
use App\Models\User;

/**
 * Used when AI is disabled or no API key is configured.
 */
class NullCategorizationProvider implements AiCategorizationProvider
{
    public function suggest(User $user, TransactionType $type, string $description, array $allowedCategories): ?array
    {
        return null;
    }

    public function suggestBatch(User $user, TransactionType $type, array $items, array $allowedCategories): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }
}

<?php

namespace App\Contracts;

use App\Enums\TransactionType;
use App\Models\User;

interface AiCategorizationProvider
{
    /**
     * Suggest a category name from the allowed list.
     *
     * @param  list<array{id: int, name: string}>  $allowedCategories
     * @return array{category_name: string, confidence: string, reason: string}|null
     */
    public function suggest(User $user, TransactionType $type, string $description, array $allowedCategories): ?array;

    /**
     * Suggest categories for multiple descriptions in one provider call.
     *
     * @param  list<array{index: int, description: string}>  $items
     * @param  list<array{id: int, name: string}>  $allowedCategories
     * @return array<int, array{category_name: string, confidence: string, reason: string}>
     */
    public function suggestBatch(User $user, TransactionType $type, array $items, array $allowedCategories): array;

    public function isAvailable(): bool;

    public function name(): string;
}

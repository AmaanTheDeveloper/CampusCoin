<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Validator;

class CategoryAccess
{
    /**
     * @return Collection<int, Category>
     */
    public static function forUser(User $user, ?TransactionType $type = null): Collection
    {
        $query = Category::query()
            ->accessibleTo($user)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query->get();
    }

    /**
     * @return list<array{id: int, name: string, type: string, is_default: bool}>
     */
    public static function optionsForUser(User $user, ?TransactionType $type = null): array
    {
        return self::forUser($user, $type)
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'is_default' => $category->is_default,
            ])
            ->values()
            ->all();
    }

    public static function assertAccessible(User $user, int $categoryId, TransactionType $type, Validator $validator, string $attribute = 'category_id'): void
    {
        $category = Category::query()->accessibleTo($user)->whereKey($categoryId)->first();

        if ($category === null) {
            $validator->errors()->add($attribute, 'Select a valid category.');

            return;
        }

        if ($category->type !== $type) {
            $validator->errors()->add($attribute, 'Category type must match the transaction type.');
        }
    }
}

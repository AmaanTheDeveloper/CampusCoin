<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'description' => fake()->optional()->sentence(4),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
        ]);
    }

    public function withCategory(?Category $category = null): static
    {
        return $this->state(function (array $attributes) use ($category) {
            $category ??= Category::factory()
                ->state([
                    'type' => $attributes['type'] ?? TransactionType::Expense,
                    'user_id' => $attributes['user_id'] ?? null,
                    'is_default' => false,
                ])
                ->create();

            return [
                'category_id' => $category->id,
                'type' => $category->type,
            ];
        });
    }
}

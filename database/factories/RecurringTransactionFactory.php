<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    protected $model = RecurringTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d');

        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'category_id' => Category::factory()->expense(),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'description' => fake()->optional()->sentence(3),
            'frequency' => RecurringFrequency::Monthly,
            'start_date' => $start,
            'end_date' => null,
            'next_run_date' => $start,
            'last_generated_at' => null,
            'is_active' => true,
        ];
    }
}

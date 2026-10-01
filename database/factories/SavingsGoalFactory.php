<?php

namespace Database\Factories;

use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    protected $model = SavingsGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'New Laptop Fund',
            'target_amount' => '100000.00',
            'current_amount' => '0.00',
            'status' => SavingsGoalStatus::Active,
        ];
    }
}

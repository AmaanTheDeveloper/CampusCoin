<?php

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoalContribution>
 */
class SavingsGoalContributionFactory extends Factory
{
    protected $model = SavingsGoalContribution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'savings_goal_id' => SavingsGoal::factory(),
            'amount' => '1000.00',
            'note' => null,
            'contributed_at' => now(),
        ];
    }
}

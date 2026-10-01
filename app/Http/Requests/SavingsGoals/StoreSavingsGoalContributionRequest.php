<?php

namespace App\Http\Requests\SavingsGoals;

use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsGoalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SavingsGoal $goal */
        $goal = $this->route('savings_goal');

        return $this->user()?->can('update', $goal) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Http\Requests\SavingsGoals;

use App\Models\SavingsGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSavingsGoalRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var SavingsGoal $goal */
            $goal = $this->route('savings_goal');
            $target = (float) $this->input('target_amount', 0);
            if ((float) $goal->current_amount > $target) {
                $validator->errors()->add('target_amount', 'Target cannot be below the amount already saved.');
            }
        });
    }
}

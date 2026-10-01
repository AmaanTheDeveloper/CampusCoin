<?php

namespace App\Http\Requests\SavingsGoals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'starting_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = (float) $this->input('target_amount', 0);
            $starting = (float) ($this->input('starting_amount') ?: 0);
            if ($starting > $target) {
                $validator->errors()->add('starting_amount', 'Starting amount cannot exceed the target amount.');
            }
        });
    }
}

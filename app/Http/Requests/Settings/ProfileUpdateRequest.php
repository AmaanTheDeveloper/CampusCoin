<?php

namespace App\Http\Requests\Settings;

use App\Enums\AcademicYear;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'academic_year' => $this->filled('academic_year') ? $this->input('academic_year') : null,
            'monthly_allowance' => $this->filled('monthly_allowance') ? $this->input('monthly_allowance') : null,
            'savings_goal' => $this->filled('savings_goal') ? $this->input('savings_goal') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            'academic_year' => ['nullable', 'string', Rule::enum(AcademicYear::class)],

            'monthly_allowance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],

            'savings_goal' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * Prevent role / ownership tampering via request payload.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            unset($validated['role'], $validated['user_id'], $validated['id'], $validated['is_admin'], $validated['admin']);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_allowance.min' => 'Monthly allowance cannot be negative.',
            'savings_goal.min' => 'Savings goal cannot be negative.',
            'monthly_allowance.decimal' => 'Monthly allowance must have at most 2 decimal places.',
            'savings_goal.decimal' => 'Savings goal must have at most 2 decimal places.',
        ];
    }
}

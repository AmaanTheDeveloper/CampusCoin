<?php

namespace App\Http\Requests\RecurringTransactions;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RecurringTransaction $recurring */
        $recurring = $this->route('recurring_transaction');

        return $this->user()?->can('update', $recurring) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => trim($this->input('description')) ?: null,
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'category_id' => ['required', 'integer'],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::enum(RecurringFrequency::class)],
            'start_date' => ['required', 'date', 'after_or_equal:'.AvailableBalance::today()],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Please select today or a future date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = TransactionType::from($this->string('type')->toString());
            CategoryAccess::assertAccessible(
                $this->user(),
                (int) $this->input('category_id'),
                $type,
                $validator,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            unset(
                $validated['user_id'],
                $validated['id'],
                $validated['next_run_date'],
                $validated['last_generated_at'],
            );
        }

        return $validated;
    }
}

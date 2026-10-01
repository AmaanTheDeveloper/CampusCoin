<?php

namespace App\Http\Requests\Budgets;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Budget::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('month')) {
            try {
                $this->merge([
                    'month' => app(BudgetService::class)->normalizeMonth((string) $this->input('month')),
                ]);
            } catch (\Throwable) {
                // Leave as-is; validation will fail.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date'],
            'category_id' => ['required', 'integer'],
            'limit_amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999.99',
                'decimal:0,2',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $category = Category::query()
                ->accessibleTo($this->user())
                ->whereKey((int) $this->input('category_id'))
                ->first();

            if ($category === null) {
                $validator->errors()->add('category_id', 'Select a valid expense category.');

                return;
            }

            if ($category->type !== TransactionType::Expense) {
                $validator->errors()->add('category_id', 'Budgets can only be created for expense categories.');

                return;
            }

            $exists = Budget::query()
                ->where('user_id', $this->user()->id)
                ->where('category_id', $category->id)
                ->whereDate('month', $this->input('month'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('category_id', 'You already have a budget for this category in that month.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            unset($validated['user_id'], $validated['id']);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'limit_amount.gt' => 'Enter a budget limit greater than 0.',
            'limit_amount.decimal' => 'Budget limit must have at most 2 decimal places.',
            'month.required' => 'Select a budget month.',
            'month.date' => 'Enter a valid budget month.',
        ];
    }
}

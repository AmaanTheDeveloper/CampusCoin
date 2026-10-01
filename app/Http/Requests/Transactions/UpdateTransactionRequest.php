<?php

namespace App\Http\Requests\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
{
    use ValidatesBudgetOverrun;

    public function authorize(): bool
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $this->user()?->can('update', $transaction) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => trim($this->input('description')) ?: null,
            ]);
        }

        if ($this->has('confirm_budget_overrun')) {
            $this->merge([
                'confirm_budget_overrun' => filter_var(
                    $this->input('confirm_budget_overrun'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ) ?? false,
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
            'transaction_date' => ['required', 'date', 'before_or_equal:'.AvailableBalance::today()],
            'ai_suggested_category_id' => ['nullable', 'integer'],
            'ai_suggestion_accepted' => ['nullable', 'boolean'],
            'confirm_budget_overrun' => ['sometimes', 'boolean'],
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

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Transaction $transaction */
            $transaction = $this->route('transaction');
            $amount = MoneyMath::normalize($this->input('amount'));
            $postBalance = AvailableBalance::afterReplacing($this->user(), $transaction, $type, $amount);

            if (MoneyMath::compare($postBalance, '0.00') < 0) {
                if ($type === TransactionType::Expense) {
                    $available = AvailableBalance::forUser($this->user(), $transaction);
                    $validator->errors()->add(
                        'amount',
                        sprintf(
                            'Insufficient Balance: Your available balance is Rs %s, but this expense is Rs %s. Please reduce the expense amount or add income first.',
                            number_format((float) $available, 2),
                            number_format((float) $amount, 2),
                        ),
                    );
                } else {
                    $validator->errors()->add(
                        'amount',
                        sprintf(
                            'Insufficient Balance: Reducing this income would leave expenses uncovered. Available funds would become Rs %s.',
                            number_format((float) $postBalance, 2),
                        ),
                    );
                }

                return;
            }

            if ($type === TransactionType::Expense) {
                $available = AvailableBalance::forUser($this->user(), $transaction);
                $this->assertBudgetOverrunConfirmed($validator, $transaction->id, $available);
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
            unset(
                $validated['user_id'],
                $validated['id'],
                $validated['created_at'],
                $validated['updated_at'],
                $validated['deleted_at'],
                $validated['ai_suggested_category_id'],
                $validated['ai_suggestion_accepted'],
                $validated['confirm_budget_overrun'],
            );
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Select income or expense.',
            'category_id.required' => 'Select a category.',
            'amount.required' => 'Enter a valid amount greater than 0.',
            'amount.gt' => 'Enter a valid amount greater than 0.',
            'amount.decimal' => 'Amount must have at most 2 decimal places.',
            'transaction_date.required' => 'Enter a valid transaction date.',
            'transaction_date.date' => 'Enter a valid transaction date.',
            'transaction_date.before_or_equal' => 'Transaction date cannot be in the future.',
            'description.max' => 'Description is too long.',
        ];
    }
}

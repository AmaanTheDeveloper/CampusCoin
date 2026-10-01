<?php

namespace App\Http\Requests\DocumentScan;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmDocumentScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transaction::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge([
                'description' => trim($this->input('description')) ?: null,
            ]);
        }

        foreach (['acknowledge_duplicate', 'confirm_budget_overrun'] as $flag) {
            if ($this->has($flag)) {
                $this->merge([
                    $flag => filter_var(
                        $this->input($flag),
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE,
                    ) ?? false,
                ]);
            }
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scan_token' => ['required', 'string', 'uuid'],
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
            'acknowledge_duplicate' => ['sometimes', 'boolean'],
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

            if ($type !== TransactionType::Expense) {
                return;
            }

            $amount = MoneyMath::normalize($this->input('amount'));
            $available = AvailableBalance::forUser($this->user());

            if (MoneyMath::compare($amount, $available) > 0) {
                $validator->errors()->add(
                    'amount',
                    sprintf(
                        'Insufficient Balance: Your available balance is Rs %s, but this expense is Rs %s. Please reduce the expense amount or add income first.',
                        number_format((float) $available, 2),
                        number_format((float) $amount, 2),
                    ),
                );
            }

            // Soft budget overrun is handled in DocumentScanService (JSON acknowledge flow).
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scan_token.required' => 'Scan session is missing. Capture or upload again.',
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

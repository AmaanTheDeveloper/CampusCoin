<?php

namespace App\Http\Requests\Transactions;

use App\Services\BudgetService;
use App\Support\MoneyMath;
use Illuminate\Validation\Validator;

/**
 * Shared budget-overrun confirmation gate for expense create/update.
 * Confirmation is a soft warning — balance safety remains a hard block.
 */
trait ValidatesBudgetOverrun
{
    protected function assertBudgetOverrunConfirmed(
        Validator $validator,
        ?int $excludeTransactionId = null,
        ?string $availableBalance = null,
    ): void {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->string('type')->toString() !== 'expense') {
            return;
        }

        $user = $this->user();
        if ($user === null) {
            return;
        }

        $impact = app(BudgetService::class)->evaluateExpenseImpact(
            $user,
            (int) $this->input('category_id'),
            MoneyMath::normalize($this->input('amount')),
            (string) $this->input('transaction_date'),
            $excludeTransactionId,
            $availableBalance,
        );

        if ($impact === null || ! $impact['exceeds']) {
            return;
        }

        if ($this->boolean('confirm_budget_overrun')) {
            return;
        }

        session()->flash('budget_overrun', $impact);

        $validator->errors()->add(
            'budget_overrun',
            'Budget limit exceeded. Confirm to continue or review your budget.',
        );
    }
}

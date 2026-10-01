<?php

namespace App\Services\Loans;

use App\Enums\Loans\DisbursementStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanPayment;
use App\Models\Transaction;
use RuntimeException;

/**
 * Posts loan cash-flow into the personal finance ledger (transactions).
 * Source of truth for student balance remains the transactions table.
 */
class LoanFinanceLedgerService
{
    public const INCOME_CATEGORY = 'Loan';

    public const EXPENSE_CATEGORY = 'Loan Repayment';

    /**
     * Create exactly one income transaction for a confirmed disbursement.
     */
    public function recordDisbursementIncome(Loan $loan, LoanDisbursement $disbursement): Transaction
    {
        if ($disbursement->transaction_id) {
            $existing = Transaction::withTrashed()->find($disbursement->transaction_id);
            if ($existing) {
                return $existing;
            }
        }

        $status = $disbursement->status instanceof DisbursementStatus
            ? $disbursement->status
            : DisbursementStatus::tryFrom((string) $disbursement->status);

        if ($status !== DisbursementStatus::Disbursed) {
            throw new RuntimeException('Finance income is only created for confirmed disbursements.');
        }

        $loan->loadMissing('product:id,name');
        $productName = $loan->product?->name ?: 'Student loan';
        $category = $this->resolveSystemCategory(TransactionType::Income, self::INCOME_CATEGORY);

        $transaction = new Transaction([
            'type' => TransactionType::Income,
            'category_id' => $category->id,
            'amount' => $disbursement->amount,
            'description' => $productName.' — Disbursement',
            'transaction_date' => ($disbursement->confirmed_at ?? now())->toDateString(),
        ]);
        $transaction->user_id = $loan->user_id;
        $transaction->save();

        $disbursement->forceFill(['transaction_id' => $transaction->id])->save();

        return $transaction;
    }

    /**
     * Create exactly one expense transaction for a succeeded installment payment.
     */
    public function recordRepaymentExpense(Loan $loan, LoanPayment $payment): Transaction
    {
        if ($payment->transaction_id) {
            $existing = Transaction::withTrashed()->find($payment->transaction_id);
            if ($existing) {
                return $existing;
            }
        }

        $loan->loadMissing('product:id,name');
        $productName = $loan->product?->name ?: 'Student loan';
        $category = $this->resolveSystemCategory(TransactionType::Expense, self::EXPENSE_CATEGORY);

        $transaction = new Transaction([
            'type' => TransactionType::Expense,
            'category_id' => $category->id,
            'amount' => $payment->amount,
            'description' => $productName.' — Installment repayment',
            'transaction_date' => ($payment->paid_at ?? now())->toDateString(),
        ]);
        $transaction->user_id = $loan->user_id;
        $transaction->save();

        return $transaction;
    }

    public function resolveSystemCategory(TransactionType $type, string $name): Category
    {
        $category = Category::query()->firstOrNew([
            'user_id' => null,
            'type' => $type->value,
            'name' => $name,
        ]);

        if (! $category->exists) {
            $category->is_default = true;
            $category->is_active = true;
            $category->save();
        } elseif (! $category->is_active) {
            $category->forceFill(['is_active' => true, 'is_default' => true])->save();
        }

        return $category->fresh();
    }
}

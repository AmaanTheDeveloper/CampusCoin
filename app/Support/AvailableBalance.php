<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;

/**
 * Authoritative wallet balance for the authenticated user.
 * Soft-deleted transactions are excluded via Eloquent SoftDeletes.
 */
class AvailableBalance
{
    /**
     * Current balance = total income − total expense.
     *
     * When $excluding is provided (expense edit / archive simulation), that transaction
     * is removed from the totals first so the amount being replaced does not double-count.
     */
    public static function forUser(User $user, ?Transaction $excluding = null): string
    {
        $totals = $user->transactions()
            ->when(
                $excluding !== null,
                fn ($query) => $query->where('id', '!=', $excluding->id),
            )
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total
            ', [
                TransactionType::Income->value,
                TransactionType::Expense->value,
            ])
            ->first();

        $income = MoneyMath::normalize($totals->income_total ?? 0);
        $expense = MoneyMath::normalize($totals->expense_total ?? 0);

        return MoneyMath::sub($income, $expense);
    }

    /**
     * Wallet balance after removing a posted transaction (e.g. archive).
     */
    public static function afterRemoving(User $user, Transaction $transaction): string
    {
        return self::forUser($user, $transaction);
    }

    /**
     * Wallet balance after replacing a posted transaction with a new type/amount.
     */
    public static function afterReplacing(
        User $user,
        Transaction $existing,
        TransactionType $newType,
        string $newAmount,
    ): string {
        $base = self::forUser($user, $existing);
        $amount = MoneyMath::normalize($newAmount);

        return $newType === TransactionType::Income
            ? MoneyMath::add($base, $amount)
            : MoneyMath::sub($base, $amount);
    }

    /**
     * Whether a proposed expense amount is covered by available funds.
     */
    public static function canAffordExpense(User $user, string $amount, ?Transaction $excluding = null): bool
    {
        $available = self::forUser($user, $excluding);

        return MoneyMath::compare(MoneyMath::normalize($amount), $available) <= 0;
    }

    public static function today(): string
    {
        return now()->toDateString();
    }
}

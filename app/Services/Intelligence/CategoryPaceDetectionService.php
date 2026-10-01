<?php

namespace App\Services\Intelligence;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;

/**
 * Deterministic category spending pace check.
 * Compares month-to-date category spend vs same day-of-month historical average.
 * Never invents baselines; alerts only when enough history exists.
 */
class CategoryPaceDetectionService
{
    public const MIN_HISTORY_MONTHS = 2;

    public const MULTIPLIER = 1.5;

    /**
     * @return array{
     *     category_id: int|null,
     *     category_name: string,
     *     month_key: string,
     *     spent_mtd: string,
     *     typical_pace: string,
     *     day_of_month: int,
     *     history_months: int
     * }|null
     */
    public function evaluate(User $user, Transaction $transaction): ?array
    {
        abort_unless($transaction->user_id === $user->id, 404);

        if ($transaction->type !== TransactionType::Expense) {
            return null;
        }

        $asOf = Carbon::parse($transaction->transaction_date->toDateString())->startOfDay();
        $dayOfMonth = (int) $asOf->day;
        $monthStart = $asOf->copy()->startOfMonth()->toDateString();
        $monthKey = $asOf->format('Y-m');
        $lookbackMonths = max(self::MIN_HISTORY_MONTHS, (int) config('intelligence.forecast_lookback_months', 3));

        $mtdQuery = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<=', $asOf->toDateString());

        if ($transaction->category_id !== null) {
            $mtdQuery->where('category_id', $transaction->category_id);
            $categoryName = $transaction->category?->name
                ?? $transaction->category()->value('name')
                ?? 'this category';
        } else {
            $mtdQuery->whereNull('category_id');
            $categoryName = 'Uncategorized';
        }

        $spentMtd = MoneyMath::normalize($mtdQuery->sum('amount'));
        if (MoneyMath::compare($spentMtd, '0.00') <= 0) {
            return null;
        }

        $historical = [];
        for ($i = 1; $i <= $lookbackMonths; $i++) {
            $histMonth = $asOf->copy()->subMonthsNoOverflow($i);
            $histStart = $histMonth->copy()->startOfMonth()->toDateString();
            $histEndDay = min($dayOfMonth, (int) $histMonth->daysInMonth);
            $histEnd = $histMonth->copy()->day($histEndDay)->toDateString();

            $histQuery = $user->transactions()
                ->where('type', TransactionType::Expense->value)
                ->whereDate('transaction_date', '>=', $histStart)
                ->whereDate('transaction_date', '<=', $histEnd);

            if ($transaction->category_id !== null) {
                $histQuery->where('category_id', $transaction->category_id);
            } else {
                $histQuery->whereNull('category_id');
            }

            $histAmount = MoneyMath::normalize($histQuery->sum('amount'));
            if (MoneyMath::compare($histAmount, '0.00') > 0) {
                $historical[] = $histAmount;
            }
        }

        if (count($historical) < self::MIN_HISTORY_MONTHS) {
            return null;
        }

        $sum = '0.00';
        foreach ($historical as $row) {
            $sum = MoneyMath::add($sum, $row);
        }
        $typical = MoneyMath::normalize(((float) $sum) / count($historical));

        if (MoneyMath::compare($typical, '0.00') <= 0) {
            return null;
        }

        $threshold = MoneyMath::normalize((float) $typical * self::MULTIPLIER);
        if (MoneyMath::compare($spentMtd, $threshold) < 0) {
            return null;
        }

        return [
            'category_id' => $transaction->category_id,
            'category_name' => $categoryName,
            'month_key' => $monthKey,
            'spent_mtd' => $spentMtd,
            'typical_pace' => $typical,
            'day_of_month' => $dayOfMonth,
            'history_months' => count($historical),
        ];
    }
}

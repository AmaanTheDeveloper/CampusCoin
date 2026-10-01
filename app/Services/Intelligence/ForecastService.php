<?php

namespace App\Services\Intelligence;

use App\Enums\ForecastConfidence;
use App\Enums\TransactionType;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic next-month forecast from completed historical months.
 *
 * Method: arithmetic mean of the last N fully completed calendar months
 * (current month excluded). Category projections use the same window.
 * Recurring templates are NOT added separately — generated occurrences are
 * already present in historical monthly totals (avoids double-counting).
 */
class ForecastService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $lookback = max(1, (int) config('intelligence.forecast_lookback_months', 3));
        $categoryLimit = max(1, (int) config('intelligence.forecast_category_limit', 5));

        $target = $now->copy()->addMonthNoOverflow()->startOfMonth();
        $completedEnd = $now->copy()->startOfMonth()->subDay(); // last day of previous month
        $completedStart = $now->copy()->startOfMonth()->subMonthsNoOverflow($lookback);

        $months = $this->monthlyTotals($user, $completedStart->toDateString(), $completedEnd->toDateString());
        $usableMonths = array_values(array_filter(
            $months,
            fn (array $m) => (int) ($m['transaction_count'] ?? 0) > 0
        ));

        $confidence = $this->confidence(count($usableMonths));

        if ($confidence === ForecastConfidence::Insufficient) {
            return [
                'target_period' => $target->format('Y-m'),
                'target_label' => $target->format('F Y'),
                'confidence' => $confidence->value,
                'confidence_label' => $confidence->label(),
                'method' => sprintf('Average of up to %d completed months (current month excluded).', $lookback),
                'months_used' => 0,
                'estimated_income' => null,
                'estimated_expense' => null,
                'estimated_balance' => null,
                'categories' => [],
                'disclaimer' => 'Forecasts are estimates based on your historical Campus Coin data, not guaranteed outcomes.',
                'empty_message' => 'Not enough transaction history yet. Keep tracking your spending to unlock forecasts.',
            ];
        }

        $incomeSum = '0.00';
        $expenseSum = '0.00';
        foreach ($usableMonths as $month) {
            $incomeSum = MoneyMath::add($incomeSum, $month['income']);
            $expenseSum = MoneyMath::add($expenseSum, $month['expense']);
        }

        $count = count($usableMonths);
        $estimatedIncome = $this->average($incomeSum, $count);
        $estimatedExpense = $this->average($expenseSum, $count);
        $estimatedBalance = MoneyMath::sub($estimatedIncome, $estimatedExpense);

        $categories = $this->categoryAverages(
            $user,
            $completedStart->toDateString(),
            $completedEnd->toDateString(),
            $count,
            $categoryLimit,
        );

        return [
            'target_period' => $target->format('Y-m'),
            'target_label' => $target->format('F Y'),
            'confidence' => $confidence->value,
            'confidence_label' => $confidence->label(),
            'method' => sprintf(
                'Estimated from the average of %d completed month%s (%s through %s). Current month excluded.',
                $count,
                $count === 1 ? '' : 's',
                $completedStart->format('M Y'),
                $completedEnd->copy()->startOfMonth()->format('M Y'),
            ),
            'months_used' => $count,
            'estimated_income' => $estimatedIncome,
            'estimated_expense' => $estimatedExpense,
            'estimated_balance' => $estimatedBalance,
            'categories' => $categories,
            'disclaimer' => 'Forecasts are estimates based on your historical Campus Coin data, not guaranteed outcomes.',
            'empty_message' => null,
        ];
    }

    private function confidence(int $usableMonths): ForecastConfidence
    {
        return match (true) {
            $usableMonths < 1 => ForecastConfidence::Insufficient,
            $usableMonths === 1 => ForecastConfidence::Low,
            default => ForecastConfidence::Moderate,
        };
    }

    /**
     * @return list<array{month: string, income: string, expense: string, transaction_count: int}>
     */
    private function monthlyTotals(User $user, string $start, string $end): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', transaction_date)"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";

        $rows = $user->transactions()
            ->whereDate('transaction_date', '>=', $start)
            ->whereDate('transaction_date', '<=', $end)
            ->groupBy(DB::raw($monthExpression))
            ->orderBy(DB::raw($monthExpression))
            ->selectRaw("
                {$monthExpression} as month_key,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total,
                COUNT(*) as transaction_count
            ", [
                TransactionType::Income->value,
                TransactionType::Expense->value,
            ])
            ->get();

        return $rows->map(fn ($row) => [
            'month' => (string) $row->month_key,
            'income' => MoneyMath::normalize($row->income_total),
            'expense' => MoneyMath::normalize($row->expense_total),
            'transaction_count' => (int) $row->transaction_count,
        ])->all();
    }

    /**
     * @return list<array{category_id: int|null, name: string, estimated_amount: string}>
     */
    private function categoryAverages(User $user, string $start, string $end, int $monthCount, int $limit): array
    {
        if ($monthCount < 1) {
            return [];
        }

        $rows = $user->transactions()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereDate('transactions.transaction_date', '>=', $start)
            ->whereDate('transactions.transaction_date', '<=', $end)
            ->whereNull('transactions.deleted_at')
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->limit($limit)
            ->selectRaw('
                transactions.category_id as category_id,
                COALESCE(categories.name, ?) as category_name,
                COALESCE(SUM(transactions.amount), 0) as total_amount
            ', ['Uncategorized'])
            ->get();

        return $rows->map(fn ($row) => [
            'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
            'name' => (string) $row->category_name,
            'estimated_amount' => $this->average(MoneyMath::normalize($row->total_amount), $monthCount),
        ])->all();
    }

    private function average(string $sum, int $count): string
    {
        if ($count < 1) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            return MoneyMath::normalize(bcdiv($sum, (string) $count, 4));
        }

        return MoneyMath::normalize((float) $sum / $count);
    }
}

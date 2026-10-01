<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    public function __construct(
        private readonly BudgetService $budgets,
    ) {}

    /**
     * Build the full dashboard payload for a user.
     *
     * Current-month totals use the full calendar month (including future-dated
     * rows whose transaction_date falls in the month). The daily spending chart
     * only includes days from month start through today so future points are
     * not implied as already spent.
     *
     * Soft-deleted transactions are excluded via Eloquent SoftDeletes.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $today = $now->toDateString();
        $monthLabel = $now->format('F Y');

        $summary = $this->summary($user, $monthStart, $monthEnd);
        // Canonical wallet (lifetime) — same source as expense create/edit guards.
        $summary['available_balance'] = AvailableBalance::forUser($user);
        $categoryBreakdown = $this->categoryBreakdown($user, $monthStart, $monthEnd, $summary['expense']);
        $dailyTrend = $this->dailyExpenseTrend($user, $monthStart, $today);
        $monthlyTrend = $this->monthlyTrend($user, $now);
        $recent = $this->recentTransactions($user);
        $largestExpense = $this->largestExpense($user, $monthStart, $monthEnd);
        $topCategory = $categoryBreakdown[0] ?? null;
        $hasAnyTransactions = $user->transactions()->exists();
        $budgetVsActual = $this->budgets->currentMonthBudgetVsActual($user, $now);

        return [
            'period' => [
                'label' => $monthLabel,
                'start' => $monthStart,
                'end' => $monthEnd,
                'today' => $today,
                'timezone' => config('app.timezone'),
            ],
            'greeting' => [
                'period' => $this->greetingPeriod($now),
                'name' => filled($user->name) ? trim((string) $user->name) : 'there',
            ],
            'has_transactions' => $hasAnyTransactions,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'top_category' => $topCategory,
            'daily_expense_trend' => $dailyTrend,
            'monthly_trend' => $monthlyTrend,
            'recent_transactions' => $recent,
            'largest_expense' => $largestExpense,
            'highlights' => $this->highlights($summary, $topCategory, $largestExpense, $hasAnyTransactions),
            'budgets' => $budgetVsActual,
        ];
    }

    /**
     * @return array{
     *     income: string,
     *     expense: string,
     *     balance: string,
     *     savings: string,
     *     savings_rate: float|null,
     *     transaction_count: int,
     *     expense_count: int,
     *     income_count: int
     * }
     *
     * Period `balance` / `savings` = current-month income − expense (may be negative).
     * Callers that need the spendable wallet must use AvailableBalance / available_balance.
     */
    public function summary(User $user, string $monthStart, string $monthEnd): array
    {
        $base = $user->transactions()
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<=', $monthEnd);

        $totals = (clone $base)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total,
                COUNT(*) as transaction_count,
                COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as income_count,
                COALESCE(SUM(CASE WHEN type = ? THEN 1 ELSE 0 END), 0) as expense_count
            ', [
                TransactionType::Income->value,
                TransactionType::Expense->value,
                TransactionType::Income->value,
                TransactionType::Expense->value,
            ])
            ->first();

        $income = MoneyMath::normalize($totals->income_total ?? 0);
        $expense = MoneyMath::normalize($totals->expense_total ?? 0);
        $balance = MoneyMath::sub($income, $expense);
        $savingsRate = MoneyMath::percentOf($balance, $income);

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $balance,
            'savings' => $balance,
            'savings_rate' => $savingsRate,
            'transaction_count' => (int) ($totals->transaction_count ?? 0),
            'income_count' => (int) ($totals->income_count ?? 0),
            'expense_count' => (int) ($totals->expense_count ?? 0),
        ];
    }

    /**
     * @return list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>
     */
    public function categoryBreakdown(User $user, string $monthStart, string $monthEnd, string $totalExpense): array
    {
        $rows = $user->transactions()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereDate('transactions.transaction_date', '>=', $monthStart)
            ->whereDate('transactions.transaction_date', '<=', $monthEnd)
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->selectRaw('transactions.category_id as category_id, categories.name as category_name, SUM(transactions.amount) as total')
            ->get();

        return $rows->map(function ($row) use ($totalExpense) {
            $amount = MoneyMath::normalize($row->total);

            return [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'name' => $row->category_name ?: 'Uncategorized',
                'amount' => $amount,
                'percentage' => MoneyMath::percentOf($amount, $totalExpense),
            ];
        })->values()->all();
    }

    /**
     * Daily expense totals from month start through today (inclusive).
     *
     * @return list<array{date: string, amount: string}>
     */
    public function dailyExpenseTrend(User $user, string $from, string $to): array
    {
        $rows = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->selectRaw('transaction_date as day, SUM(amount) as total')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $points = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $points[] = [
                'date' => $key,
                'amount' => MoneyMath::normalize($rows->get($key)?->total ?? 0),
            ];
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * Last 6 calendar months including the current month.
     *
     * @return list<array{month: string, label: string, income: string, expense: string, balance: string}>
     */
    public function monthlyTrend(User $user, CarbonInterface $now): array
    {
        $end = Carbon::parse($now->toDateString())->endOfMonth();
        $start = $end->copy()->subMonthsNoOverflow(5)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', transaction_date)"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";

        $rows = $user->transactions()
            ->whereDate('transaction_date', '>=', $start->toDateString())
            ->whereDate('transaction_date', '<=', $end->toDateString())
            ->groupBy(DB::raw($monthExpression))
            ->orderBy(DB::raw($monthExpression))
            ->selectRaw("
                {$monthExpression} as month_key,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income_total,
                COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense_total
            ", [
                TransactionType::Income->value,
                TransactionType::Expense->value,
            ])
            ->get()
            ->keyBy('month_key');

        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $income = MoneyMath::normalize($rows->get($key)?->income_total ?? 0);
            $expense = MoneyMath::normalize($rows->get($key)?->expense_total ?? 0);

            $points[] = [
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'income' => $income,
                'expense' => $expense,
                'balance' => MoneyMath::sub($income, $expense),
            ];

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return $points;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(User $user, int $limit = 5): array
    {
        return $user->transactions()
            ->with('category:id,name,type,is_default')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => MoneyMath::normalize($transaction->amount),
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'category' => $transaction->category
                    ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                    ]
                    : null,
            ])
            ->all();
    }

    /**
     * @return array{id: int, description: string, amount: string, transaction_date: string, category: string}|null
     */
    public function largestExpense(User $user, string $monthStart, string $monthEnd): ?array
    {
        $transaction = $user->transactions()
            ->with('category:id,name')
            ->where('type', TransactionType::Expense->value)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<=', $monthEnd)
            ->orderByDesc('amount')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'id' => $transaction->id,
            'description' => $transaction->description ?: 'Unnamed expense',
            'amount' => MoneyMath::normalize($transaction->amount),
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'category' => $transaction->category?->name ?? 'Uncategorized',
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $topCategory
     * @param  array<string, mixed>|null  $largestExpense
     * @return list<array{id: string, message: string}>
     */
    public function highlights(array $summary, ?array $topCategory, ?array $largestExpense, bool $hasAnyTransactions): array
    {
        if (! $hasAnyTransactions) {
            return [];
        }

        $highlights = [];

        if ((int) $summary['transaction_count'] > 0) {
            $highlights[] = [
                'id' => 'count',
                'message' => sprintf(
                    'You recorded %d transaction%s this month.',
                    $summary['transaction_count'],
                    $summary['transaction_count'] === 1 ? '' : 's'
                ),
            ];
        }

        if ((int) $summary['expense_count'] > 0 && $topCategory !== null) {
            $highlights[] = [
                'id' => 'top-category',
                'message' => sprintf('%s is your highest expense category this month.', $topCategory['name']),
            ];
        }

        if ((int) $summary['income_count'] === 0 && (int) $summary['expense_count'] > 0) {
            $highlights[] = [
                'id' => 'no-income',
                'message' => 'You have not recorded any income this month.',
            ];
        }

        if (MoneyMath::compare($summary['income'], '0.00') > 0 && MoneyMath::compare($summary['balance'], '0.00') > 0) {
            $highlights[] = [
                'id' => 'surplus',
                'message' => 'Your income is higher than your expenses this month.',
            ];
        }

        if (MoneyMath::compare($summary['balance'], '0.00') < 0) {
            $highlights[] = [
                'id' => 'deficit',
                'message' => 'Your expenses exceed your income this month.',
            ];
        }

        if ($largestExpense !== null) {
            $highlights[] = [
                'id' => 'largest',
                'message' => sprintf(
                    'Your largest expense this month was Rs. %s (%s).',
                    number_format((float) $largestExpense['amount'], 2),
                    $largestExpense['description']
                ),
            ];
        }

        return $highlights;
    }

    private function greetingPeriod(CarbonInterface $now): string
    {
        $hour = (int) $now->format('G');

        if ($hour < 12) {
            return 'morning';
        }

        if ($hour < 17) {
            return 'afternoon';
        }

        return 'evening';
    }
}

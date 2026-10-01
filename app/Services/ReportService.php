<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private readonly DashboardAnalyticsService $dashboard,
    ) {}

    /**
     * Full report payload for the authenticated user.
     *
     * Query groups (bounded aggregates, no per-day/category N+1):
     * 1) summary  2) category  3) daily  4) weekly
     * 5) six-month  6) paginated transactions  7) filter option lists
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, ReportFilters $filters, int $page = 1, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());

        $summary = $this->summary($user, $filters);
        $categoryBreakdown = $this->categoryBreakdown($user, $filters, $summary['expense']);
        $daily = $this->dailyExpenseSummary($user, $filters);
        $weekly = $this->weeklyExpenseSummary($user, $filters);
        $sixMonth = $this->dashboard->monthlyTrend($user, $now);
        $transactions = $this->paginatedTransactions($user, $filters, $page);

        $categoryName = null;
        if ($filters->categoryId !== null) {
            $categoryName = Category::query()->accessibleTo($user)->whereKey($filters->categoryId)->value('name');
        }

        $incomeSourceName = null;
        if ($filters->incomeSourceId !== null) {
            $incomeSourceName = Category::query()->accessibleTo($user)->whereKey($filters->incomeSourceId)->value('name');
        }

        $hasData = $summary['transaction_count'] > 0
            || collect($sixMonth)->contains(fn (array $m) => MoneyMath::compare($m['income'], '0.00') !== 0 || MoneyMath::compare($m['expense'], '0.00') !== 0);

        return [
            'filters' => [
                'from' => $filters->from,
                'to' => $filters->to,
                'category' => $filters->uncategorizedOnly ? 'uncategorized' : $filters->categoryId,
                'income_source' => $filters->incomeSourceId,
            ],
            'filter_labels' => $filters->activeLabels($categoryName, $incomeSourceName),
            'period' => [
                'label' => $filters->periodLabel(),
                'from' => $filters->from,
                'to' => $filters->to,
                'timezone' => config('app.timezone'),
                'generated_at' => $now->toIso8601String(),
                'generated_label' => $now->timezone(config('app.timezone'))->format('j F Y, g:i A'),
                'week_starts_on' => 'monday',
                'max_range_months' => ReportFilters::MAX_RANGE_MONTHS,
            ],
            'has_data' => $summary['transaction_count'] > 0,
            'has_any_history' => $hasData,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'top_category' => $categoryBreakdown[0] ?? null,
            'daily_summary' => $daily,
            'weekly_summary' => $weekly,
            'six_month_trend' => $sixMonth,
            'six_month_note' => 'Last six calendar months (independent of the selected date range).',
            'transactions' => $this->serializePaginator($transactions),
            'expense_categories' => array_merge(
                [['id' => 'uncategorized', 'name' => 'Uncategorized', 'type' => 'expense', 'is_default' => false]],
                CategoryAccess::optionsForUser($user, TransactionType::Expense),
            ),
            'income_sources' => CategoryAccess::optionsForUser($user, TransactionType::Income),
            'filter_semantics' => [
                'category' => 'Applies to expense totals, expense charts, and expense rows in the detail table.',
                'income_source' => 'Applies to income totals and income rows in the detail table only. Does not filter expense charts.',
                'date_range' => 'Applies to summary, category, daily, weekly, and detail table. Does not change the six-month trend.',
            ],
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
     *     income_count: int,
     *     expense_count: int
     * }
     */
    public function summary(User $user, ReportFilters $filters): array
    {
        $incomeQuery = $this->baseQuery($user, $filters->from, $filters->to)
            ->where('type', TransactionType::Income->value);
        $this->applyIncomeSourceFilter($incomeQuery, $filters);

        $expenseQuery = $this->baseQuery($user, $filters->from, $filters->to)
            ->where('type', TransactionType::Expense->value);
        $this->applyCategoryFilter($expenseQuery, $filters);

        $income = MoneyMath::normalize((clone $incomeQuery)->sum('amount'));
        $expense = MoneyMath::normalize((clone $expenseQuery)->sum('amount'));
        $balance = MoneyMath::sub($income, $expense);
        $incomeCount = (clone $incomeQuery)->count();
        $expenseCount = (clone $expenseQuery)->count();

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $balance,
            'savings' => $balance,
            'savings_rate' => MoneyMath::percentOf($balance, $income),
            'transaction_count' => $incomeCount + $expenseCount,
            'income_count' => $incomeCount,
            'expense_count' => $expenseCount,
        ];
    }

    /**
     * @return list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>
     */
    public function categoryBreakdown(User $user, ReportFilters $filters, string $totalExpense): array
    {
        $query = $this->baseQuery($user, $filters->from, $filters->to)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.type', TransactionType::Expense->value);

        $this->applyCategoryFilter($query, $filters, 'transactions.category_id');

        $rows = $query
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
     * @return list<array{date: string, amount: string}>
     */
    public function dailyExpenseSummary(User $user, ReportFilters $filters): array
    {
        $query = $this->baseQuery($user, $filters->from, $filters->to)
            ->where('type', TransactionType::Expense->value);
        $this->applyCategoryFilter($query, $filters);

        $rows = $query
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->selectRaw('transaction_date as day, SUM(amount) as total')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $points = [];
        $cursor = Carbon::parse($filters->from)->startOfDay();
        $end = Carbon::parse($filters->to)->startOfDay();

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
     * Calendar weeks (Monday–Sunday) clipped to the selected date range.
     *
     * @return list<array{week: int, label: string, start: string, end: string, amount: string}>
     */
    public function weeklyExpenseSummary(User $user, ReportFilters $filters): array
    {
        $query = $this->baseQuery($user, $filters->from, $filters->to)
            ->where('type', TransactionType::Expense->value);
        $this->applyCategoryFilter($query, $filters);

        $rows = $query
            ->groupBy('transaction_date')
            ->selectRaw('transaction_date as day, SUM(amount) as total')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $rangeStart = Carbon::parse($filters->from)->startOfDay();
        $rangeEnd = Carbon::parse($filters->to)->startOfDay();

        // Align to Monday of the week that contains the range start.
        $weekStart = $rangeStart->copy()->startOfWeek(ReportFilters::WEEK_STARTS_ON);
        $weeks = [];
        $weekNumber = 1;

        while ($weekStart->lte($rangeEnd)) {
            $weekEnd = $weekStart->copy()->addDays(6);
            $clipStart = $weekStart->copy()->max($rangeStart);
            $clipEnd = $weekEnd->copy()->min($rangeEnd);

            if ($clipStart->lte($clipEnd)) {
                $total = '0.00';
                $cursor = $clipStart->copy();
                while ($cursor->lte($clipEnd)) {
                    $dayTotal = MoneyMath::normalize($rows->get($cursor->toDateString())?->total ?? 0);
                    $total = MoneyMath::add($total, $dayTotal);
                    $cursor->addDay();
                }

                $weeks[] = [
                    'week' => $weekNumber,
                    'label' => 'Week '.$weekNumber,
                    'start' => $clipStart->toDateString(),
                    'end' => $clipEnd->toDateString(),
                    'amount' => $total,
                ];
                $weekNumber++;
            }

            $weekStart->addWeek();
        }

        return $weeks;
    }

    public function paginatedTransactions(User $user, ReportFilters $filters, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = $user->transactions()
            ->with('category:id,name,type,is_default')
            ->whereDate('transaction_date', '>=', $filters->from)
            ->whereDate('transaction_date', '<=', $filters->to);

        $query->where(function (Builder $builder) use ($filters): void {
            $builder->where(function (Builder $income) use ($filters): void {
                $income->where('type', TransactionType::Income->value);
                if ($filters->incomeSourceId !== null) {
                    $income->where('category_id', $filters->incomeSourceId);
                }
            })->orWhere(function (Builder $expense) use ($filters): void {
                $expense->where('type', TransactionType::Expense->value);
                if ($filters->uncategorizedOnly) {
                    $expense->whereNull('category_id');
                } elseif ($filters->categoryId !== null) {
                    $expense->where('category_id', $filters->categoryId);
                }
            });
        });

        return $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePaginator(LengthAwarePaginator $paginator): array
    {
        $items = collect($paginator->items())->map(function (Transaction $transaction) {
            return [
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
            ];
        })->values()->all();

        return [
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => $paginator->linkCollection()->toArray(),
        ];
    }

    private function baseQuery(User $user, string $from, string $to): HasMany
    {
        return $user->transactions()
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to);
    }

    private function applyCategoryFilter(HasMany|Builder $query, ReportFilters $filters, string $column = 'category_id'): void
    {
        if ($filters->uncategorizedOnly) {
            $query->whereNull($column);

            return;
        }

        if ($filters->categoryId !== null) {
            $query->where($column, $filters->categoryId);
        }
    }

    private function applyIncomeSourceFilter(HasMany|Builder $query, ReportFilters $filters): void
    {
        if ($filters->incomeSourceId !== null) {
            $query->where('category_id', $filters->incomeSourceId);
        }
    }
}

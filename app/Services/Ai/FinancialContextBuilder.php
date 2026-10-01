<?php

namespace App\Services\Ai;

use App\Enums\RecurringOccurrenceStatus;
use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\Insights\InsightDataBuilder;
use App\Support\AvailableBalance;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Builds minimized, user-scoped financial facts for Campus Coin AI.
 * Reuses existing analytics / insight builders — no parallel money formulas.
 */
class FinancialContextBuilder
{
    public function __construct(
        private readonly InsightDataBuilder $insights,
        private readonly DashboardAnalyticsService $dashboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $insight = $this->insights->forUser($user, $now);
        $dashboard = $this->dashboard->forUser($user, $now);

        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $categories = $this->enrichCategories(
            $user,
            $dashboard['category_breakdown'] ?? [],
            $dashboard['budgets'] ?? [],
            $monthStart,
            $monthEnd,
            MoneyMath::normalize($insight['expense'] ?? 0),
        );

        $topOverall = $categories[0] ?? null;
        $topNamed = collect($categories)
            ->first(fn (array $row) => ($row['category_id'] ?? null) !== null && ($row['name'] ?? '') !== 'Uncategorized');

        $budgets = $this->enrichBudgets($dashboard['budgets'] ?? []);
        $trend = $this->recentMonthTrend($user, $insight, $now);
        $recurring = $this->recurringCommitments($user);
        $available = AvailableBalance::forUser($user);
        $periodBalance = MoneyMath::normalize($insight['balance'] ?? 0);
        $savingsGoal = MoneyMath::normalize($user->savings_goal ?? 0);

        $uncategorized = collect($categories)->first(
            fn (array $row) => ($row['category_id'] ?? null) === null || ($row['name'] ?? '') === 'Uncategorized'
        );

        return [
            'profile' => [
                'academic_year' => $user->academic_year?->value ?? $user->academic_year,
                'monthly_allowance_baseline' => MoneyMath::normalize($user->monthly_allowance ?? 0),
                'savings_goal' => $savingsGoal,
            ],
            'current_period' => [
                'period' => $insight['period'],
                'period_label' => $insight['period_label'],
                'income' => $insight['income'],
                'expenses' => $insight['expense'],
                'period_balance' => $periodBalance,
                'available_balance' => $available,
                'savings_rate' => $insight['savings_rate'],
                'transaction_count' => $insight['transaction_count'],
                // Largest by amount (may be Uncategorized when category_id is null).
                'top_category' => $topOverall['name'] ?? null,
                'top_category_amount' => $topOverall['amount'] ?? null,
                'top_category_id' => $topOverall['category_id'] ?? null,
                // Largest named Campus Coin category — preferred for advice.
                'top_named_category' => $topNamed['name'] ?? null,
                'top_named_category_amount' => $topNamed['amount'] ?? null,
                'top_named_category_id' => $topNamed['category_id'] ?? null,
                'uncategorized_amount' => $uncategorized['amount'] ?? '0.00',
                'has_uncategorized' => $uncategorized !== null
                    && MoneyMath::compare($uncategorized['amount'] ?? '0', '0.00') > 0,
            ],
            'categories' => $categories,
            'budgets' => $budgets,
            'history' => [
                'previous_month' => $insight['previous_month'],
                'recent_months' => $trend,
            ],
            'recurring' => $recurring,
            'savings' => [
                'goal' => $savingsGoal,
                'current_period_balance' => $periodBalance,
                'available_balance' => $available,
                'period_savings' => MoneyMath::compare($periodBalance, '0.00') > 0 ? $periodBalance : '0.00',
            ],
            'insufficient_data' => (bool) $insight['insufficient_data'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     * @param  list<array<string, mixed>>  $budgets
     * @return list<array<string, mixed>>
     */
    private function enrichCategories(
        User $user,
        array $breakdown,
        array $budgets,
        string $monthStart,
        string $monthEnd,
        string $totalExpense,
    ): array {
        $counts = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<=', $monthEnd)
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as txn_count')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->category_id === null ? 'null' : (string) $row->category_id => (int) $row->txn_count,
            ]);

        $budgetByCategoryId = [];
        foreach ($budgets as $budget) {
            if (! is_array($budget)) {
                continue;
            }
            $categoryId = $budget['category']['id'] ?? null;
            if ($categoryId === null) {
                continue;
            }
            $budgetByCategoryId[(int) $categoryId] = $budget;
        }

        $rows = [];
        foreach ($breakdown as $row) {
            if (! is_array($row)) {
                continue;
            }

            $categoryId = array_key_exists('category_id', $row) && $row['category_id'] !== null
                ? (int) $row['category_id']
                : null;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = $categoryId === null ? 'Uncategorized' : 'Category';
            }

            // Never relabel a real named category as Uncategorized.
            if ($categoryId !== null && strcasecmp($name, 'Uncategorized') === 0) {
                continue;
            }

            $amount = MoneyMath::normalize($row['amount'] ?? 0);
            $budget = $categoryId !== null ? ($budgetByCategoryId[$categoryId] ?? null) : null;
            $limit = $budget !== null ? MoneyMath::normalize($budget['limit'] ?? 0) : null;
            $spent = $budget !== null ? MoneyMath::normalize($budget['spent'] ?? 0) : $amount;
            $remaining = $budget !== null ? MoneyMath::normalize($budget['remaining'] ?? 0) : null;
            $over = $budget !== null ? MoneyMath::normalize($budget['over_budget'] ?? 0) : null;
            $txnCount = (int) ($counts[$categoryId === null ? 'null' : (string) $categoryId] ?? 0);

            $rows[] = [
                'category_id' => $categoryId,
                'name' => $name,
                'amount' => $amount,
                'percentage' => $row['percentage'] ?? MoneyMath::percentOf($amount, $totalExpense),
                'transaction_count' => $txnCount,
                'budget_limit' => $limit,
                'budget_spent' => $budget !== null ? $spent : null,
                'budget_remaining' => $remaining,
                'budget_over' => $over,
                'budget_status' => $budget['status'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $budgets
     * @return list<array<string, mixed>>
     */
    private function enrichBudgets(array $budgets): array
    {
        $rows = [];
        foreach ($budgets as $budget) {
            if (! is_array($budget)) {
                continue;
            }

            $rows[] = [
                'category_id' => $budget['category']['id'] ?? null,
                'category' => $budget['category']['name'] ?? 'Category',
                'limit' => MoneyMath::normalize($budget['limit'] ?? 0),
                'spent' => MoneyMath::normalize($budget['spent'] ?? 0),
                'remaining' => MoneyMath::normalize($budget['remaining'] ?? 0),
                'over_budget' => MoneyMath::normalize($budget['over_budget'] ?? 0),
                'percentage' => $budget['percentage'] ?? null,
                'status' => $budget['status'] ?? null,
                'status_label' => $budget['status_label'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $insight
     * @return list<array{month: string, income: string, expense: string, balance: string}>
     */
    private function recentMonthTrend(User $user, array $insight, CarbonInterface $now): array
    {
        $dashboard = $this->dashboard->forUser($user, $now);
        $months = collect($dashboard['monthly_trend'] ?? [])
            ->take(-6)
            ->map(fn (array $row) => [
                'month' => (string) ($row['month'] ?? ''),
                'income' => MoneyMath::normalize($row['income'] ?? 0),
                'expense' => MoneyMath::normalize($row['expense'] ?? 0),
                'balance' => MoneyMath::normalize($row['balance'] ?? 0),
            ])
            ->values()
            ->all();

        if ($months === [] && is_array($insight['previous_month'] ?? null)) {
            return [
                [
                    'month' => 'previous',
                    'income' => $insight['previous_month']['income'],
                    'expense' => $insight['previous_month']['expense'],
                    'balance' => $insight['previous_month']['balance'],
                ],
            ];
        }

        return $months;
    }

    /**
     * @return array{upcoming: list<array<string, mixed>>, pending_approvals: list<array<string, mixed>>, monthly_committed_expense: string}
     */
    private function recurringCommitments(User $user): array
    {
        $upcoming = RecurringTransaction::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('next_run_date')
            ->limit(8)
            ->get()
            ->map(fn (RecurringTransaction $rule) => [
                'description' => (string) $rule->description,
                'amount' => MoneyMath::normalize($rule->amount),
                'type' => $rule->type instanceof TransactionType ? $rule->type->value : (string) $rule->type,
                'frequency' => $rule->frequency?->value ?? (string) $rule->frequency,
                'next_run_date' => optional($rule->next_run_date)->toDateString(),
                'category' => $rule->category?->name,
            ])
            ->values()
            ->all();

        $pending = RecurringTransactionOccurrence::query()
            ->whereHas('recurringTransaction', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', RecurringOccurrenceStatus::PendingApproval)
            ->with(['recurringTransaction.category:id,name'])
            ->orderBy('occurrence_date')
            ->limit(8)
            ->get()
            ->map(function (RecurringTransactionOccurrence $occurrence) {
                $rule = $occurrence->recurringTransaction;

                return [
                    'description' => (string) ($rule?->description ?? 'Recurring'),
                    'amount' => MoneyMath::normalize($rule?->amount ?? 0),
                    'occurred_on' => optional($occurrence->occurrence_date)->toDateString(),
                    'category' => $rule?->category?->name,
                ];
            })
            ->values()
            ->all();

        $committed = '0.00';
        foreach ($upcoming as $row) {
            if (($row['type'] ?? '') !== TransactionType::Expense->value) {
                continue;
            }
            $committed = MoneyMath::add($committed, $row['amount']);
        }

        return [
            'upcoming' => $upcoming,
            'pending_approvals' => $pending,
            'monthly_committed_expense' => $committed,
        ];
    }
}

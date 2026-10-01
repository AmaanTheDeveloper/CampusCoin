<?php

namespace App\Services\Insights;

use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class InsightDataBuilder
{
    public function __construct(
        private readonly DashboardAnalyticsService $dashboard,
    ) {}

    /**
     * Deterministic factual context for AI (and for fallbacks).
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $dashboard = $this->dashboard->forUser($user, $now);

        $period = $now->format('Y-m');
        $summary = $dashboard['summary'];
        $previous = $this->previousMonthPoint($dashboard['monthly_trend'] ?? [], $period);

        $topCategories = collect($dashboard['category_breakdown'] ?? [])
            ->take(5)
            ->map(fn (array $row) => [
                'category_id' => $row['category_id'] ?? null,
                'name' => $row['name'],
                'amount' => $row['amount'],
                'percentage' => $row['percentage'],
            ])
            ->values()
            ->all();

        $budgetStatus = collect($dashboard['budgets'] ?? [])
            ->map(fn (array $b) => [
                'category_id' => $b['category']['id'] ?? null,
                'category' => $b['category']['name'],
                'limit' => $b['limit'],
                'spent' => $b['spent'],
                'remaining' => $b['remaining'] ?? null,
                'over_budget' => $b['over_budget'] ?? null,
                'percentage' => $b['percentage'],
                'status' => $b['status'],
                'status_label' => $b['status_label'] ?? null,
            ])
            ->values()
            ->all();

        $insufficient = ((int) ($summary['transaction_count'] ?? 0)) === 0;

        return [
            'period' => $period,
            'period_label' => $now->format('F Y'),
            'income' => $summary['income'],
            'expense' => $summary['expense'],
            'balance' => $summary['balance'],
            'savings_rate' => $summary['savings_rate'],
            'transaction_count' => (int) $summary['transaction_count'],
            'top_categories' => $topCategories,
            'budget_status' => $budgetStatus,
            'previous_month' => $previous,
            'savings_goal' => MoneyMath::normalize($user->savings_goal ?? 0),
            'insufficient_data' => $insufficient,
            'facts' => [
                'top_category' => $topCategories[0]['name'] ?? null,
                'top_category_amount' => $topCategories[0]['amount'] ?? null,
                'expense_vs_previous' => $previous
                    ? MoneyMath::sub($summary['expense'], $previous['expense'])
                    : null,
            ],
        ];
    }

    /**
     * @param  list<array{month: string, income: string, expense: string, balance: string}>  $trend
     * @return array{income: string, expense: string, balance: string}|null
     */
    private function previousMonthPoint(array $trend, string $currentPeriod): ?array
    {
        $previousKey = Carbon::createFromFormat('Y-m', $currentPeriod)->subMonthNoOverflow()->format('Y-m');

        foreach ($trend as $point) {
            if (($point['month'] ?? null) === $previousKey) {
                return [
                    'income' => $point['income'],
                    'expense' => $point['expense'],
                    'balance' => $point['balance'],
                ];
            }
        }

        return null;
    }
}

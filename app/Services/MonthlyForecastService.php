<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Current-month pace forecast: “Where will I end this month?”
 *
 * Distinct from Intelligence ForecastService (next-month historical average).
 *
 * Forecast expenses = (MTD expenses ÷ days_elapsed) × days_in_month
 * Expected savings  = MTD income − forecast expenses
 *   (does not invent future income — uses known month-to-date income only)
 *
 * Budget projection per category uses the same pace formula vs current-month limit.
 */
class MonthlyForecastService
{
    /** Days elapsed at/below this are marked as early-month (estimate may swing). */
    public const EARLY_MONTH_DAY_THRESHOLD = 3;

    public function __construct(
        private readonly DashboardAnalyticsService $analytics,
        private readonly BudgetService $budgets,
    ) {}

    /**
     * Full Month Forecast payload for the authenticated user.
     *
     * @param  array<string, mixed>|null  $dashboard  Optional pre-built analytics to reuse daily trend / budgets.
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null, ?array $dashboard = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $today = $now->copy()->startOfDay();

        $daysInMonth = (int) $monthEnd->day;
        $daysElapsed = max(1, min((int) $today->day, $daysInMonth));
        $daysRemaining = max(0, $daysInMonth - $daysElapsed);

        $dashboard ??= $this->analytics->forUser($user, $now);
        $dailyTrend = is_array($dashboard['daily_expense_trend'] ?? null)
            ? $dashboard['daily_expense_trend']
            : $this->analytics->dailyExpenseTrend($user, $monthStart->toDateString(), $today->toDateString());

        $mtdExpenses = $this->sumDailyAmounts($dailyTrend);
        $mtdIncome = $this->incomeThrough($user, $monthStart->toDateString(), $today->toDateString());

        $budgetRows = is_array($dashboard['budgets'] ?? null)
            ? $dashboard['budgets']
            : $this->budgets->currentMonthBudgetVsActual($user, $now);

        if (MoneyMath::compare($mtdExpenses, '0.00') <= 0) {
            return $this->insufficientPayload(
                $now,
                $daysElapsed,
                $daysInMonth,
                $daysRemaining,
                $mtdExpenses,
                $mtdIncome,
                'Start tracking your spending this month to see an end-of-month estimate.',
            );
        }

        $dailyPace = $this->divide($mtdExpenses, $daysElapsed);
        $forecastExpenses = $this->multiply($dailyPace, $daysInMonth);
        $expectedSavings = MoneyMath::sub($mtdIncome, $forecastExpenses);

        $categoryForecasts = $this->categoryForecasts($budgetRows, $daysElapsed, $daysInMonth);
        $budgetAlert = $this->selectBudgetAlert($categoryForecasts);
        $status = $this->resolveStatus($budgetAlert, $categoryForecasts);
        $series = $this->buildSeries($dailyTrend, $dailyPace, $mtdExpenses, $forecastExpenses, $today, $monthEnd);

        $earlyMonth = $daysElapsed <= self::EARLY_MONTH_DAY_THRESHOLD;

        return [
            'available' => true,
            'status' => $status['status'],
            'status_label' => $status['label'],
            'status_indicator' => $status['indicator'],
            'status_message' => $status['message'],
            'period' => [
                'month' => $monthStart->format('Y-m'),
                'label' => $monthStart->format('F Y'),
                'days_elapsed' => $daysElapsed,
                'days_in_month' => $daysInMonth,
                'days_remaining' => $daysRemaining,
                'today' => $today->toDateString(),
                'early_month' => $earlyMonth,
            ],
            'actual' => [
                'expenses' => $mtdExpenses,
                'income' => $mtdIncome,
            ],
            'pace' => [
                'daily_expenses' => $dailyPace,
            ],
            'forecast' => [
                'expenses' => $forecastExpenses,
                'expected_savings' => $expectedSavings,
                'expected_savings_available' => true,
            ],
            'budget_alert' => $budgetAlert,
            'category_forecasts' => $categoryForecasts,
            'series' => $series,
            'explanation' => sprintf(
                'Your current monthly spending is Rs. %s after %d day%s. That\'s about Rs. %s per day. At the same pace for the full %d-day month, estimated expenses are Rs. %s.',
                number_format((float) $mtdExpenses, 2),
                $daysElapsed,
                $daysElapsed === 1 ? '' : 's',
                number_format((float) $dailyPace, 2),
                $daysInMonth,
                number_format((float) $forecastExpenses, 2),
            ),
            'savings_explanation' => sprintf(
                'Expected savings = known month-to-date income (Rs. %s) − forecast expenses (Rs. %s). Future income is not invented.',
                number_format((float) $mtdIncome, 2),
                number_format((float) $forecastExpenses, 2),
            ),
            'formula' => 'Forecast expenses = (month-to-date expenses ÷ days elapsed) × days in month.',
            'disclaimer' => 'Forecast is based on your spending pace so far this month. It is an estimate, not a guarantee.',
            'early_month_note' => $earlyMonth
                ? 'Early-month estimate — this forecast may change significantly as more spending data is recorded.'
                : null,
        ];
    }

    /**
     * Compact dashboard card payload (reuses full forecast).
     *
     * @param  array<string, mixed>|null  $dashboard
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user, ?array $dashboard = null, ?CarbonInterface $now = null): array
    {
        return $this->forUser($user, $now, $dashboard);
    }

    /**
     * @param  list<array{date?: string, amount?: string}>  $dailyTrend
     */
    private function sumDailyAmounts(array $dailyTrend): string
    {
        $total = '0.00';
        foreach ($dailyTrend as $point) {
            $total = MoneyMath::add($total, MoneyMath::normalize($point['amount'] ?? 0));
        }

        return $total;
    }

    private function incomeThrough(User $user, string $from, string $to): string
    {
        $total = $user->transactions()
            ->where('type', TransactionType::Income->value)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->sum('amount');

        return MoneyMath::normalize($total);
    }

    /**
     * @param  list<array<string, mixed>>  $budgetRows
     * @return list<array<string, mixed>>
     */
    private function categoryForecasts(array $budgetRows, int $daysElapsed, int $daysInMonth): array
    {
        $rows = [];

        foreach ($budgetRows as $budget) {
            $limit = MoneyMath::normalize($budget['limit'] ?? 0);
            $spent = MoneyMath::normalize($budget['spent'] ?? 0);
            $categoryName = is_array($budget['category'] ?? null)
                ? (string) ($budget['category']['name'] ?? 'Category')
                : 'Category';

            if (MoneyMath::compare($limit, '0.00') <= 0) {
                continue;
            }

            $alreadyOver = MoneyMath::compare($spent, $limit) > 0;
            $currentOverrun = $alreadyOver ? MoneyMath::sub($spent, $limit) : '0.00';

            $pace = $this->divide($spent, $daysElapsed);
            $forecast = $this->multiply($pace, $daysInMonth);
            $projectedOverrunRaw = MoneyMath::sub($forecast, $limit);
            $projectedOver = MoneyMath::compare($projectedOverrunRaw, '0.00') > 0;
            $projectedOverrun = $projectedOver ? $projectedOverrunRaw : '0.00';

            $rows[] = [
                'budget_id' => $budget['id'] ?? null,
                'category_name' => $categoryName,
                'limit' => $limit,
                'spent' => $spent,
                'forecast' => $forecast,
                'already_over' => $alreadyOver,
                'current_overrun' => $currentOverrun,
                'projected_over' => $projectedOver && ! $alreadyOver,
                'projected_overrun' => $projectedOverrun,
                'status' => $alreadyOver
                    ? 'already_over'
                    : ($projectedOver ? 'projected_over' : 'on_track'),
            ];
        }

        return $rows;
    }

    /**
     * Deterministic alert: already-over budgets preferred by largest current overrun;
     * otherwise largest projected overrun. Null when nothing to warn about.
     *
     * @param  list<array<string, mixed>>  $categoryForecasts
     * @return array<string, mixed>|null
     */
    private function selectBudgetAlert(array $categoryForecasts): ?array
    {
        $already = array_values(array_filter($categoryForecasts, fn (array $row) => ($row['already_over'] ?? false) === true));
        if ($already !== []) {
            usort($already, fn (array $a, array $b) => MoneyMath::compare($b['current_overrun'], $a['current_overrun']));
            $top = $already[0];

            return [
                'available' => true,
                'kind' => 'already_over',
                'category' => $top['category_name'],
                'budget' => $top['limit'],
                'spent' => $top['spent'],
                'forecast' => $top['forecast'],
                'overrun' => $top['current_overrun'],
                'message' => sprintf(
                    '%s is currently Rs. %s over budget.',
                    $top['category_name'],
                    number_format((float) $top['current_overrun'], 2),
                ),
            ];
        }

        $projected = array_values(array_filter($categoryForecasts, fn (array $row) => ($row['projected_over'] ?? false) === true));
        if ($projected === []) {
            return null;
        }

        usort($projected, fn (array $a, array $b) => MoneyMath::compare($b['projected_overrun'], $a['projected_overrun']));
        $top = $projected[0];

        return [
            'available' => true,
            'kind' => 'projected_over',
            'category' => $top['category_name'],
            'budget' => $top['limit'],
            'spent' => $top['spent'],
            'forecast' => $top['forecast'],
            'overrun' => $top['projected_overrun'],
            'message' => sprintf(
                'You may exceed your %s budget by Rs. %s.',
                $top['category_name'],
                number_format((float) $top['projected_overrun'], 2),
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $categoryForecasts
     * @param  array<string, mixed>|null  $budgetAlert
     * @return array{status: string, label: string, indicator: string, message: string}
     */
    private function resolveStatus(?array $budgetAlert, array $categoryForecasts): array
    {
        if ($budgetAlert !== null && ($budgetAlert['kind'] ?? '') === 'already_over') {
            return [
                'status' => 'already_over_budget',
                'label' => 'Already over budget',
                'indicator' => 'orange',
                'message' => (string) $budgetAlert['message'],
            ];
        }

        if ($budgetAlert !== null && ($budgetAlert['kind'] ?? '') === 'projected_over') {
            return [
                'status' => 'budget_watch',
                'label' => 'Budget watch',
                'indicator' => 'amber',
                'message' => (string) $budgetAlert['message'],
            ];
        }

        if ($categoryForecasts !== []) {
            return [
                'status' => 'on_track',
                'label' => 'On track',
                'indicator' => 'green',
                'message' => 'You\'re on track to stay within your current budgets at this pace.',
            ];
        }

        return [
            'status' => 'pace_only',
            'label' => 'Pace estimate',
            'indicator' => 'neutral',
            'message' => 'No current-month budgets set — showing spending pace only.',
        ];
    }

    /**
     * Actual cumulative through today + projected cumulative to month-end.
     *
     * @param  list<array{date?: string, amount?: string}>  $dailyTrend
     * @return list<array{date: string, label: string, actual: string|null, forecast: string|null, phase: string}>
     */
    private function buildSeries(
        array $dailyTrend,
        string $dailyPace,
        string $mtdExpenses,
        string $forecastExpenses,
        Carbon $today,
        Carbon $monthEnd,
    ): array {
        $series = [];
        $running = '0.00';

        foreach ($dailyTrend as $point) {
            $date = (string) ($point['date'] ?? '');
            $running = MoneyMath::add($running, MoneyMath::normalize($point['amount'] ?? 0));
            $series[] = [
                'date' => $date,
                'label' => $date !== '' ? Carbon::parse($date)->format('j') : '',
                'actual' => $running,
                'forecast' => $date === $today->toDateString() ? $running : null,
                'phase' => 'actual',
            ];
        }

        $cursor = $today->copy()->addDay();
        while ($cursor->lte($monthEnd)) {
            $daysFromStart = max(1, (int) $cursor->day);
            $projected = $this->multiply($dailyPace, $daysFromStart);
            // Cap end point at the overall month forecast for clarity.
            if ($cursor->isSameDay($monthEnd)) {
                $projected = $forecastExpenses;
            }

            $series[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('j'),
                'actual' => null,
                'forecast' => $projected,
                'phase' => 'forecast',
            ];
            $cursor->addDay();
        }

        // Ensure today has forecast = actual so the line connects.
        foreach ($series as $index => $point) {
            if (($point['date'] ?? null) === $today->toDateString()) {
                $series[$index]['forecast'] = $mtdExpenses;
                break;
            }
        }

        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    private function insufficientPayload(
        Carbon $now,
        int $daysElapsed,
        int $daysInMonth,
        int $daysRemaining,
        string $mtdExpenses,
        string $mtdIncome,
        string $message,
    ): array {
        return [
            'available' => false,
            'status' => 'insufficient',
            'status_label' => 'Not enough data yet',
            'status_indicator' => 'neutral',
            'status_message' => $message,
            'period' => [
                'month' => $now->format('Y-m'),
                'label' => $now->format('F Y'),
                'days_elapsed' => $daysElapsed,
                'days_in_month' => $daysInMonth,
                'days_remaining' => $daysRemaining,
                'today' => $now->toDateString(),
                'early_month' => $daysElapsed <= self::EARLY_MONTH_DAY_THRESHOLD,
            ],
            'actual' => [
                'expenses' => $mtdExpenses,
                'income' => $mtdIncome,
            ],
            'pace' => [
                'daily_expenses' => null,
            ],
            'forecast' => [
                'expenses' => null,
                'expected_savings' => null,
                'expected_savings_available' => false,
            ],
            'budget_alert' => null,
            'category_forecasts' => [],
            'series' => [],
            'explanation' => $message,
            'savings_explanation' => null,
            'formula' => 'Forecast expenses = (month-to-date expenses ÷ days elapsed) × days in month.',
            'disclaimer' => 'Forecast is based on your spending pace so far this month. It is an estimate, not a guarantee.',
            'early_month_note' => null,
        ];
    }

    private function divide(string $amount, int $divisor): string
    {
        $amount = MoneyMath::normalize($amount);
        if ($divisor <= 0) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            return MoneyMath::normalize(bcdiv($amount, (string) $divisor, 4));
        }

        return MoneyMath::normalize((float) $amount / $divisor);
    }

    private function multiply(string $amount, int|float $factor): string
    {
        $amount = MoneyMath::normalize($amount);

        if (function_exists('bcmul')) {
            return MoneyMath::normalize(bcmul($amount, (string) $factor, 4));
        }

        return MoneyMath::normalize((float) $amount * (float) $factor);
    }
}

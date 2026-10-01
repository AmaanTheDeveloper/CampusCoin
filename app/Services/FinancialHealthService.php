<?php

namespace App\Services;

use App\Models\User;
use App\Support\MoneyMath;
use Carbon\CarbonInterface;

/**
 * Deterministic “Your Money Pulse” financial-health metrics.
 *
 * All scores are explainable formulas over the authenticated user’s
 * existing CampusCoin data (monthly trend, budgets, profile savings goal).
 * No AI / random / demo values.
 *
 * Status thresholds (overall average of *available* metrics only):
 *   80–100 → Healthy
 *   60–79  → Watchful
 *    0–59  → Needs Attention
 */
class FinancialHealthService
{
    public const STATUS_HEALTHY_MIN = 80;

    public const STATUS_WATCHFUL_MIN = 60;

    /** Absolute % change below which spending trend is treated as Stable. */
    public const TREND_TOLERANCE_PERCENT = 5.0;

    /** Minimum months with income before savings consistency is scored. */
    public const MIN_SAVINGS_PERIODS = 2;

    /** Minimum months with expense before spending stability is scored. */
    public const MIN_STABILITY_PERIODS = 2;

    /**
     * Build Money Pulse payload from an already-built dashboard analytics array.
     * Reuses monthly_trend / budgets / summary — no extra aggregate queries.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user, array $dashboard, ?CarbonInterface $now = null): array
    {
        $monthlyTrend = is_array($dashboard['monthly_trend'] ?? null) ? $dashboard['monthly_trend'] : [];
        $budgets = is_array($dashboard['budgets'] ?? null) ? $dashboard['budgets'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $hasTransactions = (bool) ($dashboard['has_transactions'] ?? false);

        $savings = $this->calculateSavingsConsistency($monthlyTrend);
        $budget = $this->calculateBudgetDiscipline($budgets);
        $stability = $this->calculateSpendingStability($monthlyTrend);
        $goal = $this->calculateGoalProgress($user, $summary);
        $trend = $this->calculateSpendingTrend($monthlyTrend);

        $overall = $this->calculateOverallStatus([
            $savings,
            $budget,
            $stability,
            $goal,
        ], $hasTransactions);

        return [
            'overall' => $overall,
            'metrics' => [
                'savings_consistency' => $savings,
                'budget_discipline' => $budget,
                'spending_stability' => $stability,
                'goal_progress' => $goal,
            ],
            'spending_trend' => $trend,
            'disclaimer' => 'Based on your recent income, spending, budgets and goals. No AI or hidden scoring is used.',
            'period_label' => is_array($dashboard['period'] ?? null)
                ? (string) ($dashboard['period']['label'] ?? '')
                : '',
        ];
    }

    /**
     * Savings Consistency = (months with positive balance ÷ months with income) × 100.
     *
     * Uses dashboard monthly_trend points. CampusCoin defines monthly
     * savings ≡ balance ≡ income − expenses (see DashboardAnalyticsService).
     * Eligible period: calendar month with income > 0.
     * Saved period: that month’s balance > 0.
     * Requires at least MIN_SAVINGS_PERIODS eligible months.
     *
     * @param  list<array{month?: string, income?: string, expense?: string, balance?: string}>  $monthlyTrend
     * @return array<string, mixed>
     */
    public function calculateSavingsConsistency(array $monthlyTrend): array
    {
        $eligible = 0;
        $saved = 0;

        foreach ($monthlyTrend as $point) {
            $income = MoneyMath::normalize($point['income'] ?? 0);
            if (MoneyMath::compare($income, '0.00') <= 0) {
                continue;
            }

            $eligible++;
            $balance = MoneyMath::normalize($point['balance'] ?? MoneyMath::sub($income, MoneyMath::normalize($point['expense'] ?? 0)));
            if (MoneyMath::compare($balance, '0.00') > 0) {
                $saved++;
            }
        }

        if ($eligible < self::MIN_SAVINGS_PERIODS) {
            return $this->unavailableMetric(
                'Savings consistency',
                $eligible === 0
                    ? 'Not enough income history yet. Record income in at least two months to unlock this metric.'
                    : sprintf(
                        'Only %d eligible month with income so far. Need at least %d to measure consistency.',
                        $eligible,
                        self::MIN_SAVINGS_PERIODS,
                    ),
                'Score = (months with positive monthly balance ÷ months with income) × 100. Monthly balance = income − expenses. Requires at least 2 months with income.',
                [
                    'eligible_periods' => $eligible,
                    'saved_periods' => $saved,
                ],
            );
        }

        $score = $this->clampPercent((int) round(($saved / $eligible) * 100));

        return [
            'available' => true,
            'score' => $score,
            'label' => 'Savings consistency',
            'summary' => sprintf('%d%%', $score),
            'explanation' => sprintf(
                'You finished with a positive monthly balance (income − expenses) in %d of your last %d months that had income.',
                $saved,
                $eligible,
            ),
            'formula' => 'Savings Consistency = (months with balance > 0 ÷ months with income > 0) × 100. Balance matches dashboard savings (income − expenses).',
            'detail' => [
                'eligible_periods' => $eligible,
                'saved_periods' => $saved,
            ],
        ];
    }

    /**
     * Budget Discipline from current-month budget utilization.
     *
     * Per budget: adherence = clamp(100 − max(0, utilization% − 100), 0, 100)
     *   within/under limit → 100
     *   10% over → 90
     *   100%+ over → 0
     * Score = average adherence across budgets. No budgets → unavailable (not scored as failure).
     *
     * @param  list<array{limit?: string, spent?: string, percentage?: float|int}>  $budgets
     * @return array<string, mixed>
     */
    public function calculateBudgetDiscipline(array $budgets): array
    {
        if ($budgets === []) {
            return $this->unavailableMetric(
                'Budget discipline',
                'No budget data. Set category budgets to unlock this metric.',
                'Score = average of per-budget adherence: clamp(100 − overspend%, 0, 100), where overspend% = max(0, spent/limit×100 − 100).',
                [
                    'budget_count' => 0,
                    'within_count' => 0,
                ],
            );
        }

        $scores = [];
        $within = 0;

        foreach ($budgets as $budget) {
            $limit = MoneyMath::normalize($budget['limit'] ?? 0);
            $spent = MoneyMath::normalize($budget['spent'] ?? 0);

            if (MoneyMath::compare($limit, '0.00') <= 0) {
                continue;
            }

            $utilization = MoneyMath::percentOf($spent, $limit) ?? 0.0;
            $overshoot = max(0.0, $utilization - 100.0);
            $adherence = $this->clampPercent((int) round(100.0 - $overshoot));
            $scores[] = $adherence;

            if (MoneyMath::compare($spent, $limit) <= 0) {
                $within++;
            }
        }

        if ($scores === []) {
            return $this->unavailableMetric(
                'Budget discipline',
                'No budget data. Set category budgets to unlock this metric.',
                'Score = average of per-budget adherence: clamp(100 − overspend%, 0, 100).',
                [
                    'budget_count' => 0,
                    'within_count' => 0,
                ],
            );
        }

        $score = $this->clampPercent((int) round(array_sum($scores) / count($scores)));
        $count = count($scores);

        return [
            'available' => true,
            'score' => $score,
            'label' => 'Budget discipline',
            'summary' => sprintf('%d%%', $score),
            'explanation' => sprintf(
                'You stayed within %d of %d current-month budget%s. Overspending lowers this score gradually.',
                $within,
                $count,
                $count === 1 ? '' : 's',
            ),
            'formula' => 'Per budget: adherence = clamp(100 − max(0, spent/limit×100 − 100), 0, 100). Budget discipline = average adherence.',
            'detail' => [
                'budget_count' => $count,
                'within_count' => $within,
            ],
        ];
    }

    /**
     * Spending Stability from month-to-month expense volatility (NOT “spend less”).
     *
     * Uses expense totals from months with expense > 0.
     * CV = stdev / mean. Stability = clamp(round(100 × (1 − min(CV, 1))), 0, 100).
     * CV 0 → 100 (perfectly stable). CV ≥ 1 → 0 (highly volatile).
     *
     * @param  list<array{expense?: string}>  $monthlyTrend
     * @return array<string, mixed>
     */
    public function calculateSpendingStability(array $monthlyTrend): array
    {
        $expenses = [];

        foreach ($monthlyTrend as $point) {
            $expense = MoneyMath::normalize($point['expense'] ?? 0);
            if (MoneyMath::compare($expense, '0.00') > 0) {
                $expenses[] = (float) $expense;
            }
        }

        $count = count($expenses);

        if ($count < self::MIN_STABILITY_PERIODS) {
            return $this->unavailableMetric(
                'Spending stability',
                $count === 0
                    ? 'Not enough spending history yet. Record expenses in at least two months to unlock this metric.'
                    : sprintf(
                        'Only %d month with expenses so far. Need at least %d to measure stability.',
                        $count,
                        self::MIN_STABILITY_PERIODS,
                    ),
                'Stability = clamp(100 × (1 − min(CV, 1)), 0, 100) where CV = stdev(monthly expenses) ÷ average expense.',
                [
                    'period_count' => $count,
                    'coefficient_of_variation' => null,
                ],
            );
        }

        $mean = array_sum($expenses) / $count;

        if ($mean <= 0.0) {
            return $this->unavailableMetric(
                'Spending stability',
                'Average spending is zero, so volatility cannot be measured yet.',
                'Stability uses CV = stdev ÷ mean; mean must be greater than zero.',
                [
                    'period_count' => $count,
                    'coefficient_of_variation' => null,
                ],
            );
        }

        $variance = 0.0;
        foreach ($expenses as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $stdev = sqrt($variance / $count);
        $cv = $stdev / $mean;
        $score = $this->clampPercent((int) round(100.0 * (1.0 - min($cv, 1.0))));

        $tone = $score >= 80
            ? 'Your spending has remained relatively consistent over recent months.'
            : ($score >= 60
                ? 'Your spending fluctuates moderately from month to month.'
                : 'Your spending has been quite volatile across recent months.');

        return [
            'available' => true,
            'score' => $score,
            'label' => 'Spending stability',
            'summary' => sprintf('%d%%', $score),
            'explanation' => $tone.' Higher scores mean steadier monthly totals, not necessarily lower spending.',
            'formula' => 'CV = stdev(monthly expenses) ÷ average. Stability = clamp(100 × (1 − min(CV, 1)), 0, 100).',
            'detail' => [
                'period_count' => $count,
                'coefficient_of_variation' => round($cv, 4),
                'average_expense' => MoneyMath::normalize($mean),
            ],
        ];
    }

    /**
     * Goal Progress against the profile savings_goal.
     *
     * CampusCoin treats savings_goal as a monthly target compared to the
     * current calendar-month balance (same basis as SavingTipsService).
     * Progress = clamp((current-month balance ÷ savings_goal) × 100, 0, 100).
     * Negative balance → 0. No goal / goal ≤ 0 → unavailable.
     *
     * @param  array{balance?: string}  $summary
     * @return array<string, mixed>
     */
    public function calculateGoalProgress(User $user, array $summary): array
    {
        $goal = MoneyMath::normalize($user->savings_goal ?? 0);

        if (MoneyMath::compare($goal, '0.00') <= 0) {
            return $this->unavailableMetric(
                'Goal progress',
                'No savings goal set. Add one in your profile to compare this month’s balance against your target.',
                'Progress = clamp((current-month balance ÷ profile savings goal) × 100, 0, 100). Same monthly comparison as CampusCoin saving tips.',
                [
                    'goal' => null,
                    'balance' => MoneyMath::normalize($summary['balance'] ?? 0),
                    'progress_raw' => null,
                ],
            );
        }

        $balance = MoneyMath::normalize($summary['balance'] ?? 0);
        $raw = MoneyMath::percentOf($balance, $goal);

        if ($raw === null || MoneyMath::compare($balance, '0.00') < 0) {
            $score = 0;
        } else {
            $score = $this->clampPercent((int) round($raw));
        }

        return [
            'available' => true,
            'score' => $score,
            'label' => 'Goal progress',
            'summary' => sprintf('%d%%', $score),
            'explanation' => sprintf(
                'Your current monthly balance (Rs. %s) is %d%% of your profile savings goal (Rs. %s).',
                $balance,
                $score,
                $goal,
            ),
            'formula' => 'Goal Progress = clamp((current-month balance ÷ profile savings goal) × 100, 0, 100). Uses this month’s income − expenses vs the profile savings goal (same basis as saving tips).',
            'detail' => [
                'goal' => $goal,
                'balance' => $balance,
                'progress_raw' => $raw,
            ],
        ];
    }

    /**
     * Spending trend: current calendar month expense vs previous month.
     *
     * change% = (recent − previous) ÷ previous × 100
     *   ≤ −TREND_TOLERANCE → Improving
     *   ≥ +TREND_TOLERANCE → Increasing
     *   otherwise → Stable
     * Previous expense = 0 and recent > 0 → Increasing.
     * Both months at 0 expense → unavailable (no real comparison).
     *
     * @param  list<array{month?: string, label?: string, expense?: string}>  $monthlyTrend
     * @return array<string, mixed>
     */
    public function calculateSpendingTrend(array $monthlyTrend): array
    {
        $count = count($monthlyTrend);

        if ($count < 2) {
            return [
                'available' => false,
                'label' => null,
                'change_percentage' => null,
                'recent_expense' => null,
                'previous_expense' => null,
                'explanation' => 'Need at least two months of history to compare spending.',
                'formula' => sprintf(
                    'Compare current vs previous month expense. |change| < %s%% → Stable; decrease → Improving; increase → Increasing.',
                    rtrim(rtrim(number_format(self::TREND_TOLERANCE_PERCENT, 1), '0'), '.'),
                ),
            ];
        }

        $recent = $monthlyTrend[$count - 1];
        $previous = $monthlyTrend[$count - 2];
        $recentExpense = MoneyMath::normalize($recent['expense'] ?? 0);
        $previousExpense = MoneyMath::normalize($previous['expense'] ?? 0);

        // Both months empty → no real comparison (avoids fake "Stable" for new users).
        if (MoneyMath::compare($previousExpense, '0.00') === 0 && MoneyMath::compare($recentExpense, '0.00') === 0) {
            return [
                'available' => false,
                'label' => null,
                'change_percentage' => null,
                'recent_expense' => $recentExpense,
                'previous_expense' => $previousExpense,
                'recent_label' => (string) ($recent['label'] ?? $recent['month'] ?? 'Current'),
                'previous_label' => (string) ($previous['label'] ?? $previous['month'] ?? 'Previous'),
                'explanation' => 'Not enough spending history to compare months yet.',
                'formula' => sprintf(
                    'Compare current vs previous month expense. |change| < %s%% → Stable; decrease → Improving; increase → Increasing.',
                    rtrim(rtrim(number_format(self::TREND_TOLERANCE_PERCENT, 1), '0'), '.'),
                ),
            ];
        }

        if (MoneyMath::compare($previousExpense, '0.00') === 0) {
            $label = 'Increasing';
            $change = null;
            $explanation = sprintf(
                'Previous month had no expenses; current month spending is Rs. %s.',
                $recentExpense,
            );
        } else {
            $change = MoneyMath::percentOf(MoneyMath::sub($recentExpense, $previousExpense), $previousExpense) ?? 0.0;

            if ($change <= -self::TREND_TOLERANCE_PERCENT) {
                $label = 'Improving';
                $explanation = sprintf(
                    'Spending is down %.1f%% vs the previous month (Rs. %s → Rs. %s).',
                    abs($change),
                    $previousExpense,
                    $recentExpense,
                );
            } elseif ($change >= self::TREND_TOLERANCE_PERCENT) {
                $label = 'Increasing';
                $explanation = sprintf(
                    'Spending is up %.1f%% vs the previous month (Rs. %s → Rs. %s).',
                    $change,
                    $previousExpense,
                    $recentExpense,
                );
            } else {
                $label = 'Stable';
                $explanation = sprintf(
                    'Spending changed by %.1f%% vs the previous month — within the ±%s%% stability band (Rs. %s → Rs. %s).',
                    $change,
                    rtrim(rtrim(number_format(self::TREND_TOLERANCE_PERCENT, 1), '0'), '.'),
                    $previousExpense,
                    $recentExpense,
                );
            }
        }

        return [
            'available' => true,
            'label' => $label,
            'change_percentage' => $change,
            'recent_expense' => $recentExpense,
            'previous_expense' => $previousExpense,
            'recent_label' => (string) ($recent['label'] ?? $recent['month'] ?? 'Current'),
            'previous_label' => (string) ($previous['label'] ?? $previous['month'] ?? 'Previous'),
            'explanation' => $explanation,
            'formula' => sprintf(
                'change%% = (current month expense − previous) ÷ previous × 100. ≤ −%s%% Improving; ≥ +%s%% Increasing; else Stable.',
                rtrim(rtrim(number_format(self::TREND_TOLERANCE_PERCENT, 1), '0'), '.'),
                rtrim(rtrim(number_format(self::TREND_TOLERANCE_PERCENT, 1), '0'), '.'),
            ),
        ];
    }

    /**
     * Overall status = average of available metric scores (missing metrics excluded, not zeroed).
     *
     * @param  list<array{available: bool, score?: int|null}>  $metrics
     * @return array<string, mixed>
     */
    public function calculateOverallStatus(array $metrics, bool $hasTransactions): array
    {
        $scores = [];

        foreach ($metrics as $metric) {
            if (($metric['available'] ?? false) === true && isset($metric['score']) && is_int($metric['score'])) {
                $scores[] = $metric['score'];
            }
        }

        if ($scores === []) {
            return [
                'status' => 'insufficient',
                'status_label' => 'Not enough data yet',
                'score' => null,
                'indicator' => 'neutral',
                'explanation' => $hasTransactions
                    ? 'Some activity is recorded, but not enough history yet for a full Money Pulse. Keep tracking to unlock more metrics.'
                    : 'Add a few transactions to see your financial health.',
                'formula' => sprintf(
                    'Overall = average of available metrics. %d–100 Healthy; %d–%d Watchful; 0–%d Needs Attention. Unavailable metrics are skipped.',
                    self::STATUS_HEALTHY_MIN,
                    self::STATUS_WATCHFUL_MIN,
                    self::STATUS_HEALTHY_MIN - 1,
                    self::STATUS_WATCHFUL_MIN - 1,
                ),
                'metrics_used' => 0,
            ];
        }

        $score = $this->clampPercent((int) round(array_sum($scores) / count($scores)));

        if ($score >= self::STATUS_HEALTHY_MIN) {
            $status = 'healthy';
            $label = 'Healthy';
            $indicator = 'green';
        } elseif ($score >= self::STATUS_WATCHFUL_MIN) {
            $status = 'watchful';
            $label = 'Watchful';
            $indicator = 'amber';
        } else {
            $status = 'needs_attention';
            $label = 'Needs Attention';
            $indicator = 'red';
        }

        return [
            'status' => $status,
            'status_label' => $label,
            'score' => $score,
            'indicator' => $indicator,
            'explanation' => sprintf(
                'Overall score %d from %d available metric%s (unavailable metrics are not counted as zero).',
                $score,
                count($scores),
                count($scores) === 1 ? '' : 's',
            ),
            'formula' => sprintf(
                'Overall = average of available metrics. %d–100 Healthy; %d–%d Watchful; 0–%d Needs Attention.',
                self::STATUS_HEALTHY_MIN,
                self::STATUS_WATCHFUL_MIN,
                self::STATUS_HEALTHY_MIN - 1,
                self::STATUS_WATCHFUL_MIN - 1,
            ),
            'metrics_used' => count($scores),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function unavailableMetric(string $label, string $explanation, string $formula, array $detail): array
    {
        return [
            'available' => false,
            'score' => null,
            'label' => $label,
            'summary' => match ($label) {
                'Budget discipline' => 'No budget data',
                'Goal progress' => 'No active goals',
                default => 'Still developing',
            },
            'explanation' => $explanation,
            'formula' => $formula,
            'detail' => $detail,
        ];
    }

    private function clampPercent(int $value): int
    {
        return max(0, min(100, $value));
    }
}

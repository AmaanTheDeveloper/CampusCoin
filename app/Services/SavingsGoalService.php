<?php

namespace App\Services;

use App\Enums\SavingsGoalStatus;
use App\Enums\TransactionType;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Long-term Savings Goal Tracker.
 *
 * Distinct from profile users.savings_goal (monthly Money Pulse target).
 *
 * Progress  = clamp((current_amount / target_amount) × 100, 0, 100)
 * Remaining = max(target − current, 0)
 * Completion months = ceil(remaining / monthly_savings_rate)
 *
 * Monthly savings rate prefers MonthlyForecastService expected_savings when positive;
 * falls back to average positive monthly balances from recent completed months.
 */
class SavingsGoalService
{
    /** Prefer these default expense categories for “reach faster” scenarios. */
    public const PREFERRED_SCENARIO_CATEGORIES = [
        'Entertainment',
        'Subscriptions',
        'Miscellaneous',
    ];

    /** Never suggest cutting these essential-leaning defaults. */
    public const EXCLUDED_SCENARIO_CATEGORIES = [
        'Hostel/Rent',
        'Academics',
        'Food',
        'Transport',
    ];

    public const SCENARIO_LOOKBACK_DAYS = 90;

    public const SCENARIO_REDUCTION_PERCENT = 15.0;

    public const SCENARIO_REDUCTION_MAX = '3000.00';

    public const SCENARIO_MIN_MONTHLY_AVG = '500.00';

    public const HISTORY_MIN_MONTHS = 2;

    public function __construct(
        private readonly MonthlyForecastService $monthForecast,
        private readonly DashboardAnalyticsService $analytics,
    ) {}

    /**
     * Dashboard / page payload for the user's single active (or latest completed) goal.
     *
     * @param  array<string, mixed>|null  $dashboard
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user, ?array $dashboard = null, ?CarbonInterface $now = null): array
    {
        $goal = $this->activeOrLatestGoal($user);

        if ($goal === null) {
            return [
                'has_goal' => false,
                'goal' => null,
                'empty_message' => 'Give your next big purchase a plan — laptop, course, trip, or emergency fund.',
                'cta' => 'Create Goal',
            ];
        }

        return [
            'has_goal' => true,
            'goal' => $this->serializeGoal($user, $goal, $dashboard, $now),
        ];
    }

    public function activeOrLatestGoal(User $user): ?SavingsGoal
    {
        $active = $user->savingsGoals()
            ->where('status', SavingsGoalStatus::Active->value)
            ->orderByDesc('id')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return $user->savingsGoals()->orderByDesc('id')->first();
    }

    /**
     * @throws ValidationException
     */
    public function createGoal(User $user, string $name, string $targetAmount, string $startingAmount = '0.00'): SavingsGoal
    {
        if ($user->savingsGoals()->where('status', SavingsGoalStatus::Active->value)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'You already have an active savings goal. Complete or edit it before creating another.',
            ]);
        }

        $target = MoneyMath::normalize($targetAmount);
        $starting = MoneyMath::normalize($startingAmount);

        return DB::transaction(function () use ($user, $name, $target, $starting) {
            $goal = $user->savingsGoals()->create([
                'name' => $name,
                'target_amount' => $target,
                'current_amount' => '0.00',
                'status' => SavingsGoalStatus::Active,
            ]);

            if (MoneyMath::compare($starting, '0.00') > 0) {
                $this->addContribution($user, $goal, $starting, 'Starting balance');
            } else {
                $this->refreshTotals($goal);
            }

            return $goal->fresh(['contributions']);
        });
    }

    public function updateGoal(User $user, SavingsGoal $goal, string $name, string $targetAmount): SavingsGoal
    {
        abort_unless($goal->user_id === $user->id, 404);

        $goal->update([
            'name' => $name,
            'target_amount' => MoneyMath::normalize($targetAmount),
        ]);

        $this->refreshTotals($goal);

        return $goal->fresh();
    }

    public function addContribution(User $user, SavingsGoal $goal, string $amount, ?string $note = null): SavingsGoalContribution
    {
        abort_unless($goal->user_id === $user->id, 404);

        $normalized = MoneyMath::normalize($amount);
        if (MoneyMath::compare($normalized, '0.00') <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Contribution amount must be greater than zero.',
            ]);
        }

        $contribution = $goal->contributions()->create([
            'amount' => $normalized,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'contributed_at' => now(),
        ]);

        $this->refreshTotals($goal);

        return $contribution;
    }

    public function refreshTotals(SavingsGoal $goal): void
    {
        $sum = MoneyMath::normalize(
            $goal->contributions()->sum('amount')
        );

        $target = MoneyMath::normalize($goal->target_amount);
        $status = MoneyMath::compare($sum, $target) >= 0
            ? SavingsGoalStatus::Completed
            : SavingsGoalStatus::Active;

        $goal->forceFill([
            'current_amount' => $sum,
            'status' => $status,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $dashboard
     * @return array<string, mixed>
     */
    public function serializeGoal(User $user, SavingsGoal $goal, ?array $dashboard = null, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $current = MoneyMath::normalize($goal->current_amount);
        $target = MoneyMath::normalize($goal->target_amount);
        $remaining = MoneyMath::compare($target, $current) > 0
            ? MoneyMath::sub($target, $current)
            : '0.00';
        $progress = MoneyMath::percentOf($current, $target);
        $progressClamped = $progress === null ? 0 : max(0, min(100, (int) round($progress)));
        $completed = $goal->status === SavingsGoalStatus::Completed
            || MoneyMath::compare($remaining, '0.00') === 0;

        $rateInfo = $completed
            ? ['available' => false, 'monthly_rate' => null, 'source' => null, 'explanation' => null]
            : $this->resolveMonthlySavingsRate($user, $dashboard, $now);

        $estimate = (! $completed && ($rateInfo['available'] ?? false) === true)
            ? $this->estimateCompletion($remaining, (string) $rateInfo['monthly_rate'], $now)
            : null;

        $scenario = (! $completed && ($rateInfo['available'] ?? false) === true && $estimate !== null)
            ? $this->buildSmartScenario($user, $remaining, (string) $rateInfo['monthly_rate'], $estimate, $now)
            : null;

        $contributions = $goal->contributions()
            ->orderByDesc('contributed_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (SavingsGoalContribution $row) => [
                'id' => $row->id,
                'amount' => MoneyMath::normalize($row->amount),
                'note' => $row->note,
                'contributed_at' => $row->contributed_at?->toIso8601String(),
            ])
            ->all();

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => $target,
            'current_amount' => $current,
            'remaining_amount' => $remaining,
            'progress_percent' => $progressClamped,
            'status' => $goal->status->value,
            'status_label' => $goal->status->label(),
            'is_completed' => $completed,
            'savings_rate' => $rateInfo,
            'completion_estimate' => $estimate,
            'smart_scenario' => $scenario,
            'contributions' => $contributions,
            'disclaimer' => 'Completion estimates use your current savings pace. They are estimates, not guarantees. profile.savings_goal (Money Pulse monthly target) is separate.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $dashboard
     * @return array{available: bool, monthly_rate: string|null, source: string|null, explanation: string|null}
     */
    public function resolveMonthlySavingsRate(User $user, ?array $dashboard = null, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $monthForecast = $this->monthForecast->forUser($user, $now, $dashboard);

        if (($monthForecast['available'] ?? false) === true) {
            $expected = MoneyMath::normalize($monthForecast['forecast']['expected_savings'] ?? 0);
            if (MoneyMath::compare($expected, '0.00') > 0) {
                return [
                    'available' => true,
                    'monthly_rate' => $expected,
                    'source' => 'month_forecast',
                    'explanation' => 'Using this month\'s expected savings from Month Forecast (MTD income − forecast expenses).',
                ];
            }
        }

        $dashboard ??= $this->analytics->forUser($user, $now);
        $trend = is_array($dashboard['monthly_trend'] ?? null) ? $dashboard['monthly_trend'] : [];
        $currentKey = $now->format('Y-m');
        $positive = [];

        foreach ($trend as $point) {
            $month = (string) ($point['month'] ?? '');
            if ($month === '' || $month === $currentKey) {
                continue; // completed months only for fallback
            }
            $balance = MoneyMath::normalize($point['balance'] ?? 0);
            if (MoneyMath::compare($balance, '0.00') > 0) {
                $positive[] = $balance;
            }
        }

        if (count($positive) < self::HISTORY_MIN_MONTHS) {
            return [
                'available' => false,
                'monthly_rate' => null,
                'source' => null,
                'explanation' => 'Not enough savings history to estimate completion yet. Keep tracking income and spending.',
            ];
        }

        $sum = '0.00';
        foreach ($positive as $value) {
            $sum = MoneyMath::add($sum, $value);
        }
        $avg = $this->divide($sum, count($positive));

        return [
            'available' => true,
            'monthly_rate' => $avg,
            'source' => 'historical_average',
            'explanation' => sprintf(
                'Using the average of your last %d completed months with positive savings.',
                count($positive),
            ),
        ];
    }

    /**
     * @return array{months: int, label: string, date: string, explanation: string}|null
     */
    public function estimateCompletion(string $remaining, string $monthlyRate, CarbonInterface $now): ?array
    {
        $remaining = MoneyMath::normalize($remaining);
        $monthlyRate = MoneyMath::normalize($monthlyRate);

        if (MoneyMath::compare($remaining, '0.00') <= 0 || MoneyMath::compare($monthlyRate, '0.00') <= 0) {
            return null;
        }

        $months = (int) ceil((float) $remaining / (float) $monthlyRate);
        $months = max(1, $months);
        $completion = Carbon::parse($now->toDateString())->startOfMonth()->addMonthsNoOverflow($months);

        return [
            'months' => $months,
            'label' => $completion->format('F Y'),
            'date' => $completion->format('Y-m-01'),
            'explanation' => sprintf(
                'At Rs. %s/month, Rs. %s remaining takes about %d month%s (ceil(remaining ÷ rate)).',
                number_format((float) $monthlyRate, 2),
                number_format((float) $remaining, 2),
                $months,
                $months === 1 ? '' : 's',
            ),
        ];
    }

    /**
     * @param  array{months: int, label: string, date: string, explanation: string}  $baselineEstimate
     * @return array<string, mixed>|null
     */
    public function buildSmartScenario(
        User $user,
        string $remaining,
        string $baselineRate,
        array $baselineEstimate,
        CarbonInterface $now,
    ): ?array {
        $category = $this->pickScenarioCategory($user, $now);
        if ($category === null) {
            return null;
        }

        $reduction = $this->suggestedReduction($category['monthly_average']);
        if (MoneyMath::compare($reduction, '0.00') <= 0) {
            return null;
        }

        $scenarioRate = MoneyMath::add($baselineRate, $reduction);
        $scenarioEstimate = $this->estimateCompletion($remaining, $scenarioRate, $now);
        if ($scenarioEstimate === null) {
            return null;
        }

        $monthsSaved = (int) $baselineEstimate['months'] - (int) $scenarioEstimate['months'];
        if ($monthsSaved < 1) {
            return null;
        }

        return [
            'available' => true,
            'category_name' => $category['name'],
            'category_monthly_average' => $category['monthly_average'],
            'suggested_reduction' => $reduction,
            'baseline_monthly_savings' => MoneyMath::normalize($baselineRate),
            'scenario_monthly_savings' => $scenarioRate,
            'baseline_months' => $baselineEstimate['months'],
            'scenario_months' => $scenarioEstimate['months'],
            'months_saved' => $monthsSaved,
            'baseline_label' => $baselineEstimate['label'],
            'scenario_label' => $scenarioEstimate['label'],
            'headline' => sprintf(
                'Reduce %s spending by about Rs. %s/month → goal reached ~%d month%s earlier',
                $category['name'],
                number_format((float) $reduction, 2),
                $monthsSaved,
                $monthsSaved === 1 ? '' : 's',
            ),
            'explanation' => sprintf(
                'One possible scenario based on your recent %s spending (avg Rs. %s/month over ~%d days). Not financial advice.',
                $category['name'],
                number_format((float) $category['monthly_average'], 2),
                self::SCENARIO_LOOKBACK_DAYS,
            ),
            'formula' => 'suggested_reduction ≈ 15% of category monthly average (capped). scenario_rate = baseline + reduction. months_saved = ceil(remaining/baseline) − ceil(remaining/scenario).',
        ];
    }

    /**
     * @return array{name: string, monthly_average: string}|null
     */
    private function pickScenarioCategory(User $user, CarbonInterface $now): ?array
    {
        $end = Carbon::parse($now->toDateString());
        $start = $end->copy()->subDays(self::SCENARIO_LOOKBACK_DAYS);

        $rows = $user->transactions()
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.type', TransactionType::Expense->value)
            ->whereDate('transactions.transaction_date', '>=', $start->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $end->toDateString())
            ->groupBy('transactions.category_id', 'categories.name')
            ->selectRaw('categories.name as category_name, SUM(transactions.amount) as total')
            ->orderByDesc(DB::raw('SUM(transactions.amount)'))
            ->get();

        $monthsSpan = max(1.0, self::SCENARIO_LOOKBACK_DAYS / 30.0);
        $candidates = [];

        foreach ($rows as $row) {
            $name = $row->category_name ?: 'Uncategorized';
            if (in_array($name, self::EXCLUDED_SCENARIO_CATEGORIES, true)) {
                continue;
            }

            $total = MoneyMath::normalize($row->total);
            $monthly = $this->divide($total, $monthsSpan);
            if (MoneyMath::compare($monthly, self::SCENARIO_MIN_MONTHLY_AVG) < 0) {
                continue;
            }

            $candidates[] = [
                'name' => $name,
                'monthly_average' => $monthly,
                'preferred' => in_array($name, self::PREFERRED_SCENARIO_CATEGORIES, true),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b) {
            if ($a['preferred'] !== $b['preferred']) {
                return $a['preferred'] ? -1 : 1;
            }

            return MoneyMath::compare($b['monthly_average'], $a['monthly_average']);
        });

        return [
            'name' => $candidates[0]['name'],
            'monthly_average' => $candidates[0]['monthly_average'],
        ];
    }

    private function suggestedReduction(string $monthlyAverage): string
    {
        $raw = (float) $monthlyAverage * (self::SCENARIO_REDUCTION_PERCENT / 100.0);
        // Round to nearest Rs. 100 for student-friendly amounts.
        $rounded = (string) (max(100, (int) (round($raw / 100) * 100)).'.00');
        $capped = MoneyMath::compare($rounded, self::SCENARIO_REDUCTION_MAX) > 0
            ? self::SCENARIO_REDUCTION_MAX
            : $rounded;

        // Never suggest more than half the category average.
        $half = MoneyMath::normalize((float) $monthlyAverage * 0.5);
        if (MoneyMath::compare($capped, $half) > 0) {
            $capped = MoneyMath::normalize((int) (round(((float) $half) / 100) * 100).'.00');
        }

        return MoneyMath::compare($capped, '0.00') > 0 ? $capped : '0.00';
    }

    private function divide(string $amount, int|float $divisor): string
    {
        $amount = MoneyMath::normalize($amount);
        if ((float) $divisor <= 0) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            return MoneyMath::normalize(bcdiv($amount, (string) $divisor, 4));
        }

        return MoneyMath::normalize((float) $amount / (float) $divisor);
    }
}

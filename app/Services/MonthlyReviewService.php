<?php

namespace App\Services;

use App\Enums\BudgetStatus;
use App\Enums\TransactionType;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Standalone Monthly Money Review.
 *
 * Reuses DashboardAnalyticsService summary/categoryBreakdown and BudgetService
 * enrichBudgets. Does not modify Money Pulse, Month Forecast, or other features.
 *
 * Saved = Income − Expenses (same CampusCoin semantics as dashboard balance).
 */
class MonthlyReviewService
{
    /** Minimum absolute spend (current month) for a category change to count. */
    public const CHANGE_MIN_AMOUNT = '500.00';

    /** Minimum |%| change to treat a category shift as meaningful. */
    public const CHANGE_MIN_PERCENT = 15.0;

    /** Overall expense MoM |%| below this → “relatively stable”. */
    public const OVERALL_STABLE_PERCENT = 5.0;

    /** Category share of expenses that counts as concentration. */
    public const CONCENTRATION_PERCENT = 40.0;

    /** Savings rate below this (with income) suggests a savings action. */
    public const LOW_SAVINGS_RATE_PERCENT = 10.0;

    /** Prefer these categories for discretionary action wording. */
    public const DISCRETIONARY_CATEGORIES = [
        'Entertainment',
        'Subscriptions',
        'Miscellaneous',
        'Food',
    ];

    public function __construct(
        private readonly DashboardAnalyticsService $analytics,
        private readonly BudgetService $budgets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?string $monthKey = null, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $selected = $this->resolveMonth($user, $monthKey, $now);
        $monthStart = $selected->copy()->startOfMonth()->toDateString();
        $monthEnd = $selected->copy()->endOfMonth()->toDateString();
        $previous = $selected->copy()->subMonthNoOverflow()->startOfMonth();
        $prevStart = $previous->toDateString();
        $prevEnd = $previous->copy()->endOfMonth()->toDateString();

        $summary = $this->analytics->summary($user, $monthStart, $monthEnd);
        $prevSummary = $this->analytics->summary($user, $prevStart, $prevEnd);
        $categories = $this->sortedCategories(
            $this->analytics->categoryBreakdown($user, $monthStart, $monthEnd, $summary['expense'])
        );
        $prevCategories = $this->analytics->categoryBreakdown($user, $prevStart, $prevEnd, $prevSummary['expense']);
        $topCategory = $categories[0] ?? null;
        $biggestChange = $this->largestCategoryChange($categories, $prevCategories);
        $budgetPerformance = $this->budgetPerformance($user, $monthStart);
        $overallTrend = $this->overallSpendingTrend($summary['expense'], $prevSummary['expense'], ((int) $prevSummary['transaction_count']) > 0);
        $halfMonth = $this->secondHalfEmphasis($user, $monthStart, $monthEnd, $topCategory);

        $reviewSummary = [
            'income' => $summary['income'],
            'expenses' => $summary['expense'],
            'saved' => $summary['savings'],
            'savings_rate' => $summary['savings_rate'],
            'transaction_count' => $summary['transaction_count'],
        ];

        $insight = $this->buildInsight($reviewSummary, $overallTrend, $topCategory, $biggestChange, $budgetPerformance, $halfMonth);
        $actions = $this->buildActions($reviewSummary, $categories, $biggestChange, $budgetPerformance);

        $hasActivity = ((int) $summary['transaction_count']) > 0;
        $navigation = $this->navigation($user, $selected, $now);

        return [
            'month' => [
                'key' => $selected->format('Y-m'),
                'label' => $selected->format('F Y'),
                'start' => $monthStart,
                'end' => $monthEnd,
                'timezone' => config('app.timezone'),
            ],
            'navigation' => $navigation,
            'has_activity' => $hasActivity,
            'empty_message' => $hasActivity
                ? null
                : 'No financial activity yet for this month. Keep tracking your income and expenses to unlock a fuller monthly review.',
            'summary' => $reviewSummary,
            'comparison' => $this->monthComparison($reviewSummary, [
                'income' => $prevSummary['income'],
                'expenses' => $prevSummary['expense'],
                'saved' => $prevSummary['savings'],
                'transaction_count' => $prevSummary['transaction_count'],
            ], $previous->format('F')),
            'top_category' => $topCategory
                ? [
                    'category_id' => $topCategory['category_id'],
                    'name' => $topCategory['name'],
                    'amount' => $topCategory['amount'],
                    'percentage' => $topCategory['percentage'],
                ]
                : null,
            'biggest_change' => $biggestChange,
            'budget_performance' => $budgetPerformance,
            'overall_trend' => $overallTrend,
            'insight' => $insight,
            'actions' => $actions,
            'disclaimer' => 'This review is built from your recorded transactions and budgets for the selected month. Narratives and actions are deterministic — not AI predictions.',
        ];
    }

    public function resolveMonth(User $user, ?string $monthKey, CarbonInterface $now): Carbon
    {
        if (is_string($monthKey) && preg_match('/^\d{4}-\d{2}$/', $monthKey) === 1) {
            $parsed = Carbon::createFromFormat('Y-m', $monthKey, config('app.timezone'));
            if ($parsed !== false) {
                $candidate = $parsed->startOfMonth();
                $currentStart = Carbon::parse($now->toDateTimeString())->startOfMonth();
                if ($candidate->lte($currentStart)) {
                    return $candidate;
                }
            }
        }

        $current = Carbon::parse($now->toDateTimeString())->startOfMonth();
        $currentEnd = $current->copy()->endOfMonth()->toDateString();
        $hasCurrent = $user->transactions()
            ->whereDate('transaction_date', '>=', $current->toDateString())
            ->whereDate('transaction_date', '<=', $currentEnd)
            ->exists();

        if ($hasCurrent) {
            return $current;
        }

        $latest = $user->transactions()
            ->orderByDesc('transaction_date')
            ->value('transaction_date');

        if ($latest !== null) {
            return Carbon::parse($latest)->startOfMonth();
        }

        return $current;
    }

    /**
     * @param  list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>  $categories
     * @return list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>
     */
    public function sortedCategories(array $categories): array
    {
        usort($categories, function (array $a, array $b) {
            $cmp = MoneyMath::compare($b['amount'], $a['amount']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return array_values($categories);
    }

    /**
     * @param  list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>  $current
     * @param  list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>  $previous
     * @return array<string, mixed>|null
     */
    public function largestCategoryChange(array $current, array $previous): ?array
    {
        $prevMap = [];
        foreach ($previous as $row) {
            $key = $this->categoryKey($row);
            $prevMap[$key] = $row;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($current as $row) {
            $key = $this->categoryKey($row);
            $currentAmount = MoneyMath::normalize($row['amount']);
            if (MoneyMath::compare($currentAmount, self::CHANGE_MIN_AMOUNT) < 0) {
                continue;
            }

            $prevAmount = MoneyMath::normalize($prevMap[$key]['amount'] ?? '0.00');
            $diff = MoneyMath::sub($currentAmount, $prevAmount);

            if (MoneyMath::compare($prevAmount, '0.00') === 0) {
                if (MoneyMath::compare($currentAmount, self::CHANGE_MIN_AMOUNT) < 0) {
                    continue;
                }
                $candidate = [
                    'category_id' => $row['category_id'],
                    'name' => $row['name'],
                    'current_amount' => $currentAmount,
                    'previous_amount' => $prevAmount,
                    'change_amount' => $diff,
                    'change_percent' => null,
                    'direction' => 'new',
                    'label' => 'New spending category this month',
                    'summary' => sprintf('%s appeared as new spending this month (Rs. %s).', $row['name'], number_format((float) $currentAmount, 2)),
                ];
                $score = (float) $currentAmount;
            } else {
                $percent = MoneyMath::percentOf($diff, $prevAmount);
                if ($percent === null) {
                    continue;
                }
                if (abs($percent) < self::CHANGE_MIN_PERCENT) {
                    continue;
                }
                $direction = $percent >= 0 ? 'up' : 'down';
                $candidate = [
                    'category_id' => $row['category_id'],
                    'name' => $row['name'],
                    'current_amount' => $currentAmount,
                    'previous_amount' => $prevAmount,
                    'change_amount' => $diff,
                    'change_percent' => round($percent, 1),
                    'direction' => $direction,
                    'label' => sprintf(
                        '%s %s %s%%',
                        $row['name'],
                        $direction === 'up' ? '↑' : '↓',
                        number_format(abs(round($percent, 1)), 1)
                    ),
                    'summary' => sprintf(
                        '%s: Rs. %s vs previous Rs. %s (%s%s%%).',
                        $row['name'],
                        number_format((float) $currentAmount, 2),
                        number_format((float) $prevAmount, 2),
                        $percent >= 0 ? '+' : '',
                        number_format(round($percent, 1), 1)
                    ),
                ];
                $score = abs($percent) * (float) $currentAmount;
            }

            if ($best === null || $score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    public function budgetPerformance(User $user, string $monthStart): array
    {
        $budgets = $user->budgets()
            ->with('category:id,name,type,is_default')
            ->whereDate('month', $monthStart)
            ->orderBy('id')
            ->get();

        if ($budgets->isEmpty()) {
            return [
                'available' => false,
                'total' => 0,
                'within_limit' => 0,
                'exceeded' => 0,
                'label' => 'No budgets set for this month.',
                'items' => [],
            ];
        }

        // enrichBudgets only — do not trigger evaluateAlerts (side-effect free for reviews).
        $items = $this->budgets->enrichBudgets($user, $budgets);
        $within = 0;
        $exceeded = 0;
        $compact = [];

        foreach ($items as $item) {
            $isExceeded = ($item['status'] ?? '') === BudgetStatus::Exceeded->value;
            if ($isExceeded) {
                $exceeded++;
            } else {
                $within++;
            }
            $compact[] = [
                'id' => $item['id'],
                'category_name' => $item['category']['name'] ?? 'Category',
                'spent' => $item['spent'],
                'limit' => $item['limit'],
                'status' => $item['status'],
                'status_label' => $isExceeded ? 'Over' : 'Within',
            ];
        }

        $total = count($items);

        return [
            'available' => true,
            'total' => $total,
            'within_limit' => $within,
            'exceeded' => $exceeded,
            'label' => sprintf('%d / %d budgets within limits', $within, $total),
            'items' => $compact,
        ];
    }

    /**
     * @return array{available: bool, direction: string|null, change_percent: float|null, statement: string}
     */
    public function overallSpendingTrend(string $currentExpense, string $previousExpense, bool $hasPreviousActivity): array
    {
        if (! $hasPreviousActivity) {
            return [
                'available' => false,
                'direction' => null,
                'change_percent' => null,
                'statement' => 'No previous-month comparison available.',
            ];
        }

        $currentExpense = MoneyMath::normalize($currentExpense);
        $previousExpense = MoneyMath::normalize($previousExpense);

        if (MoneyMath::compare($previousExpense, '0.00') === 0) {
            if (MoneyMath::compare($currentExpense, '0.00') === 0) {
                return [
                    'available' => true,
                    'direction' => 'stable',
                    'change_percent' => 0.0,
                    'statement' => 'Spending remained relatively stable overall.',
                ];
            }

            return [
                'available' => true,
                'direction' => 'up',
                'change_percent' => null,
                'statement' => 'Expenses increased compared with the previous month.',
            ];
        }

        $diff = MoneyMath::sub($currentExpense, $previousExpense);
        $percent = MoneyMath::percentOf($diff, $previousExpense) ?? 0.0;

        if (abs($percent) < self::OVERALL_STABLE_PERCENT) {
            return [
                'available' => true,
                'direction' => 'stable',
                'change_percent' => round($percent, 1),
                'statement' => 'Spending remained relatively stable overall.',
            ];
        }

        if ($percent > 0) {
            return [
                'available' => true,
                'direction' => 'up',
                'change_percent' => round($percent, 1),
                'statement' => sprintf('Expenses increased about %s%% compared with the previous month.', number_format(abs(round($percent, 1)), 1)),
            ];
        }

        return [
            'available' => true,
            'direction' => 'down',
            'change_percent' => round($percent, 1),
            'statement' => sprintf('Expenses decreased about %s%% compared with the previous month.', number_format(abs(round($percent, 1)), 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array{available: bool, direction: string|null, change_percent: float|null, statement: string}  $overallTrend
     * @param  array{category_id: int|null, name: string, amount: string, percentage: float|null}|null  $topCategory
     * @param  array<string, mixed>|null  $biggestChange
     * @param  array<string, mixed>  $budgetPerformance
     * @param  array{emphasized: bool, statement: string|null}  $halfMonth
     * @return array{available: bool, text: string, evidence: list<array{label: string, value: string}>}
     */
    public function buildInsight(
        array $summary,
        array $overallTrend,
        ?array $topCategory,
        ?array $biggestChange,
        array $budgetPerformance,
        array $halfMonth,
    ): array {
        if (((int) ($summary['transaction_count'] ?? 0)) === 0) {
            return [
                'available' => false,
                'text' => 'Keep tracking your income and expenses to unlock a fuller monthly review.',
                'evidence' => [],
            ];
        }

        $sentences = [];
        $evidence = [];

        $sentences[] = $overallTrend['statement'];

        if ($biggestChange !== null) {
            if (($biggestChange['direction'] ?? '') === 'up') {
                $sentences[] = sprintf(
                    '%s spending increased significantly compared with last month.',
                    $biggestChange['name']
                );
            } elseif (($biggestChange['direction'] ?? '') === 'down') {
                $sentences[] = sprintf(
                    '%s spending decreased meaningfully compared with last month.',
                    $biggestChange['name']
                );
            } elseif (($biggestChange['direction'] ?? '') === 'new') {
                $sentences[] = sprintf(
                    '%s became a new source of spending this month.',
                    $biggestChange['name']
                );
            }
            $evidence[] = ['label' => 'Biggest change', 'value' => (string) ($biggestChange['summary'] ?? $biggestChange['label'])];
        } else {
            $sentences[] = 'Individual category spending did not show a large material shift.';
        }

        if ($halfMonth['emphasized'] && $halfMonth['statement'] !== null) {
            $sentences[] = $halfMonth['statement'];
        }

        if (($budgetPerformance['available'] ?? false) === true && ($budgetPerformance['exceeded'] ?? 0) > 0) {
            $sentences[] = sprintf(
                '%d budget%s exceeded %s limit%s this month.',
                $budgetPerformance['exceeded'],
                $budgetPerformance['exceeded'] === 1 ? '' : 's',
                $budgetPerformance['exceeded'] === 1 ? 'its' : 'their',
                $budgetPerformance['exceeded'] === 1 ? '' : 's'
            );
            $evidence[] = ['label' => 'Budgets', 'value' => (string) $budgetPerformance['label']];
        }

        if ($topCategory !== null) {
            $evidence[] = [
                'label' => 'Top category',
                'value' => sprintf(
                    '%s · Rs. %s',
                    $topCategory['name'],
                    number_format((float) $topCategory['amount'], 2)
                ),
            ];
        }

        $evidence[] = [
            'label' => 'Saved',
            'value' => sprintf('Rs. %s', number_format((float) $summary['saved'], 2)),
        ];

        // Keep 2–4 sentences.
        $sentences = array_values(array_unique(array_filter($sentences)));
        $sentences = array_slice($sentences, 0, 4);

        return [
            'available' => true,
            'text' => implode(' ', $sentences),
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array{category_id: int|null, name: string, amount: string, percentage: float|null}>  $categories
     * @param  array<string, mixed>|null  $biggestChange
     * @param  array<string, mixed>  $budgetPerformance
     * @return list<array{id: string, title: string, reason: string, priority: int}>
     */
    public function buildActions(array $summary, array $categories, ?array $biggestChange, array $budgetPerformance): array
    {
        $candidates = [];

        if (($budgetPerformance['available'] ?? false) === true) {
            foreach ($budgetPerformance['items'] as $item) {
                if (($item['status'] ?? '') !== BudgetStatus::Exceeded->value) {
                    continue;
                }
                $name = (string) ($item['category_name'] ?? 'this category');
                $candidates[] = [
                    'id' => 'budget-over-'.$item['id'],
                    'title' => sprintf('Review %s budget', $name),
                    'reason' => sprintf(
                        '%s spending reached Rs. %s against a Rs. %s limit this month.',
                        $name,
                        number_format((float) $item['spent'], 2),
                        number_format((float) $item['limit'], 2)
                    ),
                    'priority' => 1000,
                ];
            }
        }

        if ($biggestChange !== null && in_array($biggestChange['direction'] ?? '', ['up', 'new'], true)) {
            $name = (string) $biggestChange['name'];
            $isDiscretionary = in_array($name, self::DISCRETIONARY_CATEGORIES, true);
            $title = match (true) {
                strcasecmp($name, 'Food') === 0 => 'Consider reducing food delivery spending',
                strcasecmp($name, 'Subscriptions') === 0 => 'Review subscription spending',
                strcasecmp($name, 'Entertainment') === 0 => 'Try setting a weekly Entertainment limit',
                default => sprintf('Review %s spending', $name),
            };
            $reason = $biggestChange['summary'] ?? sprintf('%s changed meaningfully this month.', $name);
            $candidates[] = [
                'id' => 'change-'.$this->categoryKey($biggestChange),
                'title' => $title,
                'reason' => $reason,
                'priority' => $isDiscretionary ? 800 : 700,
            ];
        }

        foreach ($categories as $row) {
            if (strcasecmp((string) $row['name'], 'Subscriptions') !== 0) {
                continue;
            }
            if (MoneyMath::compare($row['amount'], self::CHANGE_MIN_AMOUNT) < 0) {
                continue;
            }
            $candidates[] = [
                'id' => 'subscriptions-review',
                'title' => 'Review subscription spending',
                'reason' => sprintf(
                    'Subscriptions totaled Rs. %s this month — worth a quick check for unused plans.',
                    number_format((float) $row['amount'], 2)
                ),
                'priority' => 650,
            ];
            break;
        }

        if ($categories !== [] && ($categories[0]['percentage'] ?? 0) >= self::CONCENTRATION_PERCENT) {
            $top = $categories[0];
            $candidates[] = [
                'id' => 'concentration-'.$this->categoryKey($top),
                'title' => sprintf('Watch %s concentration', $top['name']),
                'reason' => sprintf(
                    '%s made up about %s%% of expenses this month.',
                    $top['name'],
                    number_format((float) $top['percentage'], 1)
                ),
                'priority' => 500,
            ];
        }

        $rate = $summary['savings_rate'] ?? null;
        if ($rate !== null && MoneyMath::compare($summary['income'], '0.00') > 0 && (float) $rate < self::LOW_SAVINGS_RATE_PERCENT) {
            $candidates[] = [
                'id' => 'savings-rate',
                'title' => 'Look for a small savings opportunity',
                'reason' => sprintf(
                    'Savings rate was about %s%% this month (saved Rs. %s on Rs. %s income).',
                    number_format((float) $rate, 1),
                    number_format((float) $summary['saved'], 2),
                    number_format((float) $summary['income'], 2)
                ),
                'priority' => 400,
            ];
        }

        usort($candidates, fn (array $a, array $b) => $b['priority'] <=> $a['priority']);

        $selected = [];
        $seenTitles = [];
        foreach ($candidates as $candidate) {
            $titleKey = strtolower($candidate['title']);
            if (isset($seenTitles[$titleKey])) {
                continue;
            }
            $seenTitles[$titleKey] = true;
            $selected[] = [
                'id' => $candidate['id'],
                'title' => $candidate['title'],
                'reason' => $candidate['reason'],
                'priority' => $candidate['priority'],
            ];
            if (count($selected) >= 3) {
                break;
            }
        }

        return $selected;
    }

    /**
     * @param  array{category_id: int|null, name: string, amount: string, percentage: float|null}|null  $topCategory
     * @return array{emphasized: bool, statement: string|null}
     */
    private function secondHalfEmphasis(User $user, string $monthStart, string $monthEnd, ?array $topCategory): array
    {
        if ($topCategory === null || $topCategory['category_id'] === null) {
            return ['emphasized' => false, 'statement' => null];
        }

        $mid = Carbon::parse($monthStart)->day(15)->toDateString();
        if ($mid > $monthEnd) {
            return ['emphasized' => false, 'statement' => null];
        }

        $first = MoneyMath::normalize(
            $user->transactions()
                ->where('type', TransactionType::Expense->value)
                ->where('category_id', $topCategory['category_id'])
                ->whereDate('transaction_date', '>=', $monthStart)
                ->whereDate('transaction_date', '<=', $mid)
                ->sum('amount')
        );
        $secondStart = Carbon::parse($mid)->addDay()->toDateString();
        if ($secondStart > $monthEnd) {
            return ['emphasized' => false, 'statement' => null];
        }
        $second = MoneyMath::normalize(
            $user->transactions()
                ->where('type', TransactionType::Expense->value)
                ->where('category_id', $topCategory['category_id'])
                ->whereDate('transaction_date', '>=', $secondStart)
                ->whereDate('transaction_date', '<=', $monthEnd)
                ->sum('amount')
        );

        if (MoneyMath::compare($first, '0.00') === 0) {
            return ['emphasized' => false, 'statement' => null];
        }

        $growth = MoneyMath::percentOf(MoneyMath::sub($second, $first), $first);
        if ($growth === null || $growth < 25.0) {
            return ['emphasized' => false, 'statement' => null];
        }

        return [
            'emphasized' => true,
            'statement' => sprintf(
                '%s expenses increased notably during the second half of the month.',
                $topCategory['name']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $prevSummary
     * @return array<string, mixed>
     */
    private function monthComparison(array $summary, array $prevSummary, string $previousLabel): array
    {
        if (((int) ($prevSummary['transaction_count'] ?? 0)) === 0) {
            return [
                'available' => false,
                'previous_label' => $previousLabel,
                'message' => 'No previous-month comparison available.',
                'expenses_change_percent' => null,
                'income_change_percent' => null,
                'saved_change_percent' => null,
            ];
        }

        return [
            'available' => true,
            'previous_label' => $previousLabel,
            'message' => null,
            'expenses_change_percent' => $this->safeChangePercent($summary['expenses'], $prevSummary['expenses']),
            'income_change_percent' => $this->safeChangePercent($summary['income'], $prevSummary['income']),
            'saved_change_percent' => $this->safeChangePercent($summary['saved'], $prevSummary['saved']),
        ];
    }

    private function safeChangePercent(string $current, string $previous): ?float
    {
        $current = MoneyMath::normalize($current);
        $previous = MoneyMath::normalize($previous);
        if (MoneyMath::compare($previous, '0.00') === 0) {
            return null;
        }

        $percent = MoneyMath::percentOf(MoneyMath::sub($current, $previous), $previous);

        return $percent === null ? null : round($percent, 1);
    }

    /**
     * @return array{previous_key: string|null, next_key: string|null, can_go_previous: bool, can_go_next: bool}
     */
    private function navigation(User $user, Carbon $selected, CarbonInterface $now): array
    {
        $currentCap = Carbon::parse($now->toDateTimeString())->startOfMonth();
        $previous = $selected->copy()->subMonthNoOverflow()->startOfMonth();
        $next = $selected->copy()->addMonthNoOverflow()->startOfMonth();

        $earliest = $user->transactions()->orderBy('transaction_date')->value('transaction_date');
        $earliestMonth = $earliest !== null
            ? Carbon::parse($earliest)->startOfMonth()
            : $currentCap->copy();

        $canPrevious = $previous->gte($earliestMonth);
        $canNext = $next->lte($currentCap);

        return [
            'previous_key' => $canPrevious ? $previous->format('Y-m') : null,
            'next_key' => $canNext ? $next->format('Y-m') : null,
            'can_go_previous' => $canPrevious,
            'can_go_next' => $canNext,
        ];
    }

    /**
     * @param  array{category_id?: int|null, name?: string}  $row
     */
    private function categoryKey(array $row): string
    {
        if (array_key_exists('category_id', $row) && $row['category_id'] !== null) {
            return 'id:'.(int) $row['category_id'];
        }

        return 'name:'.strtolower((string) ($row['name'] ?? 'uncategorized'));
    }
}

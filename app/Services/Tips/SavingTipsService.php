<?php

namespace App\Services\Tips;

use App\Models\Bookmark;
use App\Models\SavingTip;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SavingTipsService
{
    public function __construct(
        private readonly DashboardAnalyticsService $dashboard,
    ) {}

    /**
     * Sync deterministic tips for the period and return active (non-dismissed) tips.
     *
     * @param  array<string, mixed>|null  $dashboard  Prebuilt dashboard payload to avoid a second aggregation pass.
     * @return list<array<string, mixed>>
     */
    public function syncAndListForUser(User $user, ?CarbonInterface $now = null, int $limit = 5, ?array $dashboard = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $period = $now->format('Y-m');
        $dashboard ??= $this->dashboard->forUser($user, $now);

        $candidates = $this->buildCandidates($user, $dashboard, $period);

        foreach ($candidates as $candidate) {
            $existing = SavingTip::query()
                ->where('user_id', $user->id)
                ->where('tip_key', $candidate['tip_key'])
                ->where('period', $period)
                ->first();

            if ($existing?->dismissed_at) {
                continue;
            }

            SavingTip::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tip_key' => $candidate['tip_key'],
                    'period' => $period,
                ],
                [
                    'title' => $candidate['title'],
                    'body' => $candidate['body'],
                    'category_id' => $candidate['category_id'],
                    'impact_level' => $candidate['impact_level'],
                    'rank_score' => $candidate['rank_score'],
                    'payload' => $candidate['payload'],
                    'dismissed_at' => null,
                ],
            );
        }

        $tips = SavingTip::query()
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->whereNull('dismissed_at')
            ->orderByDesc('rank_score')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $bookmarkedIds = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', SavingTip::class)
            ->whereIn('bookmarkable_id', $tips->pluck('id'))
            ->pluck('bookmarkable_id')
            ->all();

        return $tips->map(fn (SavingTip $tip) => $this->serialize($tip, in_array($tip->id, $bookmarkedIds, true)))->all();
    }

    public function dismiss(User $user, SavingTip $tip): void
    {
        abort_unless($tip->user_id === $user->id, 404);
        $tip->update(['dismissed_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(SavingTip $tip, bool $bookmarked = false): array
    {
        return [
            'id' => $tip->id,
            'tip_key' => $tip->tip_key,
            'period' => $tip->period,
            'title' => $tip->title,
            'body' => $tip->body,
            'category_id' => $tip->category_id,
            'impact_level' => $tip->impact_level,
            'rank_score' => $tip->rank_score,
            'payload' => $tip->payload,
            'is_bookmarked' => $bookmarked,
            'is_dismissed' => $tip->dismissed_at !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return list<array<string, mixed>>
     */
    private function buildCandidates(User $user, array $dashboard, string $period): array
    {
        /** @var Collection<int, array<string, mixed>> $candidates */
        $candidates = collect();

        $summary = $dashboard['summary'];
        $categories = collect($dashboard['category_breakdown'] ?? []);
        $budgets = collect($dashboard['budgets'] ?? []);
        $monthly = collect($dashboard['monthly_trend'] ?? []);

        // Budget near / exceeded tips
        foreach ($budgets as $budget) {
            $status = $budget['status'] ?? '';
            $name = $budget['category']['name'] ?? 'Category';
            $categoryId = $budget['category']['id'] ?? null;

            if ($status === 'exceeded') {
                $overage = MoneyMath::sub($budget['spent'], $budget['limit']);
                $candidates->push([
                    'tip_key' => 'budget_exceeded_'.$categoryId,
                    'title' => "{$name} budget exceeded",
                    'body' => "You've spent Rs. {$overage} over your {$name} budget this month (limit Rs. {$budget['limit']}).",
                    'category_id' => $categoryId,
                    'impact_level' => 'high',
                    'rank_score' => 900 + (int) round((float) $budget['percentage']),
                    'payload' => ['type' => 'budget_exceeded', 'spent' => $budget['spent'], 'limit' => $budget['limit']],
                ]);
            } elseif ($status === 'near_limit') {
                $candidates->push([
                    'tip_key' => 'budget_near_'.$categoryId,
                    'title' => "{$name} budget is near its limit",
                    'body' => "You've used {$budget['percentage']}% of your {$name} budget (Rs. {$budget['spent']} of Rs. {$budget['limit']}).",
                    'category_id' => $categoryId,
                    'impact_level' => 'medium',
                    'rank_score' => 700 + (int) round((float) $budget['percentage']),
                    'payload' => ['type' => 'budget_near', 'percentage' => $budget['percentage']],
                ]);
            }
        }

        // Top category vs previous month in monthly trend (same category not available historically by name easily)
        // Use expense vs previous month overall
        $previous = $monthly->firstWhere('month', Carbon::createFromFormat('Y-m', $period)->subMonthNoOverflow()->format('Y-m'));
        if ($previous && MoneyMath::compare($summary['expense'], $previous['expense']) === 1) {
            $delta = MoneyMath::sub($summary['expense'], $previous['expense']);
            if (MoneyMath::compare($delta, '0.00') === 1) {
                $candidates->push([
                    'tip_key' => 'expense_up_vs_prev',
                    'title' => 'Spending is higher than last month',
                    'body' => "Expenses are Rs. {$delta} higher than last month. Review your top categories for opportunities to adjust.",
                    'category_id' => null,
                    'impact_level' => 'medium',
                    'rank_score' => 600 + (int) min(200, (float) $delta),
                    'payload' => ['type' => 'expense_up', 'delta' => $delta],
                ]);
            }
        }

        // Top category concentration
        $top = $categories->first();
        if ($top && ($top['percentage'] ?? 0) !== null && (float) $top['percentage'] >= 40) {
            $candidates->push([
                'tip_key' => 'category_concentration_'.($top['category_id'] ?? 'uncat'),
                'title' => "{$top['name']} dominates this month's spending",
                'body' => "{$top['name']} accounts for {$top['percentage']}% of expenses (Rs. {$top['amount']}). Reviewing frequent purchases there may help.",
                'category_id' => $top['category_id'],
                'impact_level' => (float) $top['percentage'] >= 50 ? 'high' : 'medium',
                'rank_score' => 500 + (int) round((float) $top['percentage']),
                'payload' => ['type' => 'concentration', 'percentage' => $top['percentage']],
            ]);
        }

        // Savings goal gap
        $goal = MoneyMath::normalize($user->savings_goal ?? 0);
        if (MoneyMath::compare($goal, '0.00') === 1) {
            $balance = $summary['balance'];
            if (MoneyMath::compare($balance, $goal) === -1) {
                $gap = MoneyMath::sub($goal, $balance);
                $candidates->push([
                    'tip_key' => 'savings_goal_gap',
                    'title' => 'Balance is below your savings goal',
                    'body' => "Your current monthly balance (Rs. {$balance}) is Rs. {$gap} below your savings goal of Rs. {$goal}.",
                    'category_id' => null,
                    'impact_level' => 'high',
                    'rank_score' => 850,
                    'payload' => ['type' => 'savings_goal', 'gap' => $gap, 'goal' => $goal],
                ]);
            }
        }

        // Negative balance
        if (MoneyMath::compare($summary['balance'], '0.00') === -1) {
            $candidates->push([
                'tip_key' => 'negative_balance',
                'title' => 'Expenses exceed income this month',
                'body' => "Your balance is Rs. {$summary['balance']}. Review top expense categories to close the gap.",
                'category_id' => null,
                'impact_level' => 'high',
                'rank_score' => 950,
                'payload' => ['type' => 'negative_balance', 'balance' => $summary['balance']],
            ]);
        }

        return $candidates
            ->sortByDesc('rank_score')
            ->values()
            ->take(8)
            ->all();
    }
}

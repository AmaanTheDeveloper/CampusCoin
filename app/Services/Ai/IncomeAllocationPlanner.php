<?php

namespace App\Services\Ai;

use App\Enums\TransactionType;
use App\Models\User;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;

/**
 * Deterministic income allocation planner.
 * Gemini explains the result; it does not invent the arithmetic.
 */
class IncomeAllocationPlanner
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     plan: list<array{category: string, amount: string, reason: string}>,
     *     total: string,
     *     basis: list<string>,
     *     has_history: bool,
     *     needs_clarification: bool,
     *     clarifying_question: string|null
     * }
     */
    public function plan(User $user, string $incomingAmount, array $context): array
    {
        $incoming = MoneyMath::normalize($incomingAmount);

        if (MoneyMath::compare($incoming, '0.00') <= 0) {
            return [
                'plan' => [],
                'total' => '0.00',
                'basis' => [],
                'has_history' => false,
                'needs_clarification' => true,
                'clarifying_question' => 'How much income did you receive? Share the amount so Campus Coin can suggest a plan.',
            ];
        }

        $hasHistory = ! (bool) ($context['insufficient_data'] ?? true)
            && MoneyMath::compare((string) ($context['current_period']['expenses'] ?? '0'), '0.00') > 0;

        $categories = $this->expenseCategoryTargets($user, $context, $hasHistory);
        $savingsGoal = MoneyMath::normalize($context['savings']['goal'] ?? $user->savings_goal ?? 0);
        $recurringExpense = $this->upcomingExpenseCommitments($context);

        $basis = [];
        if ($hasHistory) {
            $basis[] = 'recent spending';
        }
        if (! empty($context['budgets'])) {
            $basis[] = 'existing budgets';
        }
        if (MoneyMath::compare($savingsGoal, '0.00') > 0) {
            $basis[] = 'savings goal';
        }
        if (MoneyMath::compare($recurringExpense, '0.00') > 0) {
            $basis[] = 'recurring commitments';
        }

        $remaining = $incoming;
        $plan = [];

        // Reserve known upcoming recurring expenses first (capped).
        if (MoneyMath::compare($recurringExpense, '0.00') > 0) {
            $reserve = $this->minMoney($recurringExpense, $this->percentOf($incoming, 40));
            if (MoneyMath::compare($reserve, '0.00') > 0) {
                $plan[] = [
                    'category' => 'Recurring commitments',
                    'amount' => $reserve,
                    'reason' => 'Based on upcoming recurring expenses',
                ];
                $remaining = MoneyMath::sub($remaining, $reserve);
                if (! in_array('recurring commitments', $basis, true)) {
                    $basis[] = 'recurring commitments';
                }
            }
        }

        // Savings allocation — respect goal without starving essentials.
        $savingsShare = $this->savingsAllocation($incoming, $remaining, $savingsGoal, $hasHistory);
        if (MoneyMath::compare($savingsShare, '0.00') > 0) {
            $plan[] = [
                'category' => 'Savings',
                'amount' => $savingsShare,
                'reason' => MoneyMath::compare($savingsGoal, '0.00') > 0
                    ? 'Based on savings goal'
                    : 'Starter savings buffer',
            ];
            $remaining = MoneyMath::sub($remaining, $savingsShare);
        }

        // Distribute remaining across expense categories by history / budgets,
        // capped by realistic category need so one dominant category cannot absorb almost everything.
        $weights = $this->categoryWeights($categories, $context, $hasHistory);
        $distributed = $this->distribute($remaining, $weights);
        foreach ($distributed as $row) {
            $plan[] = $row;
        }

        // Absorb rounding remainder into Buffer (or create Buffer).
        $allocated = '0.00';
        foreach ($plan as $row) {
            $allocated = MoneyMath::add($allocated, $row['amount']);
        }
        $diff = MoneyMath::sub($incoming, $allocated);
        if (MoneyMath::compare($diff, '0.00') !== 0) {
            $bufferIndex = null;
            foreach ($plan as $i => $row) {
                if ($row['category'] === 'Buffer') {
                    $bufferIndex = $i;
                    break;
                }
            }
            if ($bufferIndex === null) {
                $plan[] = [
                    'category' => 'Buffer',
                    'amount' => MoneyMath::compare($diff, '0.00') > 0 ? $diff : '0.00',
                    'reason' => 'For unexpected student expenses',
                ];
                if (MoneyMath::compare($diff, '0.00') < 0) {
                    // Trim largest non-savings line if over-allocated (should be rare).
                    $plan = $this->trimToTotal($plan, $incoming);
                }
            } else {
                $plan[$bufferIndex]['amount'] = MoneyMath::add($plan[$bufferIndex]['amount'], $diff);
                if (MoneyMath::compare($plan[$bufferIndex]['amount'], '0.00') < 0) {
                    $plan = $this->trimToTotal($plan, $incoming);
                }
            }
        }

        $plan = $this->dedupeAndValidate($plan, $incoming);

        return [
            'plan' => $plan,
            'total' => $incoming,
            'basis' => array_values(array_unique($basis)),
            'has_history' => $hasHistory,
            'needs_clarification' => false,
            'clarifying_question' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function expenseCategoryTargets(User $user, array $context, bool $hasHistory): array
    {
        $names = [];

        foreach ($context['categories'] ?? [] as $row) {
            if (is_array($row) && isset($row['name']) && is_string($row['name']) && $row['name'] !== '') {
                if (strcasecmp($row['name'], 'Uncategorized') === 0 || ($row['category_id'] ?? null) === null) {
                    continue;
                }
                $names[] = $row['name'];
            }
        }

        foreach ($context['budgets'] ?? [] as $row) {
            if (is_array($row) && isset($row['category']) && is_string($row['category']) && $row['category'] !== '') {
                if (strcasecmp($row['category'], 'Uncategorized') === 0) {
                    continue;
                }
                $names[] = $row['category'];
            }
        }

        if ($names === []) {
            $names = CategoryAccess::forUser($user, TransactionType::Expense)
                ->where('is_active', true)
                ->take(5)
                ->pluck('name')
                ->all();
        }

        if ($names === []) {
            $names = ['Food', 'Transport', 'Academics', 'Subscriptions'];
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function upcomingExpenseCommitments(array $context): string
    {
        $total = '0.00';
        foreach ($context['recurring']['upcoming'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['type'] ?? '') !== TransactionType::Expense->value) {
                continue;
            }
            $total = MoneyMath::add($total, MoneyMath::normalize($row['amount'] ?? 0));
        }
        foreach ($context['recurring']['pending_approvals'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $total = MoneyMath::add($total, MoneyMath::normalize($row['amount'] ?? 0));
        }

        return $total;
    }

    private function savingsAllocation(string $incoming, string $remaining, string $savingsGoal, bool $hasHistory): string
    {
        if (MoneyMath::compare($remaining, '0.00') <= 0) {
            return '0.00';
        }

        if (MoneyMath::compare($savingsGoal, '0.00') > 0) {
            // Aim for up to 20% toward goal, never more than 30% of remaining.
            $target = $this->minMoney($this->percentOf($incoming, 20), $this->percentOf($remaining, 30));
            if (MoneyMath::compare($savingsGoal, $target) < 0) {
                $target = $this->minMoney($savingsGoal, $target);
            }

            return $target;
        }

        // No goal: small starter share when history exists; otherwise modest starter heuristic.
        return $this->percentOf($remaining, $hasHistory ? 10 : 15);
    }

    /**
     * @param  list<string>  $categories
     * @param  array<string, mixed>  $context
     * @return list<array{category: string, weight: float, reason: string, need: string|null}>
     */
    private function categoryWeights(array $categories, array $context, bool $hasHistory): array
    {
        $expenseTotal = MoneyMath::normalize($context['current_period']['expenses'] ?? 0);
        $weights = [];

        $historyMap = [];
        foreach ($context['categories'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }
            $name = (string) $row['name'];
            if (strcasecmp($name, 'Uncategorized') === 0 || ($row['category_id'] ?? null) === null) {
                continue;
            }
            $historyMap[$name] = MoneyMath::normalize($row['amount'] ?? 0);
        }

        $budgetRemainingMap = [];
        $budgetLimitMap = [];
        foreach ($context['budgets'] ?? [] as $row) {
            if (! is_array($row) || ! isset($row['category'])) {
                continue;
            }
            $name = (string) $row['category'];
            $limit = MoneyMath::normalize($row['limit'] ?? 0);
            $spent = MoneyMath::normalize($row['spent'] ?? 0);
            $budgetLimitMap[$name] = $limit;
            $remaining = MoneyMath::compare($limit, $spent) > 0
                ? MoneyMath::sub($limit, $spent)
                : '0.00';
            $budgetRemainingMap[$name] = $remaining;
        }

        foreach ($categories as $name) {
            if (in_array($name, ['Savings', 'Buffer', 'Recurring commitments', 'Uncategorized'], true)) {
                continue;
            }
            if (strcasecmp($name, 'Uncategorized') === 0) {
                continue;
            }

            $weight = 1.0;
            $reason = $hasHistory ? 'Based on equal starter split' : 'Starter heuristic — limited history';
            $need = null;

            if (isset($budgetRemainingMap[$name]) && MoneyMath::compare($budgetLimitMap[$name] ?? '0', '0.00') > 0) {
                $need = $budgetRemainingMap[$name];
                $weight = max(0.5, (float) $budgetLimitMap[$name]);
                $reason = 'Based on remaining budget room';
            } elseif ($hasHistory && isset($historyMap[$name]) && MoneyMath::compare($expenseTotal, '0.00') > 0) {
                $pct = MoneyMath::percentOf($historyMap[$name], $expenseTotal) ?? 0.0;
                $weight = max(0.5, $pct);
                // Cap to this month's observed spend for that named category (realistic envelope).
                $need = $historyMap[$name];
                $reason = 'Based on recent average (capped to observed category spend)';
            } elseif (isset($budgetLimitMap[$name]) && MoneyMath::compare($budgetLimitMap[$name], '0.00') > 0) {
                $weight = max(0.5, (float) $budgetLimitMap[$name]);
                $need = $budgetLimitMap[$name];
                $reason = 'Based on existing budgets';
            }

            $weights[] = [
                'category' => $name,
                'weight' => $weight,
                'reason' => $reason,
                'need' => $need,
            ];
        }

        // Buffer absorbs surplus after need-capped categories — keep a positive preference weight.
        $weights[] = [
            'category' => 'Buffer',
            'weight' => $hasHistory ? 1.2 : 1.0,
            'reason' => 'For unexpected student expenses',
            'need' => null,
        ];

        return array_slice($weights, 0, 6);
    }

    /**
     * @param  list<array{category: string, weight: float, reason: string, need?: string|null}>  $weights
     * @return list<array{category: string, amount: string, reason: string}>
     */
    private function distribute(string $remaining, array $weights): array
    {
        if ($weights === [] || MoneyMath::compare($remaining, '0.00') <= 0) {
            return [];
        }

        $totalWeight = array_sum(array_map(fn (array $w) => $w['weight'], $weights));
        if ($totalWeight <= 0) {
            $totalWeight = count($weights);
            foreach ($weights as $i => $w) {
                $weights[$i]['weight'] = 1.0;
            }
        }

        $tentative = [];
        $redirectToBuffer = '0.00';

        foreach ($weights as $w) {
            $share = MoneyMath::normalize(($w['weight'] / $totalWeight) * (float) $remaining);
            $need = $w['need'] ?? null;
            $isBuffer = $w['category'] === 'Buffer';

            if (! $isBuffer && is_string($need) && MoneyMath::compare($need, '0.00') >= 0) {
                if (MoneyMath::compare($share, $need) > 0) {
                    $redirectToBuffer = MoneyMath::add($redirectToBuffer, MoneyMath::sub($share, $need));
                    $share = $need;
                }
            }

            if (MoneyMath::compare($share, '0.00') <= 0 && ! $isBuffer) {
                continue;
            }

            $tentative[] = [
                'category' => $w['category'],
                'amount' => $share,
                'reason' => $w['reason'],
            ];
        }

        // Apply redirected surplus to Buffer (create if missing).
        if (MoneyMath::compare($redirectToBuffer, '0.00') > 0) {
            $bufferIndex = null;
            foreach ($tentative as $i => $row) {
                if ($row['category'] === 'Buffer') {
                    $bufferIndex = $i;
                    break;
                }
            }
            if ($bufferIndex === null) {
                $tentative[] = [
                    'category' => 'Buffer',
                    'amount' => $redirectToBuffer,
                    'reason' => 'For unexpected student expenses',
                ];
            } else {
                $tentative[$bufferIndex]['amount'] = MoneyMath::add($tentative[$bufferIndex]['amount'], $redirectToBuffer);
            }
        }

        // Exact remainder into Buffer (or last line) using MoneyMath — avoid float drift.
        $allocated = '0.00';
        foreach ($tentative as $row) {
            $allocated = MoneyMath::add($allocated, $row['amount']);
        }
        $diff = MoneyMath::sub($remaining, $allocated);
        if (MoneyMath::compare($diff, '0.00') !== 0) {
            $bufferIndex = null;
            foreach ($tentative as $i => $row) {
                if ($row['category'] === 'Buffer') {
                    $bufferIndex = $i;
                    break;
                }
            }
            if ($bufferIndex === null) {
                if (MoneyMath::compare($diff, '0.00') > 0) {
                    $tentative[] = [
                        'category' => 'Buffer',
                        'amount' => $diff,
                        'reason' => 'For unexpected student expenses',
                    ];
                }
            } else {
                $tentative[$bufferIndex]['amount'] = MoneyMath::add($tentative[$bufferIndex]['amount'], $diff);
                if (MoneyMath::compare($tentative[$bufferIndex]['amount'], '0.00') < 0) {
                    $tentative[$bufferIndex]['amount'] = '0.00';
                }
            }
        }

        return array_values(array_filter(
            $tentative,
            fn (array $r) => MoneyMath::compare($r['amount'], '0.00') > 0,
        ));
    }

    /**
     * @param  list<array{category: string, amount: string, reason: string}>  $plan
     * @return list<array{category: string, amount: string, reason: string}>
     */
    private function trimToTotal(array $plan, string $total): array
    {
        $sum = '0.00';
        foreach ($plan as $row) {
            $sum = MoneyMath::add($sum, $row['amount']);
        }

        $over = MoneyMath::sub($sum, $total);
        if (MoneyMath::compare($over, '0.00') <= 0) {
            return $plan;
        }

        // Reduce from the largest non-savings category.
        $maxI = null;
        $maxAmt = '0.00';
        foreach ($plan as $i => $row) {
            if ($row['category'] === 'Savings') {
                continue;
            }
            if (MoneyMath::compare($row['amount'], $maxAmt) > 0) {
                $maxAmt = $row['amount'];
                $maxI = $i;
            }
        }

        if ($maxI !== null) {
            $plan[$maxI]['amount'] = MoneyMath::sub($plan[$maxI]['amount'], $over);
            if (MoneyMath::compare($plan[$maxI]['amount'], '0.00') < 0) {
                $plan[$maxI]['amount'] = '0.00';
            }
        }

        return array_values(array_filter($plan, fn (array $r) => MoneyMath::compare($r['amount'], '0.00') > 0));
    }

    /**
     * @param  list<array{category: string, amount: string, reason: string}>  $plan
     * @return list<array{category: string, amount: string, reason: string}>
     */
    private function dedupeAndValidate(array $plan, string $incoming): array
    {
        $merged = [];
        foreach ($plan as $row) {
            $cat = trim(strip_tags((string) $row['category']));
            $amount = MoneyMath::normalize($row['amount']);
            if ($cat === '' || MoneyMath::compare($amount, '0.00') <= 0) {
                continue;
            }
            if (! isset($merged[$cat])) {
                $merged[$cat] = [
                    'category' => $cat,
                    'amount' => $amount,
                    'reason' => mb_substr(trim(strip_tags((string) $row['reason'])), 0, 200),
                ];
            } else {
                $merged[$cat]['amount'] = MoneyMath::add($merged[$cat]['amount'], $amount);
            }
        }

        $list = array_values($merged);
        $sum = '0.00';
        foreach ($list as $row) {
            $sum = MoneyMath::add($sum, $row['amount']);
        }

        $diff = MoneyMath::sub($incoming, $sum);
        if (MoneyMath::compare($diff, '0.00') !== 0 && $list !== []) {
            $last = count($list) - 1;
            $list[$last]['amount'] = MoneyMath::add($list[$last]['amount'], $diff);
            if (MoneyMath::compare($list[$last]['amount'], '0.00') < 0) {
                $list = $this->trimToTotal($list, $incoming);
            }
        }

        return array_values(array_filter($list, fn (array $r) => MoneyMath::compare($r['amount'], '0.00') > 0));
    }

    private function percentOf(string $amount, float $percent): string
    {
        return MoneyMath::normalize(((float) MoneyMath::normalize($amount)) * ($percent / 100));
    }

    private function minMoney(string $a, string $b): string
    {
        return MoneyMath::compare($a, $b) <= 0 ? MoneyMath::normalize($a) : MoneyMath::normalize($b);
    }
}

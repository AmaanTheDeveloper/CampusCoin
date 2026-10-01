<?php

namespace App\Services\Ai;

use App\Support\MoneyMath;

/**
 * Deterministic P0–P4 coaching recommendation from Campus Coin context.
 * Never invents amounts; Uncategorized is never the primary named focus.
 */
class CoachRecommendationEngine
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     priority: string,
     *     response_type: string,
     *     primary_category: string|null,
     *     title: string,
     *     why: string,
     *     answer: string,
     *     facts: list<string>,
     *     suggested_action: string,
     *     suggested_cut_amount: string|null,
     *     data_quality_note: string|null,
     *     suggested_page: string|null
     * }
     */
    public function recommend(array $context): array
    {
        if (($context['insufficient_data'] ?? false) === true) {
            return $this->payload(
                priority: 'P4',
                responseType: 'financial_action',
                primaryCategory: null,
                title: 'Start tracking',
                why: 'Campus Coin does not have enough categorized spending yet to prioritize a cut.',
                answer: 'Start by adding a few categorized expenses so Campus Coin can identify your strongest improvement opportunity.',
                facts: ['Insufficient categorized spending history this month'],
                suggestedAction: 'Add expenses with categories on the Transactions page.',
                cutAmount: null,
                dataQualityNote: null,
                suggestedPage: 'transactions',
            );
        }

        $available = MoneyMath::normalize($context['current_period']['available_balance'] ?? 0);
        $expenses = MoneyMath::normalize($context['current_period']['expenses'] ?? 0);
        $income = MoneyMath::normalize($context['current_period']['income'] ?? 0);
        $goal = MoneyMath::normalize($context['savings']['goal'] ?? 0);
        $periodSavings = MoneyMath::normalize($context['savings']['period_savings'] ?? 0);
        $committed = MoneyMath::normalize($context['recurring']['monthly_committed_expense'] ?? 0);
        $named = $this->namedCategories($context);
        $topNamed = $named[0] ?? null;
        $hasUncategorized = (bool) ($context['current_period']['has_uncategorized'] ?? false);
        $uncatAmount = MoneyMath::normalize($context['current_period']['uncategorized_amount'] ?? 0);
        $dataQuality = null;
        if ($hasUncategorized && MoneyMath::compare($uncatAmount, '0.00') > 0) {
            $dataQuality = 'You also have Rs '.$this->friendly($uncatAmount).' of uncategorized spending, which may reduce confidence in the overall category picture.';
        }

        // P0 — financial safety
        if (MoneyMath::compare($available, '0.00') <= 0) {
            return $this->payload(
                priority: 'P0',
                responseType: 'financial_action',
                primaryCategory: $topNamed['name'] ?? null,
                title: 'Protect available balance',
                why: 'Available balance is at or below zero, so new discretionary spending increases financial risk.',
                answer: 'Your highest priority right now is protecting your available balance — it is currently Rs '.$this->friendly($available).'. Pause non-essential spending until income or lower expenses restore a buffer.'
                    .($dataQuality !== null ? ' '.$dataQuality : ''),
                facts: array_values(array_filter([
                    'Available balance: Rs '.$this->friendly($available),
                    'Current-month expenses: Rs '.$this->friendly($expenses),
                    $topNamed !== null ? $topNamed['name'].': Rs '.$this->friendly($topNamed['amount']) : null,
                    $dataQuality,
                ])),
                suggestedAction: 'Review upcoming recurring commitments and delay discretionary purchases until your available balance recovers.',
                cutAmount: $topNamed !== null ? $this->suggestCut($topNamed['amount'], 15) : null,
                dataQualityNote: $dataQuality,
            );
        }

        if (MoneyMath::compare($committed, '0.00') > 0
            && MoneyMath::compare($available, $committed) < 0) {
            return $this->payload(
                priority: 'P0',
                responseType: 'financial_action',
                primaryCategory: null,
                title: 'Preserve recurring commitments',
                why: 'Committed recurring expenses exceed your current available balance.',
                answer: 'Your strongest priority is preserving funds for recurring commitments. Active commitments total about Rs '.$this->friendly($committed).' while available balance is Rs '.$this->friendly($available).'.'
                    .($dataQuality !== null ? ' '.$dataQuality : ''),
                facts: [
                    'Committed recurring expenses: Rs '.$this->friendly($committed),
                    'Available balance: Rs '.$this->friendly($available),
                ],
                suggestedAction: 'Keep discretionary spending low and review Recurring for upcoming dues before new purchases.',
                cutAmount: null,
                dataQualityNote: $dataQuality,
            );
        }

        // P1 — active budget pressure
        $pressure = $this->budgetPressure($context);
        if ($pressure !== null) {
            $cut = $this->suggestCut($pressure['spent'], 10);

            return $this->payload(
                priority: 'P1',
                responseType: 'financial_action',
                primaryCategory: $pressure['name'],
                title: $pressure['name'].' budget pressure',
                why: $pressure['why'],
                answer: 'Your biggest improvement opportunity right now is '.$pressure['name'].' because '.$pressure['why']
                    .($dataQuality !== null ? ' '.$dataQuality : ''),
                facts: array_values(array_filter([
                    $pressure['name'].' spent: Rs '.$this->friendly($pressure['spent']),
                    $pressure['name'].' budget: Rs '.$this->friendly($pressure['limit']),
                    $pressure['remaining_fact'],
                    'Available balance: Rs '.$this->friendly($available),
                    $dataQuality,
                ])),
                suggestedAction: 'Target approximately Rs '.$this->friendly($cut).' lower '.$pressure['name'].' spending for the rest of the period'
                    .(MoneyMath::compare($goal, '0.00') > 0 ? ' and redirect that amount toward your savings goal.' : '.'),
                cutAmount: $cut,
                dataQualityNote: $dataQuality,
            );
        }

        // P2 — savings opportunity
        if (MoneyMath::compare($goal, '0.00') > 0
            && MoneyMath::compare($periodSavings, $goal) < 0
            && $topNamed !== null
            && MoneyMath::compare($topNamed['amount'], '0.00') > 0) {
            $cut = $this->suggestCut($topNamed['amount'], 15);
            $pct = $topNamed['percentage'] !== null
                ? number_format((float) $topNamed['percentage'], 1).'%'
                : null;

            return $this->payload(
                priority: 'P2',
                responseType: 'financial_action',
                primaryCategory: $topNamed['name'],
                title: 'Strengthen savings progress',
                why: $topNamed['name'].' is your largest named expense while period savings trail your goal.',
                answer: 'Your strongest savings opportunity is reducing '.$topNamed['name'].'. It is currently your largest named expense category at Rs '.$this->friendly($topNamed['amount'])
                    .($pct !== null ? ' ('.$pct.' of expenses)' : '')
                    .', while your savings goal is Rs '.$this->friendly($goal).' and this month\'s period balance is Rs '.$this->friendly($periodSavings).'.'
                    .($dataQuality !== null ? ' '.$dataQuality : ''),
                facts: array_values(array_filter([
                    $topNamed['name'].': Rs '.$this->friendly($topNamed['amount']),
                    $pct !== null ? $topNamed['name'].' share of expenses: '.$pct : null,
                    'Savings goal: Rs '.$this->friendly($goal),
                    'Current-month period balance: Rs '.$this->friendly($periodSavings),
                    $dataQuality,
                ])),
                suggestedAction: 'Target approximately Rs '.$this->friendly($cut).' lower '.$topNamed['name'].' spending and redirect that amount toward your savings goal.',
                cutAmount: $cut,
                dataQualityNote: $dataQuality,
            );
        }

        // P3 — optimization via largest named category
        if ($topNamed !== null && MoneyMath::compare($topNamed['amount'], '0.00') > 0) {
            $cut = $this->suggestCut($topNamed['amount'], 15);
            $pct = $topNamed['percentage'] !== null
                ? number_format((float) $topNamed['percentage'], 1).'%'
                : null;

            return $this->payload(
                priority: 'P3',
                responseType: 'financial_action',
                primaryCategory: $topNamed['name'],
                title: 'Optimize '.$topNamed['name'],
                why: $topNamed['name'].' is your largest named expense category this month.',
                answer: 'Your biggest improvement opportunity right now is '.$topNamed['name'].' spending because it is your largest named category at Rs '.$this->friendly($topNamed['amount'])
                    .($pct !== null ? ' ('.$pct.' of this month\'s expenses)' : '').'.'
                    .($dataQuality !== null ? ' '.$dataQuality : ''),
                facts: array_values(array_filter([
                    $topNamed['name'].': Rs '.$this->friendly($topNamed['amount']),
                    $pct !== null ? $topNamed['name'].' share of expenses: '.$pct : null,
                    'Current-month expenses: Rs '.$this->friendly($expenses),
                    'Available balance: Rs '.$this->friendly($available),
                    $dataQuality,
                ])),
                suggestedAction: 'Target approximately Rs '.$this->friendly($cut).' lower '.$topNamed['name'].' spending for the rest of the period.',
                cutAmount: $cut,
                dataQualityNote: $dataQuality,
            );
        }

        // P4 — healthy / maintain
        $answer = 'Your current Campus Coin pattern looks manageable';
        if (MoneyMath::compare($available, '0.00') > 0) {
            $answer .= ' with Rs '.$this->friendly($available).' available';
        }
        if (MoneyMath::compare($income, '0.00') > 0) {
            $answer .= ' and Rs '.$this->friendly($income).' income this month';
        }
        $answer .= '. Keep tracking consistently';
        if (MoneyMath::compare($goal, '0.00') > 0) {
            $answer .= ' and protect progress toward your Rs '.$this->friendly($goal).' savings goal';
        }
        $answer .= '.';
        if ($dataQuality !== null) {
            $answer .= ' '.$dataQuality;
        }

        return $this->payload(
            priority: 'P4',
            responseType: 'financial_action',
            primaryCategory: null,
            title: 'Maintain healthy pattern',
            why: 'No severe pressure signals were found in the current-month Campus Coin data.',
            answer: $answer,
            facts: array_values(array_filter([
                'Available balance: Rs '.$this->friendly($available),
                'Current-month expenses: Rs '.$this->friendly($expenses),
                MoneyMath::compare($goal, '0.00') > 0 ? 'Savings goal: Rs '.$this->friendly($goal) : null,
                $dataQuality,
            ])),
            suggestedAction: MoneyMath::compare($goal, '0.00') > 0
                ? 'Protect a small amount toward your savings goal before discretionary spending this week.'
                : 'Keep categorizing expenses so Campus Coin can spot pressure early.',
            cutAmount: null,
            dataQualityNote: $dataQuality,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function namedCategories(array $context): array
    {
        $rows = [];
        foreach ($context['categories'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $categoryId = $row['category_id'] ?? null;
            if ($name === '' || strcasecmp($name, 'Uncategorized') === 0 || $categoryId === null) {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => MoneyMath::compare($b['amount'] ?? '0', $a['amount'] ?? '0'));

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{name: string, spent: string, limit: string, why: string, remaining_fact: string}|null
     */
    private function budgetPressure(array $context): ?array
    {
        $exceeded = null;
        $near = null;

        foreach ($context['budgets'] ?? [] as $budget) {
            if (! is_array($budget)) {
                continue;
            }
            $status = (string) ($budget['status'] ?? '');
            $name = (string) ($budget['category'] ?? 'Category');
            $spent = MoneyMath::normalize($budget['spent'] ?? 0);
            $limit = MoneyMath::normalize($budget['limit'] ?? 0);
            $remaining = MoneyMath::normalize($budget['remaining'] ?? 0);
            $over = MoneyMath::normalize($budget['over_budget'] ?? 0);

            if ($status === 'exceeded' || MoneyMath::compare($spent, $limit) > 0) {
                $exceeded = [
                    'name' => $name,
                    'spent' => $spent,
                    'limit' => $limit,
                    'why' => 'it is over budget (Rs '.$this->friendly($spent).' vs Rs '.$this->friendly($limit).')',
                    'remaining_fact' => MoneyMath::compare($over, '0.00') > 0
                        ? 'Over by: Rs '.$this->friendly($over)
                        : 'Budget remaining: Rs 0',
                ];
                break;
            }

            if ($near === null && ($status === 'near_limit' || ((float) ($budget['percentage'] ?? 0)) >= 80)) {
                $near = [
                    'name' => $name,
                    'spent' => $spent,
                    'limit' => $limit,
                    'why' => 'it is near its budget limit with only Rs '.$this->friendly($remaining).' remaining',
                    'remaining_fact' => 'Budget remaining: Rs '.$this->friendly($remaining),
                ];
            }
        }

        return $exceeded ?? $near;
    }

    private function suggestCut(string $amount, float $percent): string
    {
        $cut = MoneyMath::normalize(((float) MoneyMath::normalize($amount)) * ($percent / 100));
        if (MoneyMath::compare($cut, '100.00') < 0 && MoneyMath::compare($amount, '100.00') > 0) {
            return '100.00';
        }

        return $cut;
    }

    private function friendly(string $amount): string
    {
        $normalized = MoneyMath::normalize($amount);
        if (str_ends_with($normalized, '.00')) {
            return number_format((float) $normalized, 0, '.', ',');
        }

        return number_format((float) $normalized, 2, '.', ',');
    }

    /**
     * @param  list<string>  $facts
     * @return array{
     *     priority: string,
     *     response_type: string,
     *     primary_category: string|null,
     *     title: string,
     *     why: string,
     *     answer: string,
     *     facts: list<string>,
     *     suggested_action: string,
     *     suggested_cut_amount: string|null,
     *     data_quality_note: string|null,
     *     suggested_page: string|null
     * }
     */
    private function payload(
        string $priority,
        string $responseType,
        ?string $primaryCategory,
        string $title,
        string $why,
        string $answer,
        array $facts,
        string $suggestedAction,
        ?string $cutAmount,
        ?string $dataQualityNote,
        ?string $suggestedPage = 'intelligence',
    ): array {
        return [
            'priority' => $priority,
            'response_type' => $responseType,
            'primary_category' => $primaryCategory,
            'title' => $title,
            'why' => $why,
            'answer' => $answer,
            'facts' => array_values(array_unique(array_filter($facts))),
            'suggested_action' => $suggestedAction,
            'suggested_cut_amount' => $cutAmount,
            'data_quality_note' => $dataQualityNote,
            'suggested_page' => $suggestedPage,
        ];
    }
}

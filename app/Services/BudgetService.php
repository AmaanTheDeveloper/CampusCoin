<?php

namespace App\Services;

use App\Enums\BudgetAlertType;
use App\Enums\BudgetStatus;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\User;
use App\Notifications\BudgetExceededNotification;
use App\Notifications\BudgetNearLimitNotification;
use App\Support\AvailableBalance;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class BudgetService
{
    /**
     * Normalize any date-like month input to the first calendar day.
     */
    public function normalizeMonth(string $month): string
    {
        return Carbon::parse($month)->startOfMonth()->toDateString();
    }

    /**
     * @return array{
     *     id: int,
     *     category: array{id: int, name: string, is_default: bool},
     *     month: string,
     *     month_label: string,
     *     limit: string,
     *     spent: string,
     *     remaining: string,
     *     over_budget: string,
     *     percentage: float,
     *     status: string,
     *     status_label: string
     * }
     */
    public function serialize(Budget $budget, string $spent): array
    {
        $limit = MoneyMath::normalize($budget->limit_amount);
        $spent = MoneyMath::normalize($spent);
        $rawRemaining = MoneyMath::sub($limit, $spent);
        // Remaining is never negative in the UI — overrun is reported separately.
        $remaining = MoneyMath::compare($rawRemaining, '0.00') < 0 ? '0.00' : $rawRemaining;
        $overBudget = MoneyMath::compare($spent, $limit) > 0
            ? MoneyMath::sub($spent, $limit)
            : '0.00';
        $percentage = MoneyMath::percentOf($spent, $limit) ?? 0.0;
        $status = BudgetStatus::fromPercentage($percentage);

        return [
            'id' => $budget->id,
            'category' => [
                'id' => $budget->category->id,
                'name' => $budget->category->name,
                'is_default' => (bool) $budget->category->is_default,
            ],
            'month' => $budget->month->toDateString(),
            'month_label' => $budget->month->format('F Y'),
            'limit' => $limit,
            'spent' => $spent,
            'remaining' => $remaining,
            'over_budget' => $overBudget,
            'percentage' => $percentage,
            'status' => $status->value,
            'status_label' => $status->label(),
        ];
    }

    /**
     * Category spending for a budget month, optionally excluding one transaction (edit replacement).
     */
    public function spentForCategoryMonth(
        User $user,
        int $categoryId,
        string $monthStart,
        ?int $excludeTransactionId = null,
    ): string {
        $monthEndExclusive = Carbon::parse($monthStart)->addMonthNoOverflow()->startOfMonth()->toDateString();

        $total = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->where('category_id', $categoryId)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<', $monthEndExclusive)
            ->when(
                $excludeTransactionId !== null,
                fn ($query) => $query->where('id', '!=', $excludeTransactionId),
            )
            ->sum('amount');

        return MoneyMath::normalize($total);
    }

    /**
     * Evaluate whether an expense would exceed the matching category/month budget.
     *
     * @return array{
     *     exceeds: bool,
     *     budget_id: int,
     *     category_name: string,
     *     month_label: string,
     *     limit: string,
     *     spent_before: string,
     *     expense_amount: string,
     *     resulting_spend: string,
     *     over_amount: string,
     *     already_exceeded: bool,
     *     prior_over_amount: string,
     *     balance_sufficient: bool
     * }|null null when no budget exists for the category/month
     */
    public function evaluateExpenseImpact(
        User $user,
        int $categoryId,
        string $amount,
        string $transactionDate,
        ?int $excludeTransactionId = null,
        ?string $availableBalance = null,
    ): ?array {
        $monthStart = $this->normalizeMonth($transactionDate);

        $budget = $user->budgets()
            ->with('category:id,name,type,is_default')
            ->where('category_id', $categoryId)
            ->whereDate('month', $monthStart)
            ->first();

        if ($budget === null) {
            return null;
        }

        $limit = MoneyMath::normalize($budget->limit_amount);
        $amount = MoneyMath::normalize($amount);
        $spentBefore = $this->spentForCategoryMonth($user, $categoryId, $monthStart, $excludeTransactionId);
        $resulting = MoneyMath::add($spentBefore, $amount);
        $exceeds = MoneyMath::compare($resulting, $limit) > 0;
        $alreadyExceeded = MoneyMath::compare($spentBefore, $limit) > 0;
        $overAmount = $exceeds ? MoneyMath::sub($resulting, $limit) : '0.00';
        $priorOver = $alreadyExceeded ? MoneyMath::sub($spentBefore, $limit) : '0.00';
        $balance = $availableBalance !== null
            ? MoneyMath::normalize($availableBalance)
            : AvailableBalance::forUser($user);
        $balanceSufficient = MoneyMath::compare($amount, $balance) <= 0;

        return [
            'exceeds' => $exceeds,
            'budget_id' => $budget->id,
            'category_name' => $budget->category->name,
            'month_label' => $budget->month->format('F Y'),
            'limit' => $limit,
            'spent_before' => $spentBefore,
            'expense_amount' => $amount,
            'resulting_spend' => $resulting,
            'over_amount' => $overAmount,
            'already_exceeded' => $alreadyExceeded,
            'prior_over_amount' => $priorOver,
            'balance_sufficient' => $balanceSufficient,
        ];
    }

    /**
     * Aggregate spent amounts for many budgets in one query.
     *
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, string> category_id => spent
     */
    public function spentByCategoryForMonth(User $user, string $monthStart, Collection $budgets): array
    {
        if ($budgets->isEmpty()) {
            return [];
        }

        $categoryIds = $budgets->pluck('category_id')->unique()->values()->all();
        $monthEndExclusive = Carbon::parse($monthStart)->addMonthNoOverflow()->startOfMonth()->toDateString();

        $rows = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->whereIn('category_id', $categoryIds)
            ->whereDate('transaction_date', '>=', $monthStart)
            ->whereDate('transaction_date', '<', $monthEndExclusive)
            ->groupBy('category_id')
            ->selectRaw('category_id, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'category_id');

        $result = [];
        foreach ($categoryIds as $categoryId) {
            $result[(int) $categoryId] = MoneyMath::normalize($rows[(int) $categoryId] ?? $rows[(string) $categoryId] ?? 0);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enrichBudgets(User $user, Collection $budgets): array
    {
        if ($budgets->isEmpty()) {
            return [];
        }

        $budgets->each(fn (Budget $budget) => $budget->loadMissing('category:id,name,type,is_default'));

        // Group by month so we run one aggregate query per distinct month (bounded).
        $byMonth = $budgets->groupBy(fn (Budget $budget) => $budget->month->toDateString());
        $spentMaps = [];

        foreach ($byMonth as $monthStart => $monthBudgets) {
            $spentMaps[$monthStart] = $this->spentByCategoryForMonth($user, $monthStart, $monthBudgets);
        }

        return $budgets->map(function (Budget $budget) use ($spentMaps) {
            $monthKey = $budget->month->toDateString();
            $spent = $spentMaps[$monthKey][$budget->category_id] ?? '0.00';

            return $this->serialize($budget, $spent);
        })->values()->all();
    }

    /**
     * Current-month budgets for dashboard Budget vs Actual.
     *
     * @return list<array<string, mixed>>
     */
    public function currentMonthBudgetVsActual(User $user, ?CarbonInterface $now = null): array
    {
        $monthStart = Carbon::parse($now?->toDateString() ?? now()->toDateString())->startOfMonth()->toDateString();

        $budgets = $user->budgets()
            ->with('category:id,name,type,is_default')
            ->whereDate('month', $monthStart)
            ->orderBy('id')
            ->get();

        $payload = $this->enrichBudgets($user, $budgets);
        $this->evaluateAlerts($user, $payload);

        return $payload;
    }

    /**
     * Evaluate and create near/exceeded alerts without duplicates.
     *
     * @param  list<array<string, mixed>>  $enrichedBudgets
     */
    public function evaluateAlerts(User $user, array $enrichedBudgets): void
    {
        foreach ($enrichedBudgets as $item) {
            $status = BudgetStatus::from($item['status']);
            $budget = Budget::query()->whereKey($item['id'])->where('user_id', $user->id)->first();

            if ($budget === null) {
                continue;
            }

            if ($status === BudgetStatus::NearLimit || $status === BudgetStatus::Exceeded) {
                $this->ensureAlert($user, $budget, $item, BudgetAlertType::NearLimit);
            }

            if ($status === BudgetStatus::Exceeded) {
                $this->ensureAlert($user, $budget, $item, BudgetAlertType::Exceeded);
            }
        }
    }

    /**
     * Recalculate alerts after create/update for a single budget.
     */
    public function syncAlertsForBudget(Budget $budget): void
    {
        $budget->loadMissing('category:id,name,type,is_default', 'user');
        $enriched = $this->enrichBudgets($budget->user, collect([$budget]));

        if ($enriched === []) {
            return;
        }

        $item = $enriched[0];
        $status = BudgetStatus::from($item['status']);

        // If limit was raised and usage dropped below thresholds, clear alert locks
        // so they can fire again if usage climbs later. Existing notifications remain.
        if ($status === BudgetStatus::Healthy) {
            $budget->alerts()->delete();

            return;
        }

        if ($status === BudgetStatus::NearLimit) {
            $budget->alerts()->where('alert_type', BudgetAlertType::Exceeded->value)->delete();
            $this->ensureAlert($budget->user, $budget, $item, BudgetAlertType::NearLimit);

            return;
        }

        $this->ensureAlert($budget->user, $budget, $item, BudgetAlertType::NearLimit);
        $this->ensureAlert($budget->user, $budget, $item, BudgetAlertType::Exceeded);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function ensureAlert(User $user, Budget $budget, array $item, BudgetAlertType $type): void
    {
        try {
            DB::transaction(function () use ($user, $budget, $item, $type): void {
                $existing = BudgetAlert::query()
                    ->where('budget_id', $budget->id)
                    ->where('alert_type', $type->value)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return;
                }

                BudgetAlert::query()->create([
                    'budget_id' => $budget->id,
                    'alert_type' => $type->value,
                    'triggered_at' => now(),
                ]);

                if ($type === BudgetAlertType::NearLimit) {
                    $user->notify(new BudgetNearLimitNotification($item));
                } else {
                    $user->notify(new BudgetExceededNotification($item));
                }
            });
        } catch (Throwable) {
            // Unique constraint race: another request already recorded the alert.
        }
    }
}

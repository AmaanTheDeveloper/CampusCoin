<?php

namespace App\Services\Intelligence;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CategoryPaceNotification;
use App\Notifications\UnusualExpenseNotification;
use App\Services\BudgetService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Post-save expense alerts using existing budget + signal evidence.
 * Failures never roll back a valid expense save.
 */
class ExpenseAlertBridge
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly CategoryPaceDetectionService $pace,
    ) {}

    /**
     * @param  array<string, mixed>|null  $largeSignal  Serialized large-transaction signal from TransactionSignalService
     */
    public function handle(User $user, Transaction $transaction, ?array $largeSignal = null): void
    {
        try {
            abort_unless($transaction->user_id === $user->id, 404);

            if ($transaction->type !== TransactionType::Expense) {
                return;
            }

            $this->evaluateBudgets($user);
            $this->notifyUnusualExpense($user, $transaction, $largeSignal);
            $this->notifyCategoryPace($user, $transaction);
        } catch (Throwable $e) {
            Log::warning('expense_alert_bridge_failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'reason' => class_basename($e),
            ]);
        }
    }

    private function evaluateBudgets(User $user): void
    {
        try {
            $this->budgets->currentMonthBudgetVsActual($user);
        } catch (Throwable $e) {
            Log::warning('expense_budget_alert_failed', [
                'user_id' => $user->id,
                'reason' => class_basename($e),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $largeSignal
     */
    private function notifyUnusualExpense(User $user, Transaction $transaction, ?array $largeSignal): void
    {
        if ($largeSignal === null || ($largeSignal['signal_type'] ?? null) !== 'large_transaction') {
            return;
        }

        if ($this->alreadyNotified($user, 'unusual_expense', $transaction->id)) {
            return;
        }

        $categoryName = (string) (
            $largeSignal['explanation']['category_name']
            ?? $largeSignal['transaction']['category']
            ?? $transaction->category?->name
            ?? 'this category'
        );
        $amount = (string) (
            $largeSignal['explanation']['amount']
            ?? $largeSignal['transaction']['amount']
            ?? $transaction->amount
        );
        $reason = (string) ($largeSignal['reason'] ?? sprintf(
            'That Rs %s expense is significantly higher than your recent spending in %s.',
            number_format((float) $amount, 0),
            $categoryName,
        ));

        $what = sprintf(
            'You spent Rs %s on %s.',
            number_format((float) $amount, 0),
            $categoryName,
        );
        $why = mb_substr(strip_tags($reason), 0, 400);
        $action = 'Keep additional '.$categoryName.' spending lower for the rest of the month if protecting your balance or savings remains a priority.';
        $message = "What happened: {$what}\nWhy it matters: {$why}\nSuggested action: {$action}";

        $user->notify(new UnusualExpenseNotification([
            'transaction_id' => $transaction->id,
            'signal_id' => isset($largeSignal['id']) ? (int) $largeSignal['id'] : null,
            'category_name' => $categoryName,
            'amount' => $amount,
            'title' => 'Unusual expense detected',
            'message' => mb_substr($message, 0, 500),
            'what_happened' => $what,
            'why_it_matters' => $why,
            'suggested_action' => $action,
            'action_url' => '/spending-alerts',
        ]));
    }

    private function notifyCategoryPace(User $user, Transaction $transaction): void
    {
        $evidence = $this->pace->evaluate($user, $transaction);
        if ($evidence === null) {
            return;
        }

        // One pace alert per category per calendar month.
        $exists = $user->notifications()
            ->where('data->type', 'category_pace')
            ->where('data->category_id', $evidence['category_id'])
            ->where('data->month_key', $evidence['month_key'])
            ->exists();

        if ($exists) {
            return;
        }

        $what = sprintf(
            '%s spending is rising faster than usual this month.',
            $evidence['category_name'],
        );
        $why = sprintf(
            'You\'ve already spent Rs %s compared with about Rs %s around this point in recent months.',
            number_format((float) $evidence['spent_mtd'], 0),
            number_format((float) $evidence['typical_pace'], 0),
        );
        $action = 'Slow additional '.$evidence['category_name'].' spending for the rest of the month if staying on pace matters.';
        $message = "What happened: {$what}\nWhy it matters: {$why}\nSuggested action: {$action}";

        $user->notify(new CategoryPaceNotification([
            'transaction_id' => $transaction->id,
            'category_id' => $evidence['category_id'],
            'category_name' => $evidence['category_name'],
            'month_key' => $evidence['month_key'],
            'title' => $evidence['category_name'].' spending is rising',
            'message' => mb_substr($message, 0, 500),
            'what_happened' => $what,
            'why_it_matters' => $why,
            'suggested_action' => $action,
            'spent_mtd' => $evidence['spent_mtd'],
            'typical_pace' => $evidence['typical_pace'],
            'action_url' => '/transactions',
        ]));
    }

    private function alreadyNotified(User $user, string $type, int $transactionId): bool
    {
        return $user->notifications()
            ->where('data->type', $type)
            ->where('data->transaction_id', $transactionId)
            ->exists();
    }
}

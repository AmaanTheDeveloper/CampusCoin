<?php

namespace App\Services\Intelligence;

use App\Enums\TransactionSignalSeverity;
use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionSignal;
use App\Models\User;
use App\Support\MoneyMath;
use Carbon\Carbon;

/**
 * Unusual Spending Detector — deterministic, category-scoped, user-owned.
 *
 * Flags an expense when:
 *   amount >= UNUSUAL_SPEND_MULTIPLIER × recent category average
 *   AND amount >= absolute floor
 *
 * Requires enough prior same-category expenses inside the lookback window.
 * Never invents baselines; never uses other users' data; never blocks saves.
 */
class LargeTransactionDetectionService
{
    /**
     * Evaluate whether an expense is unusually large vs the user's category baseline.
     * Does not mutate the transaction. Non-expenses are never flagged.
     */
    public function evaluate(User $user, Transaction $transaction): ?TransactionSignal
    {
        abort_unless($transaction->user_id === $user->id, 404);

        $minObservations = max(1, (int) config('intelligence.large_min_observations', 5));
        $multiplier = (float) config('intelligence.large_multiplier', 2.5);
        $floor = MoneyMath::normalize(config('intelligence.large_absolute_floor', 500));
        $lookbackDays = max(1, (int) config('intelligence.large_lookback_days', 90));

        $fingerprint = $this->fingerprint($transaction);

        // Clear stale open large signals for this transaction when re-evaluating.
        TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('transaction_id', $transaction->id)
            ->where('signal_type', TransactionSignalType::LargeTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->where('fingerprint', '!=', $fingerprint)
            ->delete();

        $existing = TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing !== null && $existing->status !== TransactionSignalStatus::Open) {
            // User already dismissed/reviewed this exact finding — no duplicate alert.
            return null;
        }

        // Unusual spending applies to expenses only (not income).
        if ($transaction->type !== TransactionType::Expense) {
            if ($existing?->status === TransactionSignalStatus::Open) {
                $existing->delete();
            }

            return null;
        }

        $baseline = $this->categoryBaseline($user, $transaction, $lookbackDays);

        if ($baseline === null || $baseline['count'] < $minObservations) {
            if ($existing?->status === TransactionSignalStatus::Open) {
                $existing->delete();
            }

            return null;
        }

        $amount = MoneyMath::normalize($transaction->amount);
        $average = $baseline['average'];

        // Guard zero/empty average — never divide or invent a threshold.
        if (MoneyMath::compare($average, '0.00') <= 0) {
            if ($existing?->status === TransactionSignalStatus::Open) {
                $existing->delete();
            }

            return null;
        }

        $threshold = MoneyMath::normalize((float) $average * $multiplier);

        $isLarge = MoneyMath::compare($amount, $threshold) >= 0
            && MoneyMath::compare($amount, $floor) >= 0;

        if (! $isLarge) {
            if ($existing?->status === TransactionSignalStatus::Open) {
                $existing->delete();
            }

            return null;
        }

        $categoryName = $baseline['category_name'];
        $deviationRatio = round((float) $amount / (float) $average, 2);
        $difference = MoneyMath::sub($amount, $average);
        $severity = MoneyMath::compare($amount, MoneyMath::normalize((float) $average * ($multiplier + 1))) >= 0
            ? TransactionSignalSeverity::High
            : TransactionSignalSeverity::Medium;

        $reason = sprintf(
            'Your %s spending is usually around Rs. %s per transaction. This Rs. %s expense is about %.1f× your recent average.',
            $categoryName,
            number_format((float) $average, 2),
            number_format((float) $amount, 2),
            $deviationRatio,
        );

        $payload = [
            'amount' => $amount,
            'baseline_average' => $average,
            'threshold' => $threshold,
            'multiplier' => $multiplier,
            'deviation_ratio' => $deviationRatio,
            'difference' => $difference,
            'observations' => $baseline['count'],
            'lookback_days' => $lookbackDays,
            'category_name' => $categoryName,
            'category_id' => $transaction->category_id,
        ];

        if ($existing !== null) {
            $existing->update([
                'severity' => $severity,
                'reason' => $reason,
                'payload' => $payload,
                'detected_at' => now(),
            ]);

            return $existing->fresh(['transaction.category:id,name']);
        }

        return TransactionSignal::query()->create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'comparison_transaction_id' => null,
            'signal_type' => TransactionSignalType::LargeTransaction,
            'severity' => $severity,
            'status' => TransactionSignalStatus::Open,
            'reason' => $reason,
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'detected_at' => now(),
            'resolved_at' => null,
        ])->load(['transaction.category:id,name']);
    }

    /**
     * Recent same-category expense baseline for this user.
     *
     * Window: [transaction_date − lookback_days, transaction_date], excluding the txn itself.
     *
     * @return array{average: string, count: int, category_name: string}|null
     */
    private function categoryBaseline(User $user, Transaction $transaction, int $lookbackDays): ?array
    {
        $asOf = Carbon::parse($transaction->transaction_date->toDateString())->startOfDay();
        $windowStart = $asOf->copy()->subDays($lookbackDays)->toDateString();
        $windowEnd = $asOf->toDateString();

        $query = $user->transactions()
            ->where('type', TransactionType::Expense->value)
            ->where('id', '!=', $transaction->id)
            ->whereDate('transaction_date', '>=', $windowStart)
            ->whereDate('transaction_date', '<=', $windowEnd);

        if ($transaction->category_id !== null) {
            $query->where('category_id', $transaction->category_id);
            $categoryName = $transaction->category?->name
                ?? $transaction->category()->value('name')
                ?? 'this category';
        } else {
            $query->whereNull('category_id');
            $categoryName = 'Uncategorized';
        }

        $stats = $query
            ->selectRaw('COALESCE(AVG(amount), 0) as avg_amount, COUNT(*) as observation_count')
            ->first();

        $count = (int) ($stats->observation_count ?? 0);
        if ($count < 1) {
            return null;
        }

        return [
            'average' => MoneyMath::normalize($stats->avg_amount),
            'count' => $count,
            'category_name' => $categoryName,
        ];
    }

    private function fingerprint(Transaction $transaction): string
    {
        return hash('sha256', implode('|', [
            'large',
            (string) $transaction->user_id,
            (string) $transaction->id,
            MoneyMath::normalize($transaction->amount),
            (string) ($transaction->category_id ?? 'null'),
            $transaction->type->value,
        ]));
    }
}

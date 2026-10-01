<?php

namespace App\Services\Intelligence;

use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Models\Transaction;
use App\Models\TransactionSignal;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestrates large + duplicate signal evaluation after transaction writes.
 * Failures never roll back a valid financial save.
 */
class TransactionSignalService
{
    public function __construct(
        private readonly LargeTransactionDetectionService $large,
        private readonly DuplicateDetectionService $duplicates,
    ) {}

    /**
     * Evaluate signals after a transaction write.
     * Returns a serialized unusual-spending alert when one was created/updated; otherwise null.
     * Failures are logged and never roll back the financial save.
     *
     * @return array<string, mixed>|null
     */
    public function evaluateTransaction(User $user, Transaction $transaction): ?array
    {
        try {
            abort_unless($transaction->user_id === $user->id, 404);
            $transaction->loadMissing('category:id,name');

            $large = $this->large->evaluate($user, $transaction);
            $this->duplicates->evaluate($user, $transaction);

            return $large !== null ? $this->serialize($large) : null;
        } catch (Throwable $e) {
            Log::warning('transaction_signal_evaluation_failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<int>  $transactionIds
     * @return int Open duplicate candidates involving the imported/evaluated transactions
     */
    public function evaluateMany(User $user, array $transactionIds): int
    {
        try {
            $transactions = $user->transactions()
                ->whereIn('id', $transactionIds)
                ->with('category:id,name')
                ->get();

            foreach ($transactions as $transaction) {
                $this->large->evaluate($user, $transaction);
            }

            return $this->duplicates->evaluateMany($user, $transactionIds);
        } catch (Throwable $e) {
            Log::warning('transaction_signal_batch_failed', [
                'user_id' => $user->id,
                'reason' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Duplicate Review page payload (open candidates + recently resolved).
     *
     * @return array{pending: list<array<string, mixed>>, resolved: list<array<string, mixed>>, pending_count: int, disclaimer: string}
     */
    public function duplicateReviewForUser(User $user, int $limit = 40): array
    {
        $signals = TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->with([
                'transaction.category:id,name',
                'comparisonTransaction.category:id,name',
            ])
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->filter(function (TransactionSignal $signal) {
                if ($signal->status !== TransactionSignalStatus::Open) {
                    return true;
                }

                return $signal->transaction !== null && $signal->comparisonTransaction !== null;
            })
            ->values()
            ->map(fn (TransactionSignal $signal) => $this->serializeDuplicate($signal))
            ->all();

        $pending = array_values(array_filter(
            $signals,
            fn (array $s) => ($s['status'] ?? '') === TransactionSignalStatus::Open->value
        ));
        $resolved = array_values(array_filter(
            $signals,
            fn (array $s) => ($s['status'] ?? '') !== TransactionSignalStatus::Open->value
        ));

        return [
            'pending' => $pending,
            'resolved' => $resolved,
            'pending_count' => count($pending),
            'disclaimer' => 'Possible duplicates are flagged when expenses share the same normalized merchant, exact amount, and date (or a tiny date window). Review carefully — legitimate repeats are common. CampusCoin never auto-deletes transactions.',
            'detection' => [
                'date_window_days' => (int) config('intelligence.duplicate_date_window_days', 0),
                'expense_only' => true,
            ],
        ];
    }

    public function keepBoth(User $user, TransactionSignal $signal): void
    {
        abort_unless($signal->user_id === $user->id, 404);
        abort_unless($signal->signal_type === TransactionSignalType::DuplicateTransaction, 404);

        $signal->update([
            'status' => TransactionSignalStatus::KeptBoth,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Soft-archive the explicitly selected transaction in the pair (never hard-delete).
     */
    public function removeDuplicate(User $user, TransactionSignal $signal, int $removeTransactionId): void
    {
        abort_unless($signal->user_id === $user->id, 404);
        abort_unless($signal->signal_type === TransactionSignalType::DuplicateTransaction, 404);

        $pairIds = array_filter([
            $signal->transaction_id,
            $signal->comparison_transaction_id,
        ]);

        if (! in_array($removeTransactionId, $pairIds, true)) {
            throw ValidationException::withMessages([
                'remove_transaction_id' => 'Choose one of the two transactions in this possible-duplicate pair.',
            ]);
        }

        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->whereKey($removeTransactionId)
            ->firstOrFail();

        if ($user->cannot('delete', $transaction)) {
            abort(403);
        }

        DB::transaction(function () use ($user, $signal, $transaction, $removeTransactionId) {
            if (! $transaction->trashed()) {
                $transaction->delete();
            }

            $payload = is_array($signal->payload) ? $signal->payload : [];
            $payload['removed_transaction_id'] = $removeTransactionId;

            $signal->update([
                'status' => TransactionSignalStatus::RemovedDuplicate,
                'resolved_at' => now(),
                'payload' => $payload,
            ]);

            $this->duplicates->resolveOpenForArchivedTransaction($user, $transaction);
        });
    }

    public function pendingDuplicateCount(User $user): int
    {
        return $this->duplicates->openCountForUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDuplicate(TransactionSignal $signal): array
    {
        $base = $this->serialize($signal);
        $payload = is_array($signal->payload) ? $signal->payload : [];
        $primary = $signal->transaction;
        $peer = $signal->comparisonTransaction;

        $suggestedRemove = (int) ($payload['suggested_remove_transaction_id'] ?? ($peer?->id ?? 0));
        $suggestedKeep = (int) ($payload['suggested_keep_transaction_id'] ?? ($primary?->id ?? 0));

        $base['match_type'] = $payload['match_type'] ?? $payload['match_tier'] ?? DuplicateDetectionService::MATCH_HIGH;
        $base['evidence'] = is_array($payload['evidence'] ?? null) ? $payload['evidence'] : [];
        $base['suggested_remove_transaction_id'] = $suggestedRemove > 0 ? $suggestedRemove : null;
        $base['suggested_keep_transaction_id'] = $suggestedKeep > 0 ? $suggestedKeep : null;
        $base['side_by_side'] = [
            'a' => $this->transactionSide($primary, 'keep'),
            'b' => $this->transactionSide($peer, 'remove'),
        ];

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function transactionSide(?Transaction $txn, string $role): ?array
    {
        if ($txn === null) {
            return null;
        }

        return [
            'id' => $txn->id,
            'description' => $txn->description ?: 'No description',
            'amount' => MoneyMath::normalize($txn->amount),
            'type' => $txn->type->value,
            'category' => $txn->category?->name,
            'transaction_date' => $txn->transaction_date->toDateString(),
            'created_at' => $txn->created_at?->toIso8601String(),
            'source_hint' => $role === 'remove'
                ? 'Likely newer / import candidate'
                : 'Earlier record (prefer keep)',
        ];
    }

    public function dismiss(User $user, TransactionSignal $signal): void
    {
        abort_unless($signal->user_id === $user->id, 404);

        $signal->update([
            'status' => TransactionSignalStatus::Dismissed,
            'resolved_at' => now(),
        ]);
    }

    public function markReviewed(User $user, TransactionSignal $signal): void
    {
        abort_unless($signal->user_id === $user->id, 404);

        $signal->update([
            'status' => TransactionSignalStatus::Reviewed,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Spending Alerts page: unusual expense signals only (open + recently resolved).
     *
     * @return array{unread: list<array<string, mixed>>, earlier: list<array<string, mixed>>, open_count: int}
     */
    public function spendingAlertsForUser(User $user, int $limit = 30): array
    {
        $signals = TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::LargeTransaction->value)
            ->whereHas('transaction', fn ($query) => $query->where('type', 'expense'))
            ->with(['transaction.category:id,name'])
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn (TransactionSignal $signal) => $this->serialize($signal))
            ->all();

        $unread = array_values(array_filter($signals, fn (array $s) => ($s['status'] ?? '') === TransactionSignalStatus::Open->value));
        $earlier = array_values(array_filter($signals, fn (array $s) => ($s['status'] ?? '') !== TransactionSignalStatus::Open->value));

        return [
            'unread' => $unread,
            'earlier' => $earlier,
            'open_count' => count($unread),
            'disclaimer' => 'Unusual spending alerts compare each expense to your own recent category average. No AI scoring is used.',
            'detection' => [
                'multiplier' => (float) config('intelligence.large_multiplier', 2.5),
                'min_observations' => (int) config('intelligence.large_min_observations', 5),
                'lookback_days' => (int) config('intelligence.large_lookback_days', 90),
                'absolute_floor' => MoneyMath::normalize(config('intelligence.large_absolute_floor', 500)),
            ],
        ];
    }

    /**
     * Open signals whose primary/comparison transactions still exist (not soft-deleted).
     *
     * @return list<array<string, mixed>>
     */
    public function openSignalsForUser(User $user, int $limit = 10): array
    {
        return TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('status', TransactionSignalStatus::Open->value)
            ->whereHas('transaction')
            ->with([
                'transaction.category:id,name',
                'comparisonTransaction.category:id,name',
            ])
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->filter(function (TransactionSignal $signal) {
                if ($signal->signal_type === TransactionSignalType::DuplicateTransaction) {
                    return $signal->comparisonTransaction !== null;
                }

                return true;
            })
            ->values()
            ->map(fn (TransactionSignal $signal) => $this->serialize($signal))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(TransactionSignal $signal): array
    {
        $txn = $signal->transaction;
        $peer = $signal->comparisonTransaction;
        $payload = is_array($signal->payload) ? $signal->payload : [];

        return [
            'id' => $signal->id,
            'signal_type' => $signal->signal_type->value,
            'signal_label' => $signal->signal_type->label(),
            'severity' => $signal->severity->value,
            'status' => $signal->status->value,
            'reason' => $signal->reason,
            'detected_at' => $signal->detected_at?->toIso8601String(),
            'is_unread' => $signal->status === TransactionSignalStatus::Open,
            'explanation' => [
                'amount' => $payload['amount'] ?? ($txn ? MoneyMath::normalize($txn->amount) : null),
                'baseline_average' => $payload['baseline_average'] ?? null,
                'difference' => $payload['difference'] ?? null,
                'deviation_ratio' => $payload['deviation_ratio'] ?? null,
                'threshold' => $payload['threshold'] ?? null,
                'observations' => $payload['observations'] ?? null,
                'lookback_days' => $payload['lookback_days'] ?? null,
                'category_name' => $payload['category_name'] ?? ($txn?->category?->name),
                'multiplier' => $payload['multiplier'] ?? null,
            ],
            'transaction' => $txn ? [
                'id' => $txn->id,
                'description' => $txn->description ?: 'No description',
                'amount' => MoneyMath::normalize($txn->amount),
                'type' => $txn->type->value,
                'category' => $txn->category?->name,
                'transaction_date' => $txn->transaction_date->toDateString(),
            ] : null,
            'comparison_transaction' => $peer ? [
                'id' => $peer->id,
                'description' => $peer->description ?: 'No description',
                'amount' => MoneyMath::normalize($peer->amount),
                'type' => $peer->type->value,
                'category' => $peer->category?->name,
                'transaction_date' => $peer->transaction_date->toDateString(),
            ] : null,
        ];
    }
}

<?php

namespace App\Services\Intelligence;

use App\Enums\TransactionActivityType;
use App\Models\Transaction;
use App\Models\TransactionActivity;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecentActivityService
{
    /**
     * Record that the user opened a transaction (edit/detail view).
     * Failures are swallowed so viewing never breaks.
     */
    public function recordView(User $user, Transaction $transaction): void
    {
        try {
            abort_unless($transaction->user_id === $user->id, 404);

            TransactionActivity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'activity_type' => TransactionActivityType::Viewed->value,
                ],
                [
                    'occurred_at' => now(),
                ],
            );
        } catch (Throwable $e) {
            Log::warning('transaction_activity_view_failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a successful persisted update.
     */
    public function recordEdit(User $user, Transaction $transaction): void
    {
        try {
            abort_unless($transaction->user_id === $user->id, 404);

            TransactionActivity::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'activity_type' => TransactionActivityType::Edited->value,
                ],
                [
                    'occurred_at' => now(),
                ],
            );
        } catch (Throwable $e) {
            Log::warning('transaction_activity_edit_failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{viewed: list<array<string, mixed>>, edited: list<array<string, mixed>>}
     */
    public function forUser(User $user, ?int $limit = null): array
    {
        $limit ??= (int) config('intelligence.activity_limit', 10);

        return [
            'viewed' => $this->list($user, TransactionActivityType::Viewed, $limit),
            'edited' => $this->list($user, TransactionActivityType::Edited, $limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(User $user, TransactionActivityType $type, int $limit): array
    {
        return TransactionActivity::query()
            ->where('user_id', $user->id)
            ->where('activity_type', $type->value)
            ->whereHas('transaction') // excludes soft-deleted transactions
            ->with(['transaction.category:id,name'])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(function (TransactionActivity $activity) {
                $txn = $activity->transaction;

                return [
                    'id' => $activity->id,
                    'transaction_id' => $txn->id,
                    'description' => $txn->description ?: 'No description',
                    'amount' => MoneyMath::normalize($txn->amount),
                    'type' => $txn->type->value,
                    'category' => $txn->category?->name,
                    'transaction_date' => $txn->transaction_date->toDateString(),
                    'occurred_at' => $activity->occurred_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}

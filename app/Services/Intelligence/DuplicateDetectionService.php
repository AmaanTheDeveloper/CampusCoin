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
use Illuminate\Support\Collection;

/**
 * Deterministic possible-duplicate detector (review-only; never auto-deletes).
 *
 * High confidence: same user + expense + normalized description + exact amount + same calendar day.
 * Moderate: same fields with date within configured ± window days (when window > 0).
 */
class DuplicateDetectionService
{
    public const MATCH_HIGH = 'high';

    public const MATCH_MODERATE = 'moderate';

    /**
     * Find likely duplicate peers for a transaction and upsert open signals.
     * Never merges or deletes transactions.
     *
     * @return list<TransactionSignal>
     */
    public function evaluate(User $user, Transaction $transaction): array
    {
        abort_unless($transaction->user_id === $user->id, 404);

        // Duplicate Review focuses on expenses (CSV import accidental doubles).
        if ($transaction->type !== TransactionType::Expense) {
            $this->clearOpenDuplicatesForTransaction($user, $transaction);

            return [];
        }

        $normalizedDescription = $this->normalizeDescription($transaction->description);
        if ($normalizedDescription === '') {
            $this->clearOpenDuplicatesForTransaction($user, $transaction);

            return [];
        }

        $windowDays = max(0, (int) config('intelligence.duplicate_date_window_days', 0));
        $date = Carbon::parse($transaction->transaction_date->toDateString());
        $from = $date->copy()->subDays($windowDays)->toDateString();
        $to = $date->copy()->addDays($windowDays)->toDateString();
        $amount = MoneyMath::normalize($transaction->amount);

        $candidates = $user->transactions()
            ->where('id', '!=', $transaction->id)
            ->where('type', TransactionType::Expense->value)
            ->where('amount', $amount)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->with('category:id,name')
            ->orderBy('id')
            ->limit(25)
            ->get();

        $matched = $candidates->filter(function (Transaction $candidate) use ($normalizedDescription) {
            return $this->normalizeDescription($candidate->description) === $normalizedDescription;
        });

        $created = [];
        $activeFingerprints = [];

        foreach ($matched as $peer) {
            $leftId = min($transaction->id, $peer->id);
            $rightId = max($transaction->id, $peer->id);
            $sameDay = $transaction->transaction_date->toDateString() === $peer->transaction_date->toDateString();
            $matchType = $sameDay ? self::MATCH_HIGH : self::MATCH_MODERATE;

            // Pair fingerprint is direction-independent (A↔B === B↔A).
            $fingerprint = $this->fingerprint($user->id, $leftId, $rightId, $matchType);
            $activeFingerprints[] = $fingerprint;

            $existing = TransactionSignal::query()
                ->where('user_id', $user->id)
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($existing !== null && $existing->status !== TransactionSignalStatus::Open) {
                continue;
            }

            $evidence = $this->buildEvidence($transaction, $peer, $matchType, $normalizedDescription, $amount, $from, $to);
            $reason = $evidence['summary'];
            $severity = $matchType === self::MATCH_HIGH
                ? TransactionSignalSeverity::High
                : TransactionSignalSeverity::Medium;

            // Newer row is the default removal candidate (often the CSV re-import).
            $suggestedRemoveId = max($transaction->id, $peer->id);
            $suggestedKeepId = min($transaction->id, $peer->id);

            $payload = [
                'amount' => $amount,
                'normalized_description' => $normalizedDescription,
                'match_type' => $matchType,
                'match_tier' => $matchType,
                'date_window' => ['from' => $from, 'to' => $to],
                'peer_id' => $peer->id,
                'evidence' => $evidence['points'],
                'suggested_remove_transaction_id' => $suggestedRemoveId,
                'suggested_keep_transaction_id' => $suggestedKeepId,
            ];

            if ($existing !== null) {
                $existing->update([
                    'transaction_id' => $suggestedKeepId,
                    'comparison_transaction_id' => $suggestedRemoveId,
                    'severity' => $severity,
                    'reason' => $reason,
                    'payload' => $payload,
                    'detected_at' => now(),
                ]);
                $created[] = $existing->fresh();

                continue;
            }

            $created[] = TransactionSignal::query()->create([
                'user_id' => $user->id,
                'transaction_id' => $suggestedKeepId,
                'comparison_transaction_id' => $suggestedRemoveId,
                'signal_type' => TransactionSignalType::DuplicateTransaction,
                'severity' => $severity,
                'status' => TransactionSignalStatus::Open,
                'reason' => $reason,
                'fingerprint' => $fingerprint,
                'payload' => $payload,
                'detected_at' => now(),
                'resolved_at' => null,
            ]);
        }

        $stale = TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->where(function ($q) use ($transaction) {
                $q->where('transaction_id', $transaction->id)
                    ->orWhere('comparison_transaction_id', $transaction->id);
            });

        if ($activeFingerprints !== []) {
            $stale->whereNotIn('fingerprint', $activeFingerprints);
        }

        $stale->delete();

        return $created;
    }

    /**
     * @param  list<int>  $transactionIds
     * @return int Number of open duplicate signals involving any of the given transactions
     */
    public function evaluateMany(User $user, array $transactionIds): int
    {
        if ($transactionIds === []) {
            return 0;
        }

        /** @var Collection<int, Transaction> $transactions */
        $transactions = $user->transactions()
            ->whereIn('id', $transactionIds)
            ->with('category:id,name')
            ->get();

        foreach ($transactions as $transaction) {
            $this->evaluate($user, $transaction);
        }

        return $this->openCountForTransactions($user, $transactionIds);
    }

    /**
     * @param  list<int>  $transactionIds
     */
    public function openCountForTransactions(User $user, array $transactionIds): int
    {
        if ($transactionIds === []) {
            return 0;
        }

        return TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->where(function ($q) use ($transactionIds) {
                $q->whereIn('transaction_id', $transactionIds)
                    ->orWhereIn('comparison_transaction_id', $transactionIds);
            })
            ->whereHas('transaction')
            ->whereHas('comparisonTransaction')
            ->count();
    }

    public function openCountForUser(User $user): int
    {
        return TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->whereHas('transaction')
            ->whereHas('comparisonTransaction')
            ->count();
    }

    /**
     * When a transaction is archived/deleted, close open duplicate candidates that reference it.
     */
    public function resolveOpenForArchivedTransaction(User $user, Transaction $transaction): void
    {
        TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->where(function ($q) use ($transaction) {
                $q->where('transaction_id', $transaction->id)
                    ->orWhere('comparison_transaction_id', $transaction->id);
            })
            ->update([
                'status' => TransactionSignalStatus::Dismissed->value,
                'resolved_at' => now(),
            ]);
    }

    public function fingerprint(int $userId, int $leftId, int $rightId, string $matchType): string
    {
        return hash('sha256', implode('|', [
            'duplicate',
            (string) $userId,
            (string) min($leftId, $rightId),
            (string) max($leftId, $rightId),
            $matchType,
        ]));
    }

    /**
     * Normalize merchant/description for conservative equality matching.
     * Trim → lowercase → unify quotes/apostrophes → strip punctuation → collapse whitespace.
     */
    public function normalizeDescription(?string $description): string
    {
        $value = strtolower(trim((string) $description));
        if ($value === '') {
            return '';
        }

        $value = str_replace(["'", "'", '`', '´', '"', '"'], '', $value);
        $value = preg_replace('/[^a-z0-9\s]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function clearOpenDuplicatesForTransaction(User $user, Transaction $transaction): void
    {
        TransactionSignal::query()
            ->where('user_id', $user->id)
            ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
            ->where('status', TransactionSignalStatus::Open->value)
            ->where(function ($q) use ($transaction) {
                $q->where('transaction_id', $transaction->id)
                    ->orWhere('comparison_transaction_id', $transaction->id);
            })
            ->delete();
    }

    /**
     * @return array{summary: string, points: list<string>}
     */
    private function buildEvidence(
        Transaction $a,
        Transaction $b,
        string $matchType,
        string $normalizedDescription,
        string $amount,
        string $from,
        string $to,
    ): array {
        $points = [
            'Same expense type',
            'Same normalized merchant/description ('.$normalizedDescription.')',
            'Exact amount match (Rs. '.number_format((float) $amount, 2).')',
        ];

        if ($matchType === self::MATCH_HIGH) {
            $points[] = 'Same calendar day ('.$a->transaction_date->toDateString().')';
            $summary = 'Same merchant, amount and date.';
        } else {
            $points[] = 'Dates within configured window ('.$from.' to '.$to.')';
            $summary = 'Same normalized merchant and amount within the allowed date window.';
        }

        return ['summary' => $summary, 'points' => $points];
    }
}

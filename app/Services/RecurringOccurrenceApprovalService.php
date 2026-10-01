<?php

namespace App\Services;

use App\Enums\RecurringOccurrenceStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RecurringOccurrenceApprovalService
{
    /**
     * Approve a pending/rejected occurrence: post exactly one transaction (idempotent).
     *
     * @return array{occurrence: RecurringTransactionOccurrence, transaction: Transaction, created: bool}
     */
    public function approve(User $user, RecurringTransactionOccurrence $occurrence): array
    {
        return DB::transaction(function () use ($user, $occurrence) {
            /** @var RecurringTransactionOccurrence $locked */
            $locked = RecurringTransactionOccurrence::query()
                ->whereKey($occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rule = RecurringTransaction::query()
                ->whereKey($locked->recurring_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $rule->user_id !== (int) $user->id) {
                throw new RuntimeException('Forbidden recurring occurrence.');
            }

            if ($locked->status === RecurringOccurrenceStatus::Approved && $locked->transaction_id !== null) {
                $existing = Transaction::query()->findOrFail($locked->transaction_id);

                return [
                    'occurrence' => $locked,
                    'transaction' => $existing,
                    'created' => false,
                ];
            }

            if (! $locked->status->isActionable()) {
                throw ValidationException::withMessages([
                    'occurrence' => 'This recurring occurrence cannot be approved.',
                ]);
            }

            $this->assertCategoryStillValid($user, $rule);

            if ($rule->type === TransactionType::Expense
                && ! AvailableBalance::canAffordExpense($user, (string) $rule->amount)) {
                $available = AvailableBalance::forUser($user);

                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Insufficient Balance: Your available balance is Rs %s, but this expense is Rs %s. Please reduce the expense amount or add income first.',
                        number_format((float) $available, 2),
                        number_format((float) $rule->amount, 2),
                    ),
                ]);
            }

            $transaction = $user->transactions()->create([
                'category_id' => $rule->category_id,
                'type' => $rule->type,
                'amount' => $rule->amount,
                'description' => $rule->description,
                'transaction_date' => $locked->occurrence_date->toDateString(),
            ]);

            $locked->forceFill([
                'status' => RecurringOccurrenceStatus::Approved,
                'transaction_id' => $transaction->id,
                'decided_at' => now(),
            ])->save();

            return [
                'occurrence' => $locked->fresh(),
                'transaction' => $transaction,
                'created' => true,
            ];
        });
    }

    /**
     * Reject without posting. Reminder job will re-notify on subsequent days.
     */
    public function reject(User $user, RecurringTransactionOccurrence $occurrence): RecurringTransactionOccurrence
    {
        return DB::transaction(function () use ($user, $occurrence) {
            /** @var RecurringTransactionOccurrence $locked */
            $locked = RecurringTransactionOccurrence::query()
                ->whereKey($occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rule = RecurringTransaction::query()
                ->whereKey($locked->recurring_transaction_id)
                ->firstOrFail();

            if ((int) $rule->user_id !== (int) $user->id) {
                throw new RuntimeException('Forbidden recurring occurrence.');
            }

            if ($locked->status === RecurringOccurrenceStatus::Approved) {
                throw ValidationException::withMessages([
                    'occurrence' => 'This recurring occurrence was already approved.',
                ]);
            }

            if ($locked->status === RecurringOccurrenceStatus::Rejected) {
                return $locked;
            }

            $locked->forceFill([
                'status' => RecurringOccurrenceStatus::Rejected,
                'decided_at' => now(),
            ])->save();

            return $locked->fresh();
        });
    }

    private function assertCategoryStillValid(User $owner, RecurringTransaction $rule): void
    {
        $category = Category::query()->find($rule->category_id);

        if ($category === null || $category->type !== $rule->type) {
            throw ValidationException::withMessages([
                'occurrence' => 'Recurring category is no longer valid.',
            ]);
        }

        $accessible = Category::query()
            ->accessibleTo($owner)
            ->whereKey($rule->category_id)
            ->exists();

        if (! $accessible) {
            throw ValidationException::withMessages([
                'occurrence' => 'Recurring category is no longer accessible.',
            ]);
        }
    }

    /**
     * Wallet after hypothetical approval (display helpers).
     */
    public function wouldLeaveBalance(User $user, RecurringTransaction $rule): string
    {
        $current = AvailableBalance::forUser($user);

        if ($rule->type === TransactionType::Income) {
            return MoneyMath::add($current, (string) $rule->amount);
        }

        return MoneyMath::sub($current, (string) $rule->amount);
    }
}

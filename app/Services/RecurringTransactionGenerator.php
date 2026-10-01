<?php

namespace App\Services;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Exceptions\RecurringOccurrenceDeferredException;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use App\Models\User;
use App\Notifications\RecurringApprovalNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecurringTransactionGenerator
{
    /**
     * Create pending approval occurrences up to $asOf (inclusive), never past today.
     * Does NOT post financial transactions — approval is required.
     *
     * Also sends next-day reminders for actionable (pending/rejected) occurrences.
     *
     * @return array{generated: int, skipped: int, reminded: int}
     */
    public function generateDue(?CarbonInterface $asOf = null): array
    {
        $today = Carbon::parse(now()->toDateString())->startOfDay();
        $asOf = Carbon::parse($asOf?->toDateString() ?? $today->toDateString())->startOfDay();

        // Occurrences must never be created for future calendar days.
        if ($asOf->gt($today)) {
            $asOf = $today->copy();
        }

        $generated = 0;
        $skipped = 0;

        $rules = RecurringTransaction::query()
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', $asOf->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $result = $this->generateForRule($rule, $asOf);
            $generated += $result['generated'];
            $skipped += $result['skipped'];
        }

        $reminded = $this->sendReminders($asOf);

        return compact('generated', 'skipped', 'reminded');
    }

    /**
     * @return array{generated: int, skipped: int}
     */
    public function generateForRule(RecurringTransaction $rule, CarbonInterface $asOf): array
    {
        $generated = 0;
        $skipped = 0;
        $cursor = Carbon::parse($rule->next_run_date->toDateString())->startOfDay();
        $endLimit = $rule->end_date
            ? Carbon::parse($rule->end_date->toDateString())->startOfDay()
            : null;
        $anchorDay = (int) Carbon::parse($rule->start_date->toDateString())->day;
        $safety = 0;

        while ($cursor->lte($asOf) && ($endLimit === null || $cursor->lte($endLimit)) && $safety < 500) {
            $safety++;

            try {
                DB::transaction(function () use ($rule, $cursor, $anchorDay, &$generated, &$skipped): void {
                    $exists = RecurringTransactionOccurrence::query()
                        ->where('recurring_transaction_id', $rule->id)
                        ->whereDate('occurrence_date', $cursor->toDateString())
                        ->lockForUpdate()
                        ->exists();

                    if ($exists) {
                        $skipped++;
                    } else {
                        /** @var User $owner */
                        $owner = User::query()->findOrFail($rule->user_id);
                        $rule->loadMissing('category:id,name,type');

                        $this->assertCategoryStillValid($owner, $rule);

                        $occurrence = RecurringTransactionOccurrence::query()->create([
                            'recurring_transaction_id' => $rule->id,
                            'occurrence_date' => $cursor->toDateString(),
                            'status' => RecurringOccurrenceStatus::PendingApproval,
                            'transaction_id' => null,
                            'last_reminded_at' => now(),
                        ]);

                        $owner->notify(new RecurringApprovalNotification($occurrence, $rule, false));

                        $generated++;
                    }

                    $next = $this->advanceDate($cursor, $rule->frequency, $anchorDay);
                    $rule->forceFill([
                        'next_run_date' => $next->toDateString(),
                        'last_generated_at' => now(),
                    ])->save();
                });
            } catch (RecurringOccurrenceDeferredException) {
                // Leave next_run_date unchanged so generation can retry when conditions allow.
                $skipped++;
                break;
            } catch (Throwable) {
                $skipped++;
                $rule->refresh();
                $occurrenceExists = RecurringTransactionOccurrence::query()
                    ->where('recurring_transaction_id', $rule->id)
                    ->whereDate('occurrence_date', $cursor->toDateString())
                    ->exists();

                if ($occurrenceExists && $rule->next_run_date->lte($cursor)) {
                    $next = $this->advanceDate($cursor, $rule->frequency, $anchorDay);
                    $rule->forceFill(['next_run_date' => $next->toDateString()])->save();
                } else {
                    break;
                }
            }

            $rule->refresh();
            $cursor = Carbon::parse($rule->next_run_date->toDateString())->startOfDay();

            if ($endLimit !== null && $cursor->gt($endLimit)) {
                $rule->forceFill(['is_active' => false])->save();
                break;
            }
        }

        return compact('generated', 'skipped');
    }

    /**
     * Send at most one reminder per actionable occurrence per calendar day.
     */
    public function sendReminders(CarbonInterface $asOf): int
    {
        $asOfDate = Carbon::parse($asOf->toDateString())->startOfDay();
        $reminded = 0;

        $occurrences = RecurringTransactionOccurrence::query()
            ->whereIn('status', [
                RecurringOccurrenceStatus::PendingApproval->value,
                RecurringOccurrenceStatus::Rejected->value,
            ])
            ->whereDate('occurrence_date', '<=', $asOfDate->toDateString())
            ->where(function ($query) use ($asOfDate): void {
                $query->whereNull('last_reminded_at')
                    ->orWhereDate('last_reminded_at', '<', $asOfDate->toDateString());
            })
            ->orderBy('id')
            ->get();

        foreach ($occurrences as $occurrence) {
            DB::transaction(function () use ($occurrence, $asOfDate, &$reminded): void {
                /** @var RecurringTransactionOccurrence $locked */
                $locked = RecurringTransactionOccurrence::query()
                    ->whereKey($occurrence->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || ! $locked->status->isActionable()) {
                    return;
                }

                if (
                    $locked->last_reminded_at !== null
                    && $locked->last_reminded_at->toDateString() >= $asOfDate->toDateString()
                ) {
                    return;
                }

                $rule = RecurringTransaction::query()
                    ->with('category:id,name,type')
                    ->find($locked->recurring_transaction_id);

                if ($rule === null || ! $rule->is_active) {
                    return;
                }

                /** @var User $owner */
                $owner = User::query()->find($rule->user_id);
                if ($owner === null) {
                    return;
                }

                $owner->notify(new RecurringApprovalNotification($locked, $rule, true));

                $locked->forceFill([
                    'last_reminded_at' => now(),
                ])->save();

                $reminded++;
            });
        }

        return $reminded;
    }

    /**
     * Monthly: add one month, clamping day-of-month to the last day of the target month.
     */
    public function advanceDate(CarbonInterface $from, RecurringFrequency $frequency, int $preferredDay): Carbon
    {
        $from = Carbon::parse($from->toDateString())->startOfDay();

        return match ($frequency) {
            RecurringFrequency::Weekly => $from->copy()->addWeek(),
            RecurringFrequency::Monthly => $this->addMonthClamped($from, $preferredDay),
        };
    }

    private function addMonthClamped(Carbon $from, int $preferredDay): Carbon
    {
        $target = $from->copy()->addMonthNoOverflow()->startOfMonth();
        $day = min($preferredDay, $target->daysInMonth);

        return $target->day($day)->startOfDay();
    }

    private function assertCategoryStillValid(User $owner, RecurringTransaction $rule): void
    {
        $category = Category::query()->find($rule->category_id);

        if ($category === null || $category->type !== $rule->type) {
            throw new RecurringOccurrenceDeferredException('Recurring category is no longer valid.');
        }

        $accessible = Category::query()
            ->accessibleTo($owner)
            ->whereKey($rule->category_id)
            ->exists();

        if (! $accessible) {
            throw new RecurringOccurrenceDeferredException('Recurring category is no longer accessible.');
        }
    }
}

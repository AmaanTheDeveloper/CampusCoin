<?php

namespace App\Services\Ai;

use App\Contracts\AiAssistantProvider;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\IncomePlanNotification;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Post-commit income planning suggestion.
 * Never blocks or rolls back a valid income save.
 */
class IncomePlanAlertService
{
    public const CACHE_TTL_SECONDS = 60 * 60 * 24 * 14;

    public function __construct(
        private readonly FinancialContextBuilder $contextBuilder,
        private readonly IncomeAllocationPlanner $planner,
        private readonly AiAssistantProvider $assistant,
    ) {}

    public function handle(User $user, Transaction $transaction): void
    {
        try {
            abort_unless($transaction->user_id === $user->id, 404);

            if ($transaction->type !== TransactionType::Income) {
                return;
            }

            if ($this->alreadyNotified($user, $transaction->id)) {
                return;
            }

            $amount = MoneyMath::normalize($transaction->amount);
            $context = $this->contextBuilder->forUser($user);
            $planResult = $this->planner->plan($user, $amount, $context);

            $planSource = 'planner';
            $explanation = $this->deterministicExplanation($amount, $planResult);
            $why = $this->deterministicWhy($planResult);

            $narration = $this->optionalGeminiNarration($user, $amount, $planResult, $context);
            if ($narration !== null) {
                $explanation = $narration['answer'];
                $why = $narration['why'] ?? $why;
                $planSource = 'planner_with_ai';
            }

            $payload = [
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'plan' => $planResult['plan'],
                'total' => $planResult['total'],
                'basis' => $planResult['basis'],
                'has_history' => $planResult['has_history'],
                'explanation' => $explanation,
                'why' => $why,
                'plan_source' => $planSource,
                'generated_at' => now()->toIso8601String(),
            ];

            Cache::put($this->cacheKey($user->id, $transaction->id), $payload, self::CACHE_TTL_SECONDS);

            $friendlyAmount = number_format((float) $amount, 0);
            $summary = $this->allocationSummary($planResult['plan']);

            $user->notify(new IncomePlanNotification([
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'title' => 'Income received — suggested plan',
                'message' => sprintf(
                    'Rs %s received. Campus Coin prepared a suggested allocation based on your current data.',
                    $friendlyAmount,
                ),
                'plan_source' => $planSource,
                'allocation_summary' => $summary,
                'action_url' => '/intelligence?plan='.$transaction->id,
            ]));
        } catch (Throwable $e) {
            Log::warning('income_plan_alert_failed', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'reason' => class_basename($e),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function planFor(User $user, int $transactionId): ?array
    {
        $cached = Cache::get($this->cacheKey($user->id, $transactionId));
        if (is_array($cached)) {
            return $cached;
        }

        $transaction = $user->transactions()
            ->whereKey($transactionId)
            ->where('type', TransactionType::Income->value)
            ->first();

        if ($transaction === null) {
            return null;
        }

        $amount = MoneyMath::normalize($transaction->amount);
        $context = $this->contextBuilder->forUser($user);
        $planResult = $this->planner->plan($user, $amount, $context);

        $payload = [
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'plan' => $planResult['plan'],
            'total' => $planResult['total'],
            'basis' => $planResult['basis'],
            'has_history' => $planResult['has_history'],
            'explanation' => $this->deterministicExplanation($amount, $planResult),
            'why' => $this->deterministicWhy($planResult),
            'plan_source' => 'planner',
            'generated_at' => now()->toIso8601String(),
        ];

        Cache::put($this->cacheKey($user->id, $transactionId), $payload, self::CACHE_TTL_SECONDS);

        return $payload;
    }

    private function alreadyNotified(User $user, int $transactionId): bool
    {
        return $user->notifications()
            ->where('data->type', 'income_plan')
            ->where('data->transaction_id', $transactionId)
            ->exists();
    }

    private function cacheKey(int $userId, int $transactionId): string
    {
        return "income_plan:{$userId}:{$transactionId}";
    }

    /**
     * @param  array<string, mixed>  $planResult
     */
    private function deterministicExplanation(string $amount, array $planResult): string
    {
        $friendly = number_format((float) $amount, 0);

        if (! ($planResult['has_history'] ?? false) && ($planResult['basis'] ?? []) === []) {
            return sprintf(
                'Rs %s was recorded. You don\'t have enough spending history yet for a fully personalized allocation, so start by setting aside known commitments and a savings buffer. Suggested plan — you stay in control.',
                $friendly,
            );
        }

        $parts = [];
        foreach (array_slice($planResult['plan'] ?? [], 0, 5) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = sprintf(
                '%s Rs %s',
                (string) ($row['category'] ?? 'Category'),
                number_format((float) ($row['amount'] ?? 0), 0),
            );
        }

        $list = $parts === [] ? 'a balanced starter split' : implode(', ', $parts);

        return sprintf(
            'Rs %s added. Based on your recent data, you could consider: %s. Suggested plan — you stay in control.',
            $friendly,
            $list,
        );
    }

    /**
     * @param  array<string, mixed>  $planResult
     */
    private function deterministicWhy(array $planResult): string
    {
        $basis = $planResult['basis'] ?? [];
        if ($basis === []) {
            return 'This starter plan reserves space for savings and essentials until Campus Coin has more of your spending history.';
        }

        return 'This plan prioritizes '.implode(', ', $basis).' from your Campus Coin data. Allocation totals match the new income exactly.';
    }

    /**
     * @param  list<array{category: string, amount: string, reason: string}>  $plan
     */
    private function allocationSummary(array $plan): ?string
    {
        if ($plan === []) {
            return null;
        }

        $parts = [];
        foreach (array_slice($plan, 0, 4) as $row) {
            $parts[] = sprintf(
                '%s %s',
                $row['category'],
                number_format((float) $row['amount'], 0),
            );
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $planResult
     * @param  array<string, mixed>  $context
     * @return array{answer: string, why: string|null}|null
     */
    private function optionalGeminiNarration(User $user, string $amount, array $planResult, array $context): ?array
    {
        if (! $this->assistant->isAvailable()) {
            return null;
        }

        try {
            $result = $this->assistant->answer([
                'question' => 'Explain this verified income allocation plan briefly for a student.',
                'financial_context' => [
                    'available_balance' => $context['current_period']['available_balance'] ?? null,
                    'savings_goal' => $context['savings']['goal'] ?? null,
                    'basis' => $planResult['basis'] ?? [],
                ],
                'allocation_plan' => $planResult['plan'],
                'incoming_amount' => $amount,
            ]);

            if (! is_array($result) || empty($result['answer']) || ($result['in_scope'] ?? true) === false) {
                return null;
            }

            $answer = trim(strip_tags((string) $result['answer']));
            if ($answer === '') {
                return null;
            }

            $why = isset($result['next_action']) && is_string($result['next_action'])
                ? trim(strip_tags($result['next_action']))
                : null;

            return [
                'answer' => mb_substr($answer, 0, 800),
                'why' => $why !== null && $why !== '' ? mb_substr($why, 0, 400) : null,
            ];
        } catch (Throwable $e) {
            Log::warning('income_plan_gemini_narration_failed', [
                'user_id' => $user->id,
                'reason' => class_basename($e),
            ]);

            return null;
        }
    }
}

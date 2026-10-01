<?php

namespace App\Services\Insights;

use App\Contracts\AiInsightProvider;
use App\Models\AiMonthlyInsight;
use App\Models\Bookmark;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class MonthlyInsightService
{
    public function __construct(
        private readonly InsightDataBuilder $builder,
        private readonly AiInsightProvider $provider,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) config('ai.enabled', true) && $this->provider->isAvailable();
    }

    /**
     * Dashboard-safe payload: never calls the AI provider.
     *
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $period = $now->format('Y-m');
        $context = $this->builder->forUser($user, $now);

        $insight = AiMonthlyInsight::query()
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->first();

        $bookmarked = false;
        if ($insight) {
            $bookmarked = Bookmark::query()
                ->where('user_id', $user->id)
                ->where('bookmarkable_type', AiMonthlyInsight::class)
                ->where('bookmarkable_id', $insight->id)
                ->exists();
        }

        return [
            'period' => $period,
            'period_label' => $context['period_label'],
            'ai_available' => $this->isAvailable(),
            'insight' => $insight ? $this->serialize($insight, $bookmarked) : null,
            'factual_fallback' => $this->factualFallback($context),
            'can_generate' => true,
        ];
    }

    /**
     * Generate or regenerate insight for the current (or given) period.
     */
    public function generateForUser(User $user, ?CarbonInterface $now = null, bool $force = false): AiMonthlyInsight
    {
        $now = Carbon::parse($now?->toDateTimeString() ?? now()->toDateTimeString());
        $period = $now->format('Y-m');
        $context = $this->builder->forUser($user, $now);

        $existing = AiMonthlyInsight::query()
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->first();

        if ($existing && ! $force) {
            return $existing;
        }

        $rateKey = 'ai-insight:'.$user->id;
        $limit = (int) config('ai.insight_rate_limit_per_minute', 5);

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            if ($existing) {
                return $existing;
            }

            return $this->persistFallback($user, $period, $context, 'rate_limited');
        }

        RateLimiter::hit($rateKey, 60);

        if ($context['insufficient_data'] || ! $this->isAvailable()) {
            return $this->persistFallback($user, $period, $context, $context['insufficient_data'] ? 'insufficient' : 'unavailable');
        }

        try {
            $raw = $this->provider->generateMonthlyInsight($context);
            $validated = $this->validateInsight($raw, $context);

            if ($validated === null) {
                return $this->persistFallback($user, $period, $context, 'invalid');
            }

            return AiMonthlyInsight::query()->updateOrCreate(
                ['user_id' => $user->id, 'period' => $period],
                [
                    'headline' => $validated['headline'],
                    'summary' => $validated['summary'],
                    'insight_payload' => [
                        'trends' => $validated['trends'],
                        'actions' => $validated['actions'],
                        'facts' => $context['facts'],
                    ],
                    'generated_source' => 'ai',
                    'status' => 'ready',
                    'model' => $this->provider->model(),
                    'generated_at' => now(),
                ],
            );
        } catch (Throwable $e) {
            Log::warning('ai.insight.generation_failed', ['error' => class_basename($e)]);

            return $this->persistFallback($user, $period, $context, 'failed');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForUser(User $user, int $limit = 12): array
    {
        $ids = AiMonthlyInsight::query()
            ->where('user_id', $user->id)
            ->orderByDesc('period')
            ->limit($limit)
            ->pluck('id');

        $bookmarkedIds = Bookmark::query()
            ->where('user_id', $user->id)
            ->where('bookmarkable_type', AiMonthlyInsight::class)
            ->whereIn('bookmarkable_id', $ids)
            ->pluck('bookmarkable_id')
            ->all();

        return AiMonthlyInsight::query()
            ->where('user_id', $user->id)
            ->orderByDesc('period')
            ->limit($limit)
            ->get()
            ->map(fn (AiMonthlyInsight $insight) => $this->serialize($insight, in_array($insight->id, $bookmarkedIds, true)))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{headline: string, summary: string, trends: list<array<string, string>>, actions: list<string>}
     */
    public function factualFallback(array $context): array
    {
        if (! empty($context['insufficient_data'])) {
            return [
                'headline' => 'Not enough data to identify a meaningful trend this month.',
                'summary' => 'Add a few income and expense transactions to unlock personalized monthly insights.',
                'trends' => [],
                'actions' => ['Add transactions to build your monthly picture.'],
            ];
        }

        $top = $context['facts']['top_category'] ?? null;
        $topAmount = $context['facts']['top_category_amount'] ?? null;
        $headline = $top
            ? "Your highest spending category this month is {$top}."
            : 'Here is your factual monthly spending snapshot.';

        $summary = sprintf(
            'Income Rs. %s · Expenses Rs. %s · Balance Rs. %s.',
            $context['income'],
            $context['expense'],
            $context['balance'],
        );

        if ($top && $topAmount) {
            $summary .= " {$top} totalled Rs. {$topAmount}.";
        }

        $trends = [];
        foreach ($context['budget_status'] as $budget) {
            if (($budget['status'] ?? '') === 'exceeded') {
                $trends[] = [
                    'label' => "{$budget['category']} budget exceeded",
                    'direction' => 'up',
                    'significance' => 'high',
                ];
            } elseif (($budget['status'] ?? '') === 'near_limit') {
                $trends[] = [
                    'label' => "{$budget['category']} budget near limit",
                    'direction' => 'up',
                    'significance' => 'medium',
                ];
            }
        }

        $actions = [];
        if ($top) {
            $actions[] = "Review spending in {$top} for this month.";
        }

        return [
            'headline' => $headline,
            'summary' => $summary,
            'trends' => array_slice($trends, 0, 4),
            'actions' => array_slice($actions ?: ['Keep tracking transactions to refine insights.'], 0, 4),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @param  array<string, mixed>  $context
     * @return array{headline: string, summary: string, trends: list<array{label: string, direction: string, significance: string}>, actions: list<string>}|null
     */
    public function validateInsight(?array $raw, array $context): ?array
    {
        if ($raw === null) {
            return null;
        }

        $headline = trim(strip_tags((string) ($raw['headline'] ?? '')));
        $summary = trim(strip_tags((string) ($raw['summary'] ?? '')));

        if ($headline === '' || $summary === '') {
            return null;
        }

        $headline = mb_substr($headline, 0, 120);
        $summary = mb_substr($summary, 0, 600);

        $allowedNames = collect($context['top_categories'] ?? [])
            ->pluck('name')
            ->merge(collect($context['budget_status'] ?? [])->pluck('category'))
            ->map(fn ($n) => mb_strtolower((string) $n))
            ->filter()
            ->unique()
            ->all();

        $trends = [];
        foreach (array_slice((array) ($raw['trends'] ?? []), 0, 4) as $trend) {
            if (! is_array($trend)) {
                continue;
            }
            $label = trim(strip_tags((string) ($trend['label'] ?? '')));
            $direction = strtolower(trim((string) ($trend['direction'] ?? '')));
            $significance = strtolower(trim((string) ($trend['significance'] ?? '')));

            if ($label === '' || ! in_array($direction, ['up', 'down', 'stable'], true)) {
                continue;
            }
            if (! in_array($significance, ['high', 'medium', 'low'], true)) {
                $significance = 'medium';
            }

            // Soft check: if label invents a clearly unknown category word, still allow generic labels
            // but reject labels that claim unsupported numeric amounts like "Rs. 999999".
            if (preg_match('/rs\.?\s*[\d,]+/i', $label)) {
                // Only allow if the amount string appears in factual context values.
                if (! $this->labelAmountsAreSupported($label, $context)) {
                    continue;
                }
            }

            $trends[] = [
                'label' => mb_substr($label, 0, 160),
                'direction' => $direction,
                'significance' => $significance,
            ];
        }

        $actions = [];
        foreach (array_slice((array) ($raw['actions'] ?? []), 0, 4) as $action) {
            $text = trim(strip_tags((string) $action));
            if ($text !== '') {
                $actions[] = mb_substr($text, 0, 200);
            }
        }

        unset($allowedNames);

        return compact('headline', 'summary', 'trends', 'actions');
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(AiMonthlyInsight $insight, bool $bookmarked = false): array
    {
        $payload = $insight->insight_payload ?? [];

        return [
            'id' => $insight->id,
            'period' => $insight->period,
            'period_label' => Carbon::createFromFormat('Y-m', $insight->period)->format('F Y'),
            'headline' => $insight->headline,
            'summary' => $insight->summary,
            'trends' => $payload['trends'] ?? [],
            'actions' => $payload['actions'] ?? [],
            'facts' => $payload['facts'] ?? null,
            'generated_source' => $insight->generated_source,
            'status' => $insight->status,
            'generated_at' => $insight->generated_at?->toIso8601String(),
            'is_bookmarked' => $bookmarked,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function persistFallback(User $user, string $period, array $context, string $reason): AiMonthlyInsight
    {
        $fallback = $this->factualFallback($context);

        return AiMonthlyInsight::query()->updateOrCreate(
            ['user_id' => $user->id, 'period' => $period],
            [
                'headline' => $fallback['headline'],
                'summary' => $fallback['summary'],
                'insight_payload' => [
                    'trends' => $fallback['trends'],
                    'actions' => $fallback['actions'],
                    'facts' => $context['facts'],
                    'fallback_reason' => $reason,
                ],
                'generated_source' => 'fallback',
                'status' => 'ready',
                'model' => null,
                'generated_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function labelAmountsAreSupported(string $label, array $context): bool
    {
        preg_match_all('/rs\.?\s*([\d,]+(?:\.\d+)?)/i', $label, $matches);
        $amounts = $matches[1] ?? [];
        if ($amounts === []) {
            return true;
        }

        $haystack = json_encode($context) ?: '';

        foreach ($amounts as $amount) {
            $normalized = str_replace(',', '', $amount);
            if (! str_contains($haystack, $normalized)) {
                return false;
            }
        }

        return true;
    }
}

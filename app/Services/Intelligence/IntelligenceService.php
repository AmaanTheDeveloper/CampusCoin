<?php

namespace App\Services\Intelligence;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntelligenceService
{
    public function __construct(
        private readonly ForecastService $forecast,
        private readonly RecentActivityService $activity,
        private readonly TransactionSignalService $signals,
    ) {}

    /**
     * Full intelligence page payload.
     *
     * @return array<string, mixed>
     */
    public function buildFor(User $user, ?CarbonInterface $now = null): array
    {
        return [
            'forecast' => $this->safeForecast($user, $now),
            'signals' => $this->safeSignals($user),
            'activity' => $this->safeActivity($user),
        ];
    }

    /**
     * Compact dashboard strip.
     *
     * @return array<string, mixed>
     */
    public function dashboardPayload(User $user, ?CarbonInterface $now = null): array
    {
        $forecast = $this->safeForecast($user, $now);
        $signals = $this->safeSignals($user, 5);
        $activity = $this->safeActivity($user);

        return [
            'forecast' => $forecast,
            'large_signals' => array_values(array_filter(
                $signals,
                fn (array $s) => $s['signal_type'] === 'large_transaction'
            )),
            'duplicate_signals' => array_values(array_filter(
                $signals,
                fn (array $s) => $s['signal_type'] === 'duplicate_transaction'
            )),
            'recent_viewed' => array_slice($activity['viewed'] ?? [], 0, 5),
            'recent_edited' => array_slice($activity['edited'] ?? [], 0, 5),
            'open_signal_count' => count($signals),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeForecast(User $user, ?CarbonInterface $now = null): array
    {
        try {
            return $this->forecast->forUser($user, $now);
        } catch (Throwable $e) {
            Log::warning('forecast_calculation_failed', [
                'user_id' => $user->id,
                'reason' => $e->getMessage(),
            ]);

            return [
                'target_period' => null,
                'target_label' => null,
                'confidence' => 'insufficient',
                'confidence_label' => 'Unavailable',
                'method' => null,
                'months_used' => 0,
                'estimated_income' => null,
                'estimated_expense' => null,
                'estimated_balance' => null,
                'categories' => [],
                'disclaimer' => 'Forecasts are estimates based on your historical Campus Coin data, not guaranteed outcomes.',
                'empty_message' => 'We couldn\'t generate this intelligence view right now. Your transactions are safe and unchanged.',
                'error' => true,
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safeSignals(User $user, int $limit = 20): array
    {
        try {
            return $this->signals->openSignalsForUser($user, $limit);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{viewed: list<array<string, mixed>>, edited: list<array<string, mixed>>}
     */
    private function safeActivity(User $user): array
    {
        try {
            return $this->activity->forUser($user);
        } catch (Throwable) {
            return ['viewed' => [], 'edited' => []];
        }
    }
}

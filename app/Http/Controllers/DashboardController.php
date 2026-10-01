<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use App\Services\FinancialHealthService;
use App\Services\Insights\MonthlyInsightService;
use App\Services\Intelligence\IntelligenceService;
use App\Services\MonthlyForecastService;
use App\Services\SavingsGoalService;
use App\Services\Tips\SavingTipsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardAnalyticsService $analytics,
        MonthlyInsightService $insights,
        SavingTipsService $tips,
        IntelligenceService $intelligence,
        FinancialHealthService $financialHealth,
        MonthlyForecastService $monthForecast,
        SavingsGoalService $savingsGoals,
    ): Response {
        try {
            $user = $request->user();
            $dashboard = $analytics->forUser($user);
            // Phase 9: load existing insight + deterministic tips without blocking on AI generation.
            $dashboard['monthly_insight'] = $insights->dashboardPayload($user);
            $dashboard['saving_tips'] = $tips->syncAndListForUser($user, null, 5, $dashboard);
            // Phase 10: deterministic forecast/signals/activity (no AI).
            $dashboard['intelligence'] = $intelligence->dashboardPayload($user);
            // Deterministic Money Pulse from the same dashboard aggregates (no AI).
            $dashboard['money_pulse'] = $financialHealth->dashboardPayload($user, $dashboard);
            // Current-month pace forecast (distinct from next-month intelligence forecast).
            $dashboard['month_forecast'] = $monthForecast->dashboardPayload($user, $dashboard);
            // Long-term savings goal tracker (distinct from profile.savings_goal / Money Pulse).
            $dashboard['savings_goal_tracker'] = $savingsGoals->dashboardPayload($user, $dashboard);
        } catch (Throwable $e) {
            Log::error('dashboard.load_failed', [
                'user_id' => $request->user()?->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return Inertia::render('dashboard', [
                'dashboard' => null,
                'error' => 'Your dashboard data couldn\'t be loaded. Please try again.',
            ]);
        }

        return Inertia::render('dashboard', [
            'dashboard' => $dashboard,
            'error' => null,
        ]);
    }
}

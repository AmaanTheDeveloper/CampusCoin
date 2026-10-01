<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use App\Services\MonthlyForecastService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MonthForecastController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardAnalyticsService $analytics,
        MonthlyForecastService $forecast,
    ): Response {
        try {
            $user = $request->user();
            $dashboard = $analytics->forUser($user);
            $payload = $forecast->forUser($user, null, $dashboard);
        } catch (Throwable) {
            return Inertia::render('month-forecast/index', [
                'forecast' => null,
                'error' => 'Month forecast couldn\'t be loaded right now. Your transactions are unchanged.',
            ]);
        }

        return Inertia::render('month-forecast/index', [
            'forecast' => $payload,
            'error' => null,
        ]);
    }
}

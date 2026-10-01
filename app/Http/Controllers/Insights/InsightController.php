<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Models\AiMonthlyInsight;
use App\Services\Insights\MonthlyInsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightController extends Controller
{
    public function index(Request $request, MonthlyInsightService $insights): Response
    {
        $this->authorize('viewAny', AiMonthlyInsight::class);

        return Inertia::render('insights/index', [
            'insights' => $insights->historyForUser($request->user()),
            'current' => $insights->dashboardPayload($request->user()),
            'aiAvailable' => $insights->isAvailable(),
        ]);
    }

    public function regenerate(Request $request, MonthlyInsightService $insights): RedirectResponse
    {
        $this->authorize('create', AiMonthlyInsight::class);

        $insight = $insights->generateForUser($request->user(), null, true);

        return back()->with('success', $insight->generated_source === 'ai'
            ? 'Monthly insight updated.'
            : 'Showing a factual monthly snapshot (AI interpretation unavailable).');
    }
}

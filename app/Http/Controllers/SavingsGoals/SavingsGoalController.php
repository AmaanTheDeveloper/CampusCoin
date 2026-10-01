<?php

namespace App\Http\Controllers\SavingsGoals;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavingsGoals\StoreSavingsGoalContributionRequest;
use App\Http\Requests\SavingsGoals\StoreSavingsGoalRequest;
use App\Http\Requests\SavingsGoals\UpdateSavingsGoalRequest;
use App\Models\SavingsGoal;
use App\Services\DashboardAnalyticsService;
use App\Services\SavingsGoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SavingsGoalController extends Controller
{
    public function index(
        Request $request,
        SavingsGoalService $goals,
        DashboardAnalyticsService $analytics,
    ): Response {
        $this->authorize('viewAny', SavingsGoal::class);

        try {
            $dashboard = $analytics->forUser($request->user());
            $payload = $goals->dashboardPayload($request->user(), $dashboard);
        } catch (Throwable) {
            return Inertia::render('savings-goal/index', [
                'panel' => null,
                'error' => 'Savings goal couldn\'t be loaded right now.',
            ]);
        }

        return Inertia::render('savings-goal/index', [
            'panel' => $payload,
            'error' => null,
        ]);
    }

    public function store(StoreSavingsGoalRequest $request, SavingsGoalService $goals): RedirectResponse
    {
        $this->authorize('create', SavingsGoal::class);

        $goals->createGoal(
            $request->user(),
            $request->string('name')->toString(),
            (string) $request->input('target_amount'),
            (string) ($request->input('starting_amount') ?: '0'),
        );

        return redirect()
            ->route('savings-goal.index')
            ->with('success', 'Savings goal created.');
    }

    public function update(
        UpdateSavingsGoalRequest $request,
        SavingsGoal $savingsGoal,
        SavingsGoalService $goals,
    ): RedirectResponse {
        $this->authorize('update', $savingsGoal);

        $goals->updateGoal(
            $request->user(),
            $savingsGoal,
            $request->string('name')->toString(),
            (string) $request->input('target_amount'),
        );

        return redirect()
            ->route('savings-goal.index')
            ->with('success', 'Savings goal updated.');
    }

    public function contribute(
        StoreSavingsGoalContributionRequest $request,
        SavingsGoal $savingsGoal,
        SavingsGoalService $goals,
    ): RedirectResponse {
        $this->authorize('update', $savingsGoal);

        $goals->addContribution(
            $request->user(),
            $savingsGoal,
            (string) $request->input('amount'),
            $request->input('note'),
        );

        return redirect()
            ->route('savings-goal.index')
            ->with('success', 'Contribution added.');
    }
}

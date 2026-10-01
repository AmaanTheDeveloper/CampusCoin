<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Intelligence\AssistantAskRequest;
use App\Models\TransactionSignal;
use App\Services\Ai\FinancialAssistantService;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\Intelligence\IntelligenceService;
use App\Services\Intelligence\TransactionSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class IntelligenceController extends Controller
{
    public function index(
        Request $request,
        IntelligenceService $intelligence,
        FinancialAssistantService $assistant,
        IncomePlanAlertService $incomePlans,
    ): Response {
        $assistantMeta = [
            'available' => $assistant->isAvailable(),
            'suggested_prompts' => $assistant->suggestedPrompts($request->user()),
        ];

        $suggestedPlan = null;
        $planId = $request->query('plan');
        if (is_numeric($planId)) {
            $suggestedPlan = $incomePlans->planFor($request->user(), (int) $planId);
        }

        try {
            $payload = $intelligence->buildFor($request->user());
        } catch (Throwable) {
            return Inertia::render('intelligence/index', [
                'intelligence' => null,
                'assistant' => $assistantMeta,
                'suggested_plan' => $suggestedPlan,
                'error' => 'We couldn\'t generate this intelligence view right now. Your transactions are safe and unchanged.',
            ]);
        }

        return Inertia::render('intelligence/index', [
            'intelligence' => $payload,
            'assistant' => $assistantMeta,
            'suggested_plan' => $suggestedPlan,
            'error' => null,
        ]);
    }

    public function ask(AssistantAskRequest $request, FinancialAssistantService $assistant): JsonResponse
    {
        $result = $assistant->ask(
            $request->user(),
            (string) $request->validated('message'),
            $request->history(),
        );

        return response()->json([
            'ok' => $result['error'] === null,
            'response' => $result,
        ]);
    }

    public function dismiss(
        Request $request,
        TransactionSignal $signal,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $this->authorize('update', $signal);
        $signals->dismiss($request->user(), $signal);

        return back()->with('success', 'Signal dismissed.');
    }

    public function review(
        Request $request,
        TransactionSignal $signal,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $this->authorize('update', $signal);
        $signals->markReviewed($request->user(), $signal);

        return back()->with('success', 'Marked as reviewed.');
    }
}

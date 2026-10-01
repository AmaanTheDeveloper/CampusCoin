<?php

namespace App\Http\Controllers\SpendingAlerts;

use App\Http\Controllers\Controller;
use App\Services\Intelligence\TransactionSignalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SpendingAlertController extends Controller
{
    /**
     * Unusual spending alerts for the authenticated user only.
     */
    public function index(Request $request, TransactionSignalService $signals): Response
    {
        try {
            $alerts = $signals->spendingAlertsForUser($request->user());
        } catch (Throwable) {
            return Inertia::render('spending-alerts/index', [
                'alerts' => null,
                'error' => 'Spending alerts couldn\'t be loaded right now. Your transactions are unchanged.',
            ]);
        }

        return Inertia::render('spending-alerts/index', [
            'alerts' => $alerts,
            'error' => null,
        ]);
    }
}

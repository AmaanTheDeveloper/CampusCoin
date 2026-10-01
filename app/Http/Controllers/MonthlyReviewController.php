<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MonthlyReviewController extends Controller
{
    /**
     * Standalone Monthly Money Review for the authenticated user.
     */
    public function __invoke(Request $request, MonthlyReviewService $reviews): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $review = $reviews->forUser($request->user(), $validated['month'] ?? null);
        } catch (Throwable) {
            return Inertia::render('monthly-review/index', [
                'review' => null,
                'error' => 'Monthly review couldn\'t be loaded right now. Your transactions are unchanged.',
            ]);
        }

        return Inertia::render('monthly-review/index', [
            'review' => $review,
            'error' => null,
        ]);
    }
}

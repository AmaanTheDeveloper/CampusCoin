<?php

namespace App\Http\Controllers\DuplicateReview;

use App\Http\Controllers\Controller;
use App\Models\TransactionSignal;
use App\Services\Intelligence\TransactionSignalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DuplicateReviewController extends Controller
{
    public function index(Request $request, TransactionSignalService $signals): Response
    {
        try {
            $review = $signals->duplicateReviewForUser($request->user());
        } catch (Throwable) {
            return Inertia::render('duplicate-review/index', [
                'review' => null,
                'error' => 'Duplicate review couldn\'t be loaded right now. Your transactions are unchanged.',
            ]);
        }

        return Inertia::render('duplicate-review/index', [
            'review' => $review,
            'error' => null,
        ]);
    }

    public function keepBoth(
        Request $request,
        TransactionSignal $signal,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $this->authorize('update', $signal);
        $signals->keepBoth($request->user(), $signal);

        return redirect()
            ->route('duplicate-review.index')
            ->with('success', 'Kept both transactions. This possible duplicate is resolved.');
    }

    public function remove(
        Request $request,
        TransactionSignal $signal,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $this->authorize('update', $signal);

        $validated = $request->validate([
            'remove_transaction_id' => ['required', 'integer'],
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'Confirm removal before archiving a transaction.',
        ]);

        try {
            $signals->removeDuplicate(
                $request->user(),
                $signal,
                (int) $validated['remove_transaction_id'],
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'remove_transaction_id' => 'Unable to remove that transaction. Nothing was changed.',
            ]);
        }

        return redirect()
            ->route('duplicate-review.index')
            ->with('success', 'Selected transaction archived. Possible duplicate resolved.')
            ->with('archived_transaction_id', (int) $validated['remove_transaction_id']);
    }
}

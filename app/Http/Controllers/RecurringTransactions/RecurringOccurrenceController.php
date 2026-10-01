<?php

namespace App\Http\Controllers\RecurringTransactions;

use App\Http\Controllers\Controller;
use App\Models\RecurringTransactionOccurrence;
use App\Services\Notifications\StudentNotificationService;
use App\Services\RecurringOccurrenceApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RecurringOccurrenceController extends Controller
{
    public function approve(
        Request $request,
        RecurringTransactionOccurrence $occurrence,
        RecurringOccurrenceApprovalService $approvals,
        StudentNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeOccurrence($request, $occurrence);

        try {
            $result = $approvals->approve($request->user(), $occurrence);
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException) {
            abort(403);
        }

        $this->markRelatedNotificationsRead($request, $occurrence, $notifications);

        $message = $result['created']
            ? 'Recurring payment approved and posted.'
            : 'Recurring payment was already approved.';

        return back()->with('success', $message);
    }

    public function reject(
        Request $request,
        RecurringTransactionOccurrence $occurrence,
        RecurringOccurrenceApprovalService $approvals,
        StudentNotificationService $notifications,
    ): RedirectResponse {
        $this->authorizeOccurrence($request, $occurrence);

        try {
            $approvals->reject($request->user(), $occurrence);
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException) {
            abort(403);
        }

        $this->markRelatedNotificationsRead($request, $occurrence, $notifications);

        return back()->with('success', 'Recurring payment rejected. You will be reminded again tomorrow.');
    }

    private function authorizeOccurrence(Request $request, RecurringTransactionOccurrence $occurrence): void
    {
        $occurrence->loadMissing('recurringTransaction');

        $rule = $occurrence->recurringTransaction;
        if ($rule === null || (int) $rule->user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }

    private function markRelatedNotificationsRead(
        Request $request,
        RecurringTransactionOccurrence $occurrence,
        StudentNotificationService $notifications,
    ): void {
        $notifications->queryFor($request->user())
            ->whereNull('read_at')
            ->where(function ($query) use ($occurrence): void {
                $query->where('data->occurrence_id', $occurrence->id)
                    ->orWhere('data->occurrence_id', (string) $occurrence->id);
            })
            ->update(['read_at' => now()]);
    }
}

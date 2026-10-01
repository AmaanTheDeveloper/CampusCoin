<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\StudentNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, StudentNotificationService $notifications): Response
    {
        $items = $notifications->queryFor($request->user())
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification));

        return Inertia::render('notifications/index', [
            'notifications' => $items,
        ]);
    }

    public function markAsRead(Request $request, string $notification, StudentNotificationService $notifications): RedirectResponse
    {
        $record = $notifications->queryFor($request->user())
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        $budgetId = $record->data['budget_id'] ?? null;
        $actionUrl = $record->data['action_url'] ?? null;

        if (is_numeric($budgetId)) {
            return redirect()
                ->route('budgets.index')
                ->with('success', 'Notification marked as read.');
        }

        if (is_string($actionUrl) && str_starts_with($actionUrl, '/')) {
            return redirect()
                ->to($actionUrl)
                ->with('success', 'Notification marked as read.');
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request, StudentNotificationService $notifications): RedirectResponse
    {
        $notifications->queryFor($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $type = $data['type'] ?? 'notification';
        $isRecurring = in_array($type, ['recurring_approval', 'recurring_approval_reminder'], true);

        return [
            'id' => $notification->id,
            'type' => $type,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? 'Budget update',
            'category_name' => $data['category_name'] ?? null,
            'budget_id' => $data['budget_id'] ?? null,
            'occurrence_id' => $isRecurring ? ($data['occurrence_id'] ?? null) : null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'month_label' => $data['month_label'] ?? null,
            'percentage' => $data['percentage'] ?? null,
            'play_sound' => (bool) ($data['play_sound'] ?? false),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'is_unread' => $notification->read_at === null,
        ];
    }
}

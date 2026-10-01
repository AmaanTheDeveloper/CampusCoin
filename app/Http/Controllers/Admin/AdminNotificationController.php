<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNotificationController extends Controller
{
    public function index(Request $request, AdminNotificationService $notifications): Response
    {
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'unread', 'important'], true)) {
            $filter = 'all';
        }

        $query = $notifications->adminNotificationsQuery($request->user());

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        if ($filter === 'important') {
            $query->whereIn('data->severity', ['warning', 'important']);
        }

        $records = $query->limit(100)->get();

        return Inertia::render('admin/notifications/index', [
            'notifications' => $records->map(fn ($notification) => $notifications->serialize($notification))->values(),
            'grouped' => $notifications->groupByDay($records),
            'filters' => [
                'filter' => $filter,
            ],
            'unread_count' => $notifications->unreadAdminCount($request->user()),
        ]);
    }

    public function markAsRead(Request $request, string $notification, AdminNotificationService $notifications): RedirectResponse
    {
        $record = $notifications->adminNotificationsQuery($request->user())
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAsUnread(Request $request, string $notification, AdminNotificationService $notifications): RedirectResponse
    {
        $record = $notifications->adminNotificationsQuery($request->user())
            ->whereKey($notification)
            ->firstOrFail();

        $record->forceFill(['read_at' => null])->save();

        return back()->with('success', 'Notification marked as unread.');
    }

    public function markAllAsRead(Request $request, AdminNotificationService $notifications): RedirectResponse
    {
        $notifications->adminNotificationsQuery($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All admin notifications marked as read.');
    }

    public function destroy(Request $request, string $notification, AdminNotificationService $notifications): RedirectResponse
    {
        $record = $notifications->adminNotificationsQuery($request->user())
            ->whereKey($notification)
            ->firstOrFail();

        $record->delete();

        return back()->with('success', 'Notification removed.');
    }
}

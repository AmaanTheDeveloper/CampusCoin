<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminNotificationService;
use App\Services\Intelligence\DuplicateDetectionService;
use App\Services\Notifications\StudentNotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Financial baseline fields are NOT shared globally — only via profile page props.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'email_verified_at' => $user->email_verified_at,
                    'academic_year' => $user->academic_year?->value,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'payment_result' => fn () => $request->session()->get('payment_result'),
                'contact_success' => fn () => $request->session()->get('contact_success'),
                'archived_transaction_id' => fn () => $request->session()->get('archived_transaction_id'),
                'spending_alert' => fn () => $request->session()->get('spending_alert'),
                'import_result' => fn () => $request->session()->get('import_result'),
                'budget_overrun' => fn () => $request->session()->get('budget_overrun'),
            ],
            'unread_notifications_count' => function () use ($user) {
                if (! $user) {
                    return 0;
                }

                // Student badge excludes Admin-scoped operational notifications.
                return app(StudentNotificationService::class)->unreadCount($user);
            },
            'recurring_notification_sound_id' => function () use ($user) {
                if (! $user) {
                    return null;
                }

                return app(StudentNotificationService::class)->latestUnreadRecurringSoundId($user);
            },
            'admin_unread_notifications_count' => function () use ($user) {
                if (! $user || ! $user->isAdmin()) {
                    return 0;
                }

                return app(AdminNotificationService::class)->unreadAdminCount($user);
            },
            'admin_notifications_preview' => function () use ($user) {
                if (! $user || ! $user->isAdmin()) {
                    return [];
                }

                return app(AdminNotificationService::class)->previewFor($user, 8);
            },
            'pending_duplicate_count' => fn () => $user
                ? app(DuplicateDetectionService::class)->openCountForUser($user)
                : 0,
        ];
    }
}

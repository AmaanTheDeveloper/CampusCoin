<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\AdminOperationalNotification;
use App\Services\Notifications\StudentNotificationService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class AdminNotificationService
{
    public const AUDIENCE = StudentNotificationService::ADMIN_AUDIENCE;

    public function __construct(private StudentNotificationService $studentNotifications) {}

    /**
     * @param  'info'|'success'|'warning'|'important'  $severity
     */
    public function notifyAdmins(
        string $eventType,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): void {
        $admins = User::query()
            ->where('role', Role::Admin)
            ->whereNull('disabled_at')
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            new AdminOperationalNotification(
                eventType: $eventType,
                title: $title,
                message: $message,
                severity: $severity,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
            ),
        );
    }

    public function adminNotificationsQuery(User $admin): MorphMany
    {
        return $admin->notifications()
            ->where('data->audience', self::AUDIENCE)
            ->latest();
    }

    public function studentFacingNotificationsQuery(User $user): MorphMany
    {
        return $this->studentNotifications->queryFor($user);
    }

    public function unreadAdminCount(User $admin): int
    {
        return $admin->unreadNotifications()
            ->where('data->audience', self::AUDIENCE)
            ->count();
    }

    public function unreadStudentFacingCount(User $user): int
    {
        return $this->studentNotifications->unreadCount($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewFor(User $admin, int $limit = 8): array
    {
        return $this->adminNotificationsQuery($admin)
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'admin_event',
            'title' => $data['title'] ?? 'Admin update',
            'message' => $data['message'] ?? '',
            'severity' => $data['severity'] ?? 'info',
            'action_url' => $data['action_url'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'is_unread' => $notification->read_at === null,
        ];
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupByDay(Collection $notifications): array
    {
        $groups = [
            'Today' => [],
            'Yesterday' => [],
            'Earlier' => [],
        ];

        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        foreach ($notifications as $notification) {
            $created = $notification->created_at?->copy()->startOfDay();
            $item = $this->serialize($notification);

            if ($created && $created->equalTo($today)) {
                $groups['Today'][] = $item;
            } elseif ($created && $created->equalTo($yesterday)) {
                $groups['Yesterday'][] = $item;
            } else {
                $groups['Earlier'][] = $item;
            }
        }

        return array_filter($groups, fn (array $items) => $items !== []);
    }
}

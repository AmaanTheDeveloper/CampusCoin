<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Auth\Events\Registered;

class NotifyAdminsOfNewStudent
{
    public function __construct(private AdminNotificationService $notifications) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isAdmin()) {
            return;
        }

        $this->notifications->notifyAdmins(
            eventType: 'student.registered',
            title: 'New student registered',
            message: "{$user->name} joined Campus Coin.",
            severity: 'info',
            actionUrl: '/admin/users/'.$user->id,
            actionLabel: 'View student',
        );
    }
}

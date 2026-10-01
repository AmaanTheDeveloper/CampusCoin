<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Student-facing notification queries (excludes admin-audience operational alerts).
 *
 * Kept outside Admin\* so student controllers/middleware do not depend on admin services.
 */
class StudentNotificationService
{
    public const ADMIN_AUDIENCE = 'admin';

    public function queryFor(User $user): MorphMany
    {
        return $user->notifications()
            ->where(function ($query) {
                $query->whereNull('data->audience')
                    ->orWhere('data->audience', '!=', self::ADMIN_AUDIENCE);
            })
            ->latest();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()
            ->where(function ($query) {
                $query->whereNull('data->audience')
                    ->orWhere('data->audience', '!=', self::ADMIN_AUDIENCE);
            })
            ->count();
    }

    /**
     * Newest unread recurring approval/reminder notification id for one-shot sound.
     */
    public function latestUnreadRecurringSoundId(User $user): ?string
    {
        $notification = $this->queryFor($user)
            ->whereNull('read_at')
            ->where(function ($query) {
                $query->where('data->type', 'recurring_approval')
                    ->orWhere('data->type', 'recurring_approval_reminder');
            })
            ->where('data->play_sound', true)
            ->first();

        return $notification?->id;
    }
}

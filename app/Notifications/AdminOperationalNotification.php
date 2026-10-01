<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Admin Control Center operational notification.
 * Stored in the shared notifications table with audience=admin so Student inboxes stay untouched.
 */
class AdminOperationalNotification extends Notification
{
    use Queueable;

    /**
     * @param  'info'|'success'|'warning'|'important'  $severity
     */
    public function __construct(
        public string $eventType,
        public string $title,
        public string $message,
        public string $severity = 'info',
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'audience' => 'admin',
            'type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
        ];
    }
}

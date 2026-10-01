<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LoanLifecycleNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

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
            'type' => (string) ($this->payload['type'] ?? 'loan_event'),
            'title' => (string) ($this->payload['title'] ?? 'Loan update'),
            'message' => (string) ($this->payload['message'] ?? ''),
            'action_url' => $this->payload['action_url'] ?? null,
            'reference' => $this->payload['reference'] ?? null,
            'loan_id' => $this->payload['loan_id'] ?? null,
            'application_id' => $this->payload['application_id'] ?? null,
        ];
    }
}

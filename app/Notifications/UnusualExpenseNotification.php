<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UnusualExpenseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{
     *     transaction_id: int,
     *     signal_id: int|null,
     *     category_name: string,
     *     amount: string,
     *     title: string,
     *     message: string,
     *     what_happened?: string,
     *     why_it_matters?: string,
     *     suggested_action?: string,
     *     action_url: string
     * }  $payload
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
            'type' => 'unusual_expense',
            'title' => $this->payload['title'],
            'message' => $this->payload['message'],
            'what_happened' => $this->payload['what_happened'] ?? null,
            'why_it_matters' => $this->payload['why_it_matters'] ?? null,
            'suggested_action' => $this->payload['suggested_action'] ?? null,
            'transaction_id' => $this->payload['transaction_id'],
            'signal_id' => $this->payload['signal_id'],
            'category_name' => $this->payload['category_name'],
            'amount' => $this->payload['amount'],
            'action_url' => $this->payload['action_url'],
            'action_label' => 'Review alert',
            'budget_id' => null,
        ];
    }
}

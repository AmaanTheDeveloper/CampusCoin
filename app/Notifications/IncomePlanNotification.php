<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncomePlanNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{
     *     transaction_id: int,
     *     amount: string,
     *     title: string,
     *     message: string,
     *     plan_source: string,
     *     allocation_summary: string|null,
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
            'type' => 'income_plan',
            'title' => $this->payload['title'],
            'message' => $this->payload['message'],
            'transaction_id' => $this->payload['transaction_id'],
            'amount' => $this->payload['amount'],
            'plan_source' => $this->payload['plan_source'],
            'allocation_summary' => $this->payload['allocation_summary'],
            'action_url' => $this->payload['action_url'],
            'action_label' => 'View plan',
            'category_name' => null,
            'budget_id' => null,
        ];
    }
}

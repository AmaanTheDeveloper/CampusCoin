<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategoryPaceNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{
     *     transaction_id: int,
     *     category_id: int|null,
     *     category_name: string,
     *     month_key: string,
     *     title: string,
     *     message: string,
     *     spent_mtd: string,
     *     typical_pace: string,
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
            'type' => 'category_pace',
            'title' => $this->payload['title'],
            'message' => $this->payload['message'],
            'what_happened' => $this->payload['what_happened'] ?? null,
            'why_it_matters' => $this->payload['why_it_matters'] ?? null,
            'suggested_action' => $this->payload['suggested_action'] ?? null,
            'transaction_id' => $this->payload['transaction_id'],
            'category_id' => $this->payload['category_id'],
            'category_name' => $this->payload['category_name'],
            'month_key' => $this->payload['month_key'],
            'spent_mtd' => $this->payload['spent_mtd'],
            'typical_pace' => $this->payload['typical_pace'],
            'action_url' => $this->payload['action_url'],
            'action_label' => 'View spending',
            'budget_id' => null,
        ];
    }
}

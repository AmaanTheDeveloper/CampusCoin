<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BudgetNearLimitNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $budget
     */
    public function __construct(public array $budget) {}

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
        $percentage = (float) ($this->budget['percentage'] ?? 0);

        return [
            'type' => 'budget_near_limit',
            'budget_id' => $this->budget['id'],
            'category_id' => $this->budget['category']['id'],
            'category_name' => $this->budget['category']['name'],
            'month' => $this->budget['month'],
            'month_label' => $this->budget['month_label'],
            'spent' => $this->budget['spent'],
            'limit' => $this->budget['limit'],
            'percentage' => $percentage,
            'message' => sprintf(
                'Your %s budget is at %s%% for %s.',
                $this->budget['category']['name'],
                number_format($percentage, $percentage == (int) $percentage ? 0 : 1),
                $this->budget['month_label']
            ),
        ];
    }
}

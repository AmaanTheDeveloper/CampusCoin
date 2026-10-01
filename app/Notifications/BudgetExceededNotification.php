<?php

namespace App\Notifications;

use App\Support\MoneyMath;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BudgetExceededNotification extends Notification
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
        $overage = MoneyMath::sub($this->budget['spent'], $this->budget['limit']);
        $percentage = (float) ($this->budget['percentage'] ?? 0);

        return [
            'type' => 'budget_exceeded',
            'budget_id' => $this->budget['id'],
            'category_id' => $this->budget['category']['id'],
            'category_name' => $this->budget['category']['name'],
            'month' => $this->budget['month'],
            'month_label' => $this->budget['month_label'],
            'spent' => $this->budget['spent'],
            'limit' => $this->budget['limit'],
            'percentage' => $percentage,
            'overage' => $overage,
            'message' => sprintf(
                "You've exceeded your %s budget by Rs. %s for %s.",
                $this->budget['category']['name'],
                number_format((float) $overage, 2),
                $this->budget['month_label']
            ),
        ];
    }
}

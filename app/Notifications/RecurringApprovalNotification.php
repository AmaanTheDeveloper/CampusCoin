<?php

namespace App\Notifications;

use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RecurringApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public RecurringTransactionOccurrence $occurrence,
        public RecurringTransaction $rule,
        public bool $isReminder = false,
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
        $amount = number_format((float) $this->rule->amount, 2);
        $label = filled($this->rule->description)
            ? trim((string) $this->rule->description)
            : ($this->rule->category?->name ?? 'recurring payment');
        $typeLabel = $this->rule->type->value;
        $date = $this->occurrence->occurrence_date->toDateString();

        if ($this->isReminder) {
            $message = sprintf(
                'Your Rs %s recurring %s (%s) was not approved yet. Would you like to approve it now?',
                $amount,
                $typeLabel,
                $label,
            );
            $title = 'Recurring payment still pending';
            $type = 'recurring_approval_reminder';
        } else {
            $message = sprintf(
                'Your %s %s of Rs %s is due on %s. Do you want to approve this payment?',
                $this->rule->frequency->label(),
                $label,
                $amount,
                $date,
            );
            $title = 'Upcoming recurring payment';
            $type = 'recurring_approval';
        }

        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'occurrence_id' => $this->occurrence->id,
            'recurring_transaction_id' => $this->rule->id,
            'amount' => (string) $this->rule->amount,
            'transaction_type' => $this->rule->type->value,
            'category_name' => $this->rule->category?->name,
            'occurrence_date' => $date,
            'is_reminder' => $this->isReminder,
            'play_sound' => true,
        ];
    }
}

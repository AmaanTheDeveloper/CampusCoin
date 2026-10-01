<?php

namespace App\Enums;

enum RecurringOccurrenceStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function isActionable(): bool
    {
        return $this === self::PendingApproval || $this === self::Rejected;
    }
}

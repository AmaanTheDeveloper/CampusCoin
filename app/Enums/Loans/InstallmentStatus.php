<?php

namespace App\Enums\Loans;

enum InstallmentStatus: string
{
    case Upcoming = 'upcoming';
    case Due = 'due';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Failed = 'failed';
    case Waived = 'waived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::Due => 'Due',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Failed => 'Failed',
            self::Waived => 'Waived',
            self::Cancelled => 'Cancelled',
        };
    }
}

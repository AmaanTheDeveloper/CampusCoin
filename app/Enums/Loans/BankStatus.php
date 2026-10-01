<?php

namespace App\Enums\Loans;

enum BankStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending verification',
            self::UnderReview => 'Under review',
            self::Approved => 'Active',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }
}

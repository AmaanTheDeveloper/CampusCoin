<?php

namespace App\Enums\Loans;

enum BankStaffRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Officer = 'officer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Bank owner',
            self::Admin => 'Bank admin',
            self::Officer => 'Loan officer',
        };
    }

    public function canManageStaff(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageProducts(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canReviewApplications(): bool
    {
        return true;
    }
}

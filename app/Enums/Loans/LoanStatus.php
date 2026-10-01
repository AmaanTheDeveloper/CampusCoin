<?php

namespace App\Enums\Loans;

enum LoanStatus: string
{
    case Approved = 'approved';
    case AgreementPending = 'agreement_pending';
    case ReadyForDisbursement = 'ready_for_disbursement';
    case Disbursed = 'disbursed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::AgreementPending => 'Agreement pending',
            self::ReadyForDisbursement => 'Ready for disbursement',
            self::Disbursed => 'Disbursed',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}

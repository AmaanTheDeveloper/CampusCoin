<?php

namespace App\Enums\Loans;

enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Disbursed = 'disbursed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}

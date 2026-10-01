<?php

namespace App\Enums;

enum TransactionSignalType: string
{
    case LargeTransaction = 'large_transaction';
    case DuplicateTransaction = 'duplicate_transaction';

    public function label(): string
    {
        return match ($this) {
            self::LargeTransaction => 'Unusually large transaction',
            self::DuplicateTransaction => 'Possible duplicate',
        };
    }
}

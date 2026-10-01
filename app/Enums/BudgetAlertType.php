<?php

namespace App\Enums;

enum BudgetAlertType: string
{
    case NearLimit = 'near_limit';
    case Exceeded = 'exceeded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

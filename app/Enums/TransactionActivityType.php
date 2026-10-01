<?php

namespace App\Enums;

enum TransactionActivityType: string
{
    case Viewed = 'viewed';
    case Edited = 'edited';
}

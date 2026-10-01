<?php

namespace App\Enums;

enum TransactionSignalStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Reviewed = 'reviewed';
    case KeptBoth = 'kept_both';
    case RemovedDuplicate = 'removed_duplicate';
}

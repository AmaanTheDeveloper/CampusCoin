<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Generation for this occurrence was deferred (e.g. insufficient funds or invalid category).
 * No transaction/occurrence is written and next_run_date is left unchanged for a later retry.
 */
class RecurringOccurrenceDeferredException extends RuntimeException
{
    //
}

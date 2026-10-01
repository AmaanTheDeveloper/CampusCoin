<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringTransactionsCommand extends Command
{
    protected $signature = 'campuscoin:generate-recurring
                            {--date= : Process as of this date (Y-m-d). Defaults to today.}';

    protected $description = 'Create pending recurring approval occurrences and send reminders';

    public function handle(RecurringTransactionGenerator $generator): int
    {
        $asOf = $this->option('date');

        $result = $generator->generateDue(
            $asOf ? Carbon::parse($asOf) : null
        );

        $this->info("Pending approvals created: {$result['generated']}");
        $this->info("Skipped: {$result['skipped']}");
        $this->info("Reminders sent: {$result['reminded']}");

        return self::SUCCESS;
    }
}

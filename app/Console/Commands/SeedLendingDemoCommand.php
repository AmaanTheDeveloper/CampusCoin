<?php

namespace App\Console\Commands;

use App\Support\Demo\LendingDemoBootstrap;
use App\Support\Demo\LendingDemoIds;
use Illuminate\Console\Command;

/**
 * Local/demo lending ecosystem seed.
 * Does not bypass auth, policies, or the loan state machine.
 */
class SeedLendingDemoCommand extends Command
{
    protected $signature = 'campuscoin:seed-lending-demo
                            {--no-active : Skip the optional ACTIVE repayment demo loan}
                            {--reset-walkthrough : Reset APP-DEMO-WALKTHROUGH to SUBMITTED (dev only)}';

    protected $description = 'Seed idempotent Campus Demo Bank lending scenario (local/development/testing only)';

    public function handle(LendingDemoBootstrap $bootstrap): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('Refused: campuscoin:seed-lending-demo only runs when APP_ENV is local, development, or testing.');
            $this->line('Current APP_ENV='.(string) config('app.env'));

            return self::FAILURE;
        }

        if ($this->option('reset-walkthrough')) {
            $application = $bootstrap->resetWalkthrough();
            $this->info('Walkthrough application reset to SUBMITTED: '.$application->reference);
            $this->line('Open Bank → Applications → '.$application->reference);
        }

        $result = $bootstrap->seed(withActiveLoan: ! $this->option('no-active'));

        $this->newLine();
        $this->info('Lending demo ecosystem ready.');
        $this->table(
            ['Item', 'Value'],
            [
                ['Bank', $result['bank']->name.' ('.$result['bank']->status->value.')'],
                ['Product', $result['product']->name],
                ['Student', LendingDemoIds::STUDENT_EMAIL],
                ['Bank owner', LendingDemoIds::OWNER_EMAIL],
                ['Bank officer', LendingDemoIds::OFFICER_EMAIL],
                ['Walkthrough app', LendingDemoIds::APP_WALKTHROUGH_REFERENCE.' ('.$result['walkthrough_application']->status->value.')'],
                ['Active loan', $result['active_loan']?->reference ?? 'skipped'],
                ['Demo admin', $result['admin']?->email ?? 'existing admin preserved'],
            ],
        );

        $this->warn('DEVELOPMENT / DEMO ONLY — see DEMO_CREDENTIALS.md and DEMO_LOAN_FLOW.md');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Support\Demo\LendingDemoBootstrap;
use App\Support\Demo\LendingDemoIds;
use Illuminate\Database\Seeder;

/**
 * Idempotent DEVELOPMENT / DEMO lending ecosystem.
 * Prefer: php artisan campuscoin:seed-lending-demo
 */
class LendingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(LendingDemoBootstrap::class)->seed(withActiveLoan: true);

        $this->command?->info('Campus Demo Bank ready: '.$result['bank']->name);
        $this->command?->info('Product: '.$result['product']->name);
        $this->command?->info('Walkthrough application: '.LendingDemoIds::APP_WALKTHROUGH_REFERENCE);
        if ($result['active_loan']) {
            $this->command?->info('Active demo loan: '.$result['active_loan']->reference);
        }
        $this->command?->warn('Credentials: see DEMO_CREDENTIALS.md (DEVELOPMENT / DEMO ONLY)');
    }
}

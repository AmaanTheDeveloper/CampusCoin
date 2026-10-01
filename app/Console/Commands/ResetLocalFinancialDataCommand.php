<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One-shot LOCAL/DEVELOPMENT wipe of student financial/demo data.
 * Preserves users, authentication, default categories, and schema.
 */
class ResetLocalFinancialDataCommand extends Command
{
    protected $signature = 'campuscoin:reset-local-financial-data
                            {--force : Required confirmation to run the destructive reset}';

    protected $description = 'Clear local/dev financial and demo records while preserving users and default categories';

    /**
     * Child → parent delete order for FK safety.
     *
     * @var list<string>
     */
    private array $tables = [
        'savings_goal_contributions',
        'savings_goals',
        'recurring_transaction_occurrences',
        'transaction_signals',
        'transaction_activities',
        'bookmarks',
        'saving_tips',
        'ai_monthly_insights',
        'ai_category_feedback',
        'budget_alerts',
        'notifications',
        'budgets',
        'recurring_transactions',
        'transactions',
    ];

    public function handle(): int
    {
        if (! $this->environmentAllowed()) {
            $this->error('Refused: this command only runs when APP_ENV is local, development, or testing.');
            $this->line('Current APP_ENV='.(string) config('app.env'));

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Refused: pass --force to confirm this destructive local reset.');

            return self::FAILURE;
        }

        $host = (string) config('database.connections.'.config('database.default').'.host');
        $database = (string) config('database.connections.'.config('database.default').'.database');

        $this->warn('Resetting financial/demo data on:');
        $this->line('  APP_ENV='.config('app.env'));
        $this->line("  DB_HOST={$host}");
        $this->line("  DB_DATABASE={$database}");

        $before = $this->counts();
        $this->table(['Table', 'Before'], collect($before)->map(fn ($count, $table) => [$table, $count])->values()->all());

        try {
            DB::transaction(function (): void {
                foreach ($this->tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    DB::table($table)->delete();
                }

                // Remove user-created categories only; keep required defaults.
                if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'is_default')) {
                    DB::table('categories')->where('is_default', false)->delete();
                }

                // Fresh financial profile baseline (auth identity untouched).
                if (Schema::hasTable('users')) {
                    $profile = [];
                    if (Schema::hasColumn('users', 'monthly_allowance')) {
                        $profile['monthly_allowance'] = null;
                    }
                    if (Schema::hasColumn('users', 'savings_goal')) {
                        $profile['savings_goal'] = null;
                    }
                    if ($profile !== []) {
                        DB::table('users')->update($profile);
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->error('Reset failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $after = $this->counts();
        $this->table(['Table', 'After'], collect($after)->map(fn ($count, $table) => [$table, $count])->values()->all());

        $users = Schema::hasTable('users') ? DB::table('users')->count() : 0;
        $defaultCategories = Schema::hasTable('categories')
            ? DB::table('categories')->where('is_default', true)->count()
            : 0;
        $customCategories = Schema::hasTable('categories')
            ? DB::table('categories')->where('is_default', false)->count()
            : 0;

        $this->info("Preserved users={$users}, default_categories={$defaultCategories}");
        $this->info("Custom categories remaining={$customCategories}");
        $this->info('Local financial/demo data reset complete.');

        return self::SUCCESS;
    }

    private function environmentAllowed(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    /**
     * @return array<string, int|string>
     */
    private function counts(): array
    {
        $counts = [];

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                $counts[$table] = 'missing';

                continue;
            }

            $counts[$table] = DB::table($table)->count();
        }

        if (Schema::hasTable('categories')) {
            $counts['categories_default'] = DB::table('categories')->where('is_default', true)->count();
            $counts['categories_custom'] = DB::table('categories')->where('is_default', false)->count();
        }

        return $counts;
    }
}

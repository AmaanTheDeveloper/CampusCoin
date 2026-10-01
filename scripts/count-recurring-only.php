<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tables = [
    'recurring_transactions',
    'recurring_transaction_occurrences',
    'transactions',
];

foreach ($tables as $table) {
    if (! Schema::hasTable($table)) {
        echo "{$table}=missing\n";

        continue;
    }
    echo "{$table}=".DB::table($table)->count()."\n";
}

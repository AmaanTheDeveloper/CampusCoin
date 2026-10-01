<?php

/**
 * Smart Scan browser QA helpers.
 * Usage: php scripts/qa-smart-scan-setup.php setup|teardown|count|food-id
 */

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'setup';
$marker = '[qa-smart-scan]';
$user = User::query()->where('email', 'test@example.com')->first();

if ($user === null) {
    fwrite(STDERR, "test@example.com not found\n");
    exit(1);
}

if ($action === 'teardown') {
    Transaction::withTrashed()
        ->where('user_id', $user->id)
        ->where('description', 'like', $marker.'%')
        ->forceDelete();

    // Also clear Campus Cafe from fake extractor confirm if tagged
    Transaction::withTrashed()
        ->where('user_id', $user->id)
        ->where('description', 'Campus Cafe')
        ->whereDate('created_at', today())
        ->where('amount', '42.00')
        ->forceDelete();

    echo "teardown_ok\n";
    exit(0);
}

if ($action === 'count') {
    $count = Transaction::query()
        ->where('user_id', $user->id)
        ->where(function ($q) use ($marker) {
            $q->where('description', 'like', $marker.'%')
                ->orWhere(function ($q2) {
                    $q2->where('description', 'Campus Cafe')->where('amount', '42.00');
                });
        })
        ->count();
    echo $count."\n";
    exit(0);
}

if ($action === 'food-id') {
    $id = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Food')->value('id');
    echo (string) $id."\n";
    exit(0);
}

// setup: ensure funding income exists
$incomeCat = Category::query()->whereNull('user_id')->where('type', 'income')->where('name', 'Allowance')->firstOrFail();
$hasIncome = $user->transactions()->where('type', 'income')->exists();
if (! $hasIncome) {
    $user->transactions()->create([
        'type' => 'income',
        'category_id' => $incomeCat->id,
        'amount' => '100000.00',
        'description' => $marker.' funding',
        'transaction_date' => '2026-01-01',
    ]);
}

$foodId = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Food')->value('id');

echo "setup_ok food={$foodId}\n";

<?php

/**
 * Temporary QA fixtures for budget-overrun browser scenarios.
 * Usage: php scripts/qa-budget-overrun-setup.php setup|teardown|bump9000|refill|transport-edit|count-expense|count-desc <desc>
 */

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'setup';
$user = User::query()->where('email', 'test@example.com')->firstOrFail();
$academics = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Academics')->first()
    ?? Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Food')->firstOrFail();
$transport = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Transport')->firstOrFail();
$misc = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Miscellaneous')->firstOrFail();
$income = Category::query()->whereNull('user_id')->where('type', 'income')->where('name', 'Allowance')->firstOrFail();
$marker = '[qa-budget-overrun]';
$month = now()->startOfMonth()->toDateString();

if ($action === 'teardown') {
    Transaction::withTrashed()
        ->where('user_id', $user->id)
        ->where('description', 'like', $marker.'%')
        ->forceDelete();

    Budget::query()->where('user_id', $user->id)->whereDate('month', $month)->whereIn('category_id', [$academics->id, $transport->id])->delete();

    echo "teardown_ok\n";
    exit(0);
}

if ($action === 'bump9000') {
    $user->transactions()->create([
        'type' => 'expense',
        'category_id' => $academics->id,
        'amount' => '1000.00',
        'description' => $marker.' bump to 9000',
        'transaction_date' => now()->toDateString(),
    ]);
    echo "bump_ok\n";
    exit(0);
}

if ($action === 'refill') {
    $user->transactions()->create([
        'type' => 'income',
        'category_id' => $income->id,
        'amount' => '100000.00',
        'description' => $marker.' refill',
        'transaction_date' => now()->toDateString(),
    ]);
    echo 'balance='.AvailableBalance::forUser($user)."\n";
    exit(0);
}

if ($action === 'transport-edit') {
    $user->budgets()->updateOrCreate(
        ['category_id' => $transport->id, 'month' => $month],
        ['limit_amount' => '5000.00'],
    );
    $user->transactions()->create([
        'type' => 'expense',
        'category_id' => $transport->id,
        'amount' => '4000.00',
        'description' => $marker.' transport prior',
        'transaction_date' => now()->toDateString(),
    ]);
    $tx = $user->transactions()->create([
        'type' => 'expense',
        'category_id' => $transport->id,
        'amount' => '500.00',
        'description' => $marker.' transport edit target',
        'transaction_date' => now()->toDateString(),
    ]);
    echo "edit_tx={$tx->id} transport_id={$transport->id}\n";
    exit(0);
}

if ($action === 'count-expense') {
    echo Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->count()."\n";
    exit(0);
}

if ($action === 'count-desc') {
    $desc = $argv[2] ?? '';
    echo Transaction::query()->where('description', $desc)->count()."\n";
    exit(0);
}

if ($action === 'ids') {
    echo "academics={$academics->id} transport={$transport->id} misc={$misc->id} income={$income->id}\n";
    exit(0);
}

// setup
Transaction::withTrashed()
    ->where('user_id', $user->id)
    ->where('description', 'like', $marker.'%')
    ->forceDelete();

Budget::query()->where('user_id', $user->id)->whereDate('month', $month)->whereIn('category_id', [$academics->id, $transport->id])->delete();

$user->transactions()->create([
    'type' => 'income',
    'category_id' => $income->id,
    'amount' => '50000.00',
    'description' => $marker.' income',
    'transaction_date' => now()->toDateString(),
]);

$user->budgets()->create([
    'category_id' => $academics->id,
    'month' => $month,
    'limit_amount' => '10000.00',
]);

$user->transactions()->create([
    'type' => 'expense',
    'category_id' => $academics->id,
    'amount' => '7000.00',
    'description' => $marker.' prior spend 7000',
    'transaction_date' => now()->toDateString(),
]);

echo "setup_ok academics={$academics->id} category_name={$academics->name} misc={$misc->id} income={$income->id}\n";

<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->where('email', 'test@example.com')->first();
if (! $user) {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'savings_goal' => 10000,
    ]);
    echo "created_user\n";
}

$food = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Food')->first();
$transport = Category::query()->whereNull('user_id')->where('type', 'expense')->where('name', 'Transport')->first();
$allowance = Category::query()->whereNull('user_id')->where('type', 'income')->where('name', 'Allowance')->first();

$monthStart = now()->startOfMonth()->toDateString();
$marker = '[qa-dashboard-aware]';

$expense = (float) Transaction::query()
    ->where('user_id', $user->id)
    ->where('type', 'expense')
    ->whereDate('transaction_date', '>=', $monthStart)
    ->sum('amount');

$foodSpend = 0.0;
$transportSpend = 0.0;
if ($food) {
    $foodSpend = (float) Transaction::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->where('category_id', $food->id)
        ->whereDate('transaction_date', '>=', $monthStart)
        ->sum('amount');
}
if ($transport) {
    $transportSpend = (float) Transaction::query()
        ->where('user_id', $user->id)
        ->where('type', 'expense')
        ->where('category_id', $transport->id)
        ->whereDate('transaction_date', '>=', $monthStart)
        ->sum('amount');
}

$balance = AvailableBalance::forUser($user);

// Ensure enough income + Food/Transport spend for acceptance checks.
if ($balance === null || (float) $balance < 1000) {
    if ($allowance) {
        $user->transactions()->create([
            'type' => 'income',
            'category_id' => $allowance->id,
            'amount' => '80000.00',
            'description' => $marker.' income top-up',
            'transaction_date' => now()->toDateString(),
        ]);
    }
}

if ($food && $foodSpend < 100) {
    $user->transactions()->create([
        'type' => 'expense',
        'category_id' => $food->id,
        'amount' => '5000.00',
        'description' => $marker.' Food',
        'transaction_date' => now()->toDateString(),
    ]);
}

if ($transport && $transportSpend < 100) {
    $user->transactions()->create([
        'type' => 'expense',
        'category_id' => $transport->id,
        'amount' => '2000.00',
        'description' => $marker.' Transport',
        'transaction_date' => now()->toDateString(),
    ]);
}

$user->refresh();
$balance = AvailableBalance::forUser($user);
$expense = (float) Transaction::query()
    ->where('user_id', $user->id)
    ->where('type', 'expense')
    ->whereDate('transaction_date', '>=', $monthStart)
    ->sum('amount');
$foodSpend = $food ? (float) Transaction::query()
    ->where('user_id', $user->id)
    ->where('type', 'expense')
    ->where('category_id', $food->id)
    ->whereDate('transaction_date', '>=', $monthStart)
    ->sum('amount') : 0;
$transportSpend = $transport ? (float) Transaction::query()
    ->where('user_id', $user->id)
    ->where('type', 'expense')
    ->where('category_id', $transport->id)
    ->whereDate('transaction_date', '>=', $monthStart)
    ->sum('amount') : 0;

// Ensure a second student exists for isolation checks (no shared data).
$other = User::query()->where('email', 'qa-isolation-b@example.com')->first();
if (! $other) {
    $other = User::factory()->create([
        'name' => 'QA Isolation B',
        'email' => 'qa-isolation-b@example.com',
        'savings_goal' => 5000,
    ]);
    if ($allowance) {
        $other->transactions()->create([
            'type' => 'income',
            'category_id' => $allowance->id,
            'amount' => '12345.00',
            'description' => $marker.' isolation B income',
            'transaction_date' => now()->toDateString(),
        ]);
    }
    if ($food) {
        $other->transactions()->create([
            'type' => 'expense',
            'category_id' => $food->id,
            'amount' => '777.00',
            'description' => $marker.' isolation B food',
            'transaction_date' => now()->toDateString(),
        ]);
    }
}

echo json_encode([
    'user_id' => $user->id,
    'email' => $user->email,
    'available_balance' => $balance,
    'month_expense' => number_format($expense, 2, '.', ''),
    'food' => number_format($foodSpend, 2, '.', ''),
    'transport' => number_format($transportSpend, 2, '.', ''),
    'isolation_b' => $other->email,
    'isolation_b_balance' => AvailableBalance::forUser($other),
], JSON_PRETTY_PRINT)."\n";

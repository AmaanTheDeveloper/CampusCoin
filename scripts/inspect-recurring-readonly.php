<?php

use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'recurring_count='.DB::table('recurring_transactions')->count().PHP_EOL;

RecurringTransaction::query()->withTrashed()->get(['id', 'user_id', 'type', 'amount', 'frequency', 'next_run_date', 'is_active', 'description', 'deleted_at'])->each(function ($r) {
    $email = User::query()->whereKey($r->user_id)->value('email');
    $deleted = $r->deleted_at ? '1' : '0';
    echo "id={$r->id} user_id={$r->user_id} email={$email} type={$r->type->value} amount={$r->amount} freq={$r->frequency->value} next={$r->next_run_date->toDateString()} active=".($r->is_active ? '1' : '0')." deleted={$deleted} desc=".($r->description ?? '').PHP_EOL;
});

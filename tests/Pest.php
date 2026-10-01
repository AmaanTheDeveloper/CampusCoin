<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DefaultCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function seedDefaultCategories(): void
{
    (new DefaultCategorySeeder)->run();
}

function defaultCategoryId(string $type, string $name): int
{
    $id = Category::query()
        ->whereNull('user_id')
        ->where('type', $type)
        ->where('name', $name)
        ->value('id');

    expect($id)->not->toBeNull();

    return (int) $id;
}

/**
 * Seed income so expense create/update tests can pass the wallet balance rule.
 */
function fundUser(User $user, string $amount = '100000.00'): void
{
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => $amount,
        'transaction_date' => '2026-01-01',
        'description' => 'Test funding',
    ]);
}

<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedDefaultCategories();
});

test('reset local financial data clears finance tables and preserves users and categories', function () {
    $user = User::factory()->create([
        'monthly_allowance' => '15000.00',
        'savings_goal' => '5000.00',
    ]);

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);
    Budget::factory()->for($user)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-09-01',
        'limit_amount' => '50.00',
    ]);
    Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Custom Test Cat',
        'type' => 'expense',
        'is_default' => false,
    ]);

    expect(Transaction::count())->toBeGreaterThan(0);
    expect(Budget::count())->toBeGreaterThan(0);

    $usersBefore = User::count();
    $defaultCategoriesBefore = Category::query()->where('is_default', true)->count();

    $exit = Artisan::call('campuscoin:reset-local-financial-data', ['--force' => true]);

    expect($exit)->toBe(0);
    expect(Transaction::count())->toBe(0);
    expect(Budget::count())->toBe(0);
    expect(User::count())->toBe($usersBefore);
    expect(Category::query()->where('is_default', false)->count())->toBe(0);
    expect(Category::query()->where('is_default', true)->count())->toBe($defaultCategoriesBefore);
    expect(Category::query()->where('is_default', true)->count())->toBeGreaterThan(0);
    $user->refresh();
    expect($user->monthly_allowance)->toBeNull();
    expect($user->savings_goal)->toBeNull();
});

test('reset refuses without force flag', function () {
    $exit = Artisan::call('campuscoin:reset-local-financial-data');

    expect($exit)->toBe(1);
});

test('reset is idempotent when already empty', function () {
    $usersBefore = User::count();

    expect(Artisan::call('campuscoin:reset-local-financial-data', ['--force' => true]))->toBe(0);
    expect(Artisan::call('campuscoin:reset-local-financial-data', ['--force' => true]))->toBe(0);
    expect(User::count())->toBe($usersBefore);
    expect(DB::table('transactions')->count())->toBe(0);
});

<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthlyForecastService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access month forecast', function () {
    $this->get(route('month-forecast.index'))->assertRedirect(route('login'));
});

test('authenticated user can open month forecast page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('month-forecast.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('month-forecast/index')
            ->has('forecast')
            ->where('error', null)
        );
});

test('dashboard includes month forecast payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('dashboard.month_forecast'));
});

test('zero expenses yields insufficient forecast without fake numbers', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '5000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['available'])->toBeFalse();
    expect($forecast['forecast']['expenses'])->toBeNull();
    expect($forecast['forecast']['expected_savings'])->toBeNull();
    expect($forecast['status'])->toBe('insufficient');
});

test('core pace formula projects month to date expenses', function () {
    // Sep 15 → 15 elapsed, 30 days in Sep 2026
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '20000.00',
        'transaction_date' => '2026-09-10',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['available'])->toBeTrue();
    expect($forecast['period']['days_elapsed'])->toBe(15);
    expect($forecast['period']['days_in_month'])->toBe(30);
    expect($forecast['actual']['expenses'])->toBe('20000.00');
    expect($forecast['pace']['daily_expenses'])->toBe('1333.33'); // 20000/15
    expect($forecast['forecast']['expenses'])->toBe('39999.90'); // 1333.33 * 30
});

test('scenario A style forecast matches deterministic pace', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '20000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['period']['days_elapsed'])->toBe(10);
    expect($forecast['pace']['daily_expenses'])->toBe('2000.00');
    expect($forecast['forecast']['expenses'])->toBe('60000.00');
});

test('expected savings uses known mtd income minus forecast expenses', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '20000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['forecast']['expected_savings'])->toBe('-10000.00'); // 50000 - 60000
});

test('first day of month never divides by zero', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00', config('app.timezone')));
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['period']['days_elapsed'])->toBe(1);
    expect($forecast['pace']['daily_expenses'])->toBe('100.00');
    expect($forecast['forecast']['expenses'])->toBe('3000.00');
    expect($forecast['period']['early_month'])->toBeTrue();
});

test('projected budget overrun uses largest projected amount', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    $user = User::factory()->create();
    fundUser($user, '50000.00');

    $food = Category::query()->where('name', 'Food')->where('is_default', true)->firstOrFail();
    $transport = Category::query()->where('name', 'Transport')->where('is_default', true)->firstOrFail();

    Budget::factory()->for($user)->create([
        'category_id' => $food->id,
        'month' => '2026-09-01',
        'limit_amount' => '7000.00',
    ]);
    Budget::factory()->for($user)->create([
        'category_id' => $transport->id,
        'month' => '2026-09-01',
        'limit_amount' => '5000.00',
    ]);

    // Food: 3000 in 10 days → forecast 9000 → overrun 2000
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '3000.00',
        'transaction_date' => '2026-09-05',
    ]);
    // Transport: 1000 in 10 days → forecast 3000 → under budget
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport->id,
        'amount' => '1000.00',
        'transaction_date' => '2026-09-06',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['budget_alert']['available'])->toBeTrue();
    expect($forecast['budget_alert']['kind'])->toBe('projected_over');
    expect($forecast['budget_alert']['category'])->toBe('Food');
    expect($forecast['budget_alert']['overrun'])->toBe('2000.00');
    expect($forecast['budget_alert']['message'])->toContain('may exceed');
    expect($forecast['status'])->toBe('budget_watch');
});

test('already over budget is distinguished from projected overrun', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = Category::query()->where('name', 'Food')->where('is_default', true)->firstOrFail();

    Budget::factory()->for($user)->create([
        'category_id' => $food->id,
        'month' => '2026-09-01',
        'limit_amount' => '5000.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '6500.00',
        'transaction_date' => '2026-09-05',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['budget_alert']['kind'])->toBe('already_over');
    expect($forecast['budget_alert']['overrun'])->toBe('1500.00');
    expect($forecast['budget_alert']['message'])->toContain('currently');
    expect($forecast['status'])->toBe('already_over_budget');
});

test('no budget means no budget overrun alert', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '500.00',
        'transaction_date' => '2026-09-05',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);

    expect($forecast['budget_alert'])->toBeNull();
    expect($forecast['status'])->toBe('pace_only');
});

test('forecast updates after new expense is recorded', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', config('app.timezone')));
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $before = app(MonthlyForecastService::class)->forUser($user);
    expect($before['forecast']['expenses'])->toBe('30000.00');

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'category_id' => $food,
        'amount' => '5000.00',
        'description' => 'Extra spend',
        'transaction_date' => '2026-09-09',
    ])->assertRedirect();

    $after = app(MonthlyForecastService::class)->forUser($user);
    expect($after['actual']['expenses'])->toBe('15000.00');
    expect($after['forecast']['expenses'])->toBe('45000.00');
});

test('month forecast is isolated between users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    Transaction::factory()->for($owner)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '3000.00',
        'transaction_date' => '2026-09-05',
    ]);
    Transaction::factory()->for($other)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '9000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $ownerForecast = app(MonthlyForecastService::class)->forUser($owner);
    $otherForecast = app(MonthlyForecastService::class)->forUser($other);

    expect($ownerForecast['actual']['expenses'])->toBe('3000.00');
    expect($otherForecast['actual']['expenses'])->toBe('9000.00');
});

test('series includes actual through today and forecast to month end', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '1500.00',
        'transaction_date' => '2026-09-05',
    ]);

    $forecast = app(MonthlyForecastService::class)->forUser($user);
    $phases = collect($forecast['series'])->pluck('phase')->unique()->values()->all();

    expect($phases)->toContain('actual');
    expect($phases)->toContain('forecast');
    expect(collect($forecast['series'])->last()['forecast'])->toBe($forecast['forecast']['expenses']);
});

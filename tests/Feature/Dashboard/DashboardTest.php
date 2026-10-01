<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('unverified users are redirected away from the dashboard', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));
});

test('verified users can visit the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('dashboard')
            ->where('error', null)
        );
});

test('empty dashboard has onboarding state without fake metrics charts data', function () {
    $user = User::factory()->create(['name' => 'Ali']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.has_transactions', false)
            ->where('dashboard.summary.income', '0.00')
            ->where('dashboard.summary.expense', '0.00')
            ->where('dashboard.summary.balance', '0.00')
            ->where('dashboard.summary.available_balance', '0.00')
            ->where('dashboard.greeting.name', 'Ali')
            ->where('dashboard.greeting.period', 'afternoon')
            ->where('dashboard.period.label', 'September 2026')
            ->where('dashboard.highlights', [])
            ->where('dashboard.top_category', null)
            ->where('dashboard.largest_expense', null)
            ->where('dashboard.recent_transactions', [])
            ->where('dashboard.budgets', [])
        );
});

test('dashboard greeting falls back when user name is blank', function () {
    $user = User::factory()->create(['name' => '']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('dashboard.greeting.name', 'there'));
});

test('user a dashboard never includes user b transaction data', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Transaction::factory()->for($userA)->income()->create([
        'amount' => '100.00',
        'transaction_date' => '2026-09-10',
        'category_id' => defaultCategoryId('income', 'Allowance'),
    ]);
    Transaction::factory()->for($userB)->expense()->create([
        'amount' => '999.00',
        'transaction_date' => '2026-09-10',
        'category_id' => defaultCategoryId('expense', 'Food'),
        'description' => 'Secret B',
    ]);

    $this->actingAs($userA)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.income', '100.00')
            ->where('dashboard.summary.expense', '0.00')
            ->where('dashboard.recent_transactions', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['description'] !== 'Secret B'
            ))
        );
});

test('personal categories from another user never appear', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $secret = Category::factory()->for($userB)->expense()->create(['name' => 'SecretCat']);

    Transaction::factory()->for($userA)->expense()->create([
        'amount' => '20.00',
        'transaction_date' => '2026-09-10',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);
    Transaction::factory()->for($userB)->expense()->create([
        'amount' => '50.00',
        'transaction_date' => '2026-09-10',
        'category_id' => $secret->id,
    ]);

    $this->actingAs($userA)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.category_breakdown', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['name'] !== 'SecretCat'
            ))
        );
});

test('summary income expense balance savings and rate are correct', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
        'category_id' => defaultCategoryId('income', 'Allowance'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '8000.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '2000.00',
        'transaction_date' => '2026-09-08',
        'category_id' => defaultCategoryId('expense', 'Transport'),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.income', '50000.00')
            ->where('dashboard.summary.expense', '10000.00')
            ->where('dashboard.summary.balance', '40000.00')
            ->where('dashboard.summary.savings', '40000.00')
            ->where('dashboard.summary.available_balance', '40000.00')
            ->where('dashboard.summary.savings_rate', 80)
            ->where('dashboard.summary.transaction_count', 3)
        );
});

test('zero income savings rate is null', function () {
    $user = User::factory()->create();

    // Prior-month income funds current-month expense so the wallet stays non-negative.
    Transaction::factory()->for($user)->income()->create([
        'amount' => '100.00',
        'transaction_date' => '2026-08-15',
        'category_id' => defaultCategoryId('income', 'Allowance'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '10.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.savings_rate', null)
            ->where('dashboard.summary.balance', '-10.00')
            ->where('dashboard.summary.available_balance', '90.00')
        );
});

test('period deficit is honest while available balance stays non-negative', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'amount' => '1000.00',
        'transaction_date' => '2026-08-01',
        'category_id' => defaultCategoryId('income', 'Gift'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '250.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.balance', '-250.00')
            ->where('dashboard.summary.savings_rate', null)
            ->where('dashboard.summary.available_balance', '750.00')
        );
});

test('category breakdown percentages and top category are correct including uncategorized', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->expense()->create([
        'amount' => '8000.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '2000.00',
        'transaction_date' => '2026-09-06',
        'category_id' => null,
        'description' => 'Misc',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.expense', '10000.00')
            ->where('dashboard.top_category.name', 'Food')
            ->where('dashboard.top_category.amount', '8000.00')
            ->where('dashboard.top_category.percentage', 80)
            ->where('dashboard.category_breakdown.1.name', 'Uncategorized')
            ->where('dashboard.category_breakdown.1.amount', '2000.00')
            ->where('dashboard.category_breakdown.1.percentage', 20)
        );
});

test('soft deleted transactions are excluded from all dashboard analytics', function () {
    $user = User::factory()->create();

    $kept = Transaction::factory()->for($user)->expense()->create([
        'amount' => '100.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
        'description' => 'Kept',
    ]);
    $deleted = Transaction::factory()->for($user)->expense()->create([
        'amount' => '900.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
        'description' => 'Deleted',
    ]);
    $deleted->delete();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.summary.expense', '100.00')
            ->where('dashboard.summary.transaction_count', 1)
            ->where('dashboard.recent_transactions.0.id', $kept->id)
            ->where('dashboard.largest_expense.id', $kept->id)
        );
});

test('daily trend fills missing days with zero and stops at today', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->expense()->create([
        'amount' => '50.00',
        'transaction_date' => '2026-09-10',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);
    // Future-dated within month — included in totals, not in daily-to-today chart.
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '70.00',
        'transaction_date' => '2026-09-20',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $payload = app(DashboardAnalyticsService::class)->forUser($user);
    $trend = $payload['daily_expense_trend'];

    expect($trend[0]['date'])->toBe('2026-09-01');
    expect($trend[array_key_last($trend)]['date'])->toBe('2026-09-15');
    expect(collect($trend)->firstWhere('date', '2026-09-10')['amount'])->toBe('50.00');
    expect(collect($trend)->firstWhere('date', '2026-09-11')['amount'])->toBe('0.00');
    expect(collect($trend)->firstWhere('date', '2026-09-20'))->toBeNull();
    expect($payload['summary']['expense'])->toBe('120.00');
});

test('monthly trend covers six calendar months', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'amount' => '100.00',
        'transaction_date' => '2026-04-15',
        'category_id' => defaultCategoryId('income', 'Allowance'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '40.00',
        'transaction_date' => '2026-09-02',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.monthly_trend', 6)
            ->where('dashboard.monthly_trend.0.month', '2026-04')
            ->where('dashboard.monthly_trend.0.income', '100.00')
            ->where('dashboard.monthly_trend.5.month', '2026-09')
            ->where('dashboard.monthly_trend.5.expense', '40.00')
        );
});

test('recent transactions are bounded and include category', function () {
    $user = User::factory()->create();

    foreach (range(1, 8) as $day) {
        Transaction::factory()->for($user)->expense()->create([
            'amount' => '1.00',
            'transaction_date' => sprintf('2026-09-%02d', $day),
            'category_id' => defaultCategoryId('expense', 'Food'),
            'description' => "Day {$day}",
        ]);
    }

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.recent_transactions', 5)
            ->where('dashboard.recent_transactions.0.description', 'Day 8')
            ->where('dashboard.recent_transactions.0.category.name', 'Food')
        );
});

test('largest expense and highlights match actual data', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'amount' => '1000.00',
        'transaction_date' => '2026-09-01',
        'category_id' => defaultCategoryId('income', 'Allowance'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '300.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
        'description' => null,
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '100.00',
        'transaction_date' => '2026-09-06',
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'description' => 'Bus',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.largest_expense.description', 'Unnamed expense')
            ->where('dashboard.largest_expense.amount', '300.00')
            ->where('dashboard.highlights', fn ($items) => collect($items)->contains(
                fn ($item) => str_contains($item['message'], 'Food is your highest expense category')
            ))
        );
});

test('dashboard totals reconcile with current month transaction history filter', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'amount' => '200.50',
        'transaction_date' => '2026-09-03',
        'category_id' => defaultCategoryId('income', 'Gift'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '50.25',
        'transaction_date' => '2026-09-04',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'amount' => '10.00',
        'transaction_date' => '2026-08-31',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $historyIncome = (string) $user->transactions()
        ->where('type', 'income')
        ->whereDate('transaction_date', '>=', '2026-09-01')
        ->whereDate('transaction_date', '<=', '2026-09-30')
        ->sum('amount');
    $historyExpense = (string) $user->transactions()
        ->where('type', 'expense')
        ->whereDate('transaction_date', '>=', '2026-09-01')
        ->whereDate('transaction_date', '<=', '2026-09-30')
        ->sum('amount');

    $payload = app(DashboardAnalyticsService::class)->forUser($user);

    expect($payload['summary']['income'])->toBe(MoneyMath::normalize($historyIncome));
    expect($payload['summary']['expense'])->toBe(MoneyMath::normalize($historyExpense));
    expect(MoneyMath::normalize(
        collect($payload['category_breakdown'])->reduce(
            fn ($carry, $row) => MoneyMath::add($carry, $row['amount']),
            '0.00'
        )
    ))->toBe($payload['summary']['expense']);
});

test('admin role does not grant another users dashboard data', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    Transaction::factory()->for($owner)->expense()->create([
        'amount' => '77.00',
        'transaction_date' => '2026-09-05',
        'category_id' => defaultCategoryId('expense', 'Food'),
        'description' => 'Owner only',
    ]);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.has_transactions', false)
            ->where('dashboard.summary.expense', '0.00')
        );
});

test('dashboard uses aggregation and remains correct with many transactions', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $rows = [];
    for ($i = 0; $i < 500; $i++) {
        $rows[] = [
            'user_id' => $user->id,
            'category_id' => $food,
            'type' => 'expense',
            'amount' => '1.00',
            'description' => "Row {$i}",
            'transaction_date' => '2026-09-10',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('transactions')->insert($rows);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $payload = app(DashboardAnalyticsService::class)->forUser($user);

    expect($payload['summary']['expense'])->toBe('500.00');
    expect($payload['summary']['transaction_count'])->toBe(500);
    expect($queries)->toBeLessThan(15);
});

test('create transaction accepts type query for quick actions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('transactions.create', ['type' => 'income']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('initialType', 'income'));
});

test('dashboard failure returns safe error and logs without stack trace', function () {
    Event::fake([MessageLogged::class]);

    $user = User::factory()->create();

    $this->mock(DashboardAnalyticsService::class, function ($mock) {
        $mock->shouldReceive('forUser')->once()->andThrow(new RuntimeException('simulated dashboard failure'));
    });

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('dashboard', null)
            ->where('error', 'Your dashboard data couldn\'t be loaded. Please try again.')
        );

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($user) {
        return $event->level === 'error'
            && $event->message === 'dashboard.load_failed'
            && ($event->context['user_id'] ?? null) === $user->id
            && ($event->context['exception'] ?? null) === RuntimeException::class
            && ($event->context['message'] ?? null) === 'simulated dashboard failure'
            && ! array_key_exists('trace', $event->context)
            && ! array_key_exists('password', $event->context);
    });
});

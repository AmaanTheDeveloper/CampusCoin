<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\FinancialHealthService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

function moneyPulseFor(User $user): array
{
    $dashboard = app(DashboardAnalyticsService::class)->forUser($user);

    return app(FinancialHealthService::class)->dashboardPayload($user, $dashboard);
}

test('dashboard includes money pulse for authenticated user only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.money_pulse')
            ->has('dashboard.money_pulse.overall')
            ->has('dashboard.money_pulse.metrics.savings_consistency')
            ->has('dashboard.money_pulse.metrics.budget_discipline')
            ->has('dashboard.money_pulse.metrics.spending_stability')
            ->has('dashboard.money_pulse.metrics.goal_progress')
            ->has('dashboard.money_pulse.spending_trend')
        );
});

test('guest cannot access money pulse via dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('new user without transactions gets insufficient money pulse', function () {
    $user = User::factory()->create();
    $pulse = moneyPulseFor($user);

    expect($pulse['overall']['status'])->toBe('insufficient');
    expect($pulse['overall']['score'])->toBeNull();
    expect($pulse['metrics']['savings_consistency']['available'])->toBeFalse();
    expect($pulse['metrics']['budget_discipline']['available'])->toBeFalse();
    expect($pulse['metrics']['goal_progress']['summary'])->toBe('No active goals');
    expect($pulse['spending_trend']['available'])->toBeFalse();
});

test('savings consistency scores positive and deficit months', function () {
    $user = User::factory()->create();

    // Aug: saved
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1000.00',
        'transaction_date' => '2026-08-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '200.00',
        'transaction_date' => '2026-08-05',
    ]);

    // Sep: did not save
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '500.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '600.00',
        'transaction_date' => '2026-09-05',
    ]);

    $pulse = moneyPulseFor($user);
    $savings = $pulse['metrics']['savings_consistency'];

    expect($savings['available'])->toBeTrue();
    expect($savings['score'])->toBe(50);
    expect($savings['detail']['eligible_periods'])->toBe(2);
    expect($savings['detail']['saved_periods'])->toBe(1);
});

test('savings consistency unavailable with only one income month', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);

    $savings = moneyPulseFor($user)['metrics']['savings_consistency'];

    expect($savings['available'])->toBeFalse();
    expect($savings['score'])->toBeNull();
});

test('zero income months are not eligible for savings consistency', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '50.00',
        'transaction_date' => '2026-08-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '50.00',
        'transaction_date' => '2026-09-01',
    ]);

    $savings = moneyPulseFor($user)['metrics']['savings_consistency'];

    expect($savings['available'])->toBeFalse();
    expect($savings['detail']['eligible_periods'])->toBe(0);
});

test('budget discipline is high within limit and falls with overspend', function () {
    $user = User::factory()->create();
    fundUser($user, '5000.00');
    $food = Category::query()->where('name', 'Food')->where('is_default', true)->firstOrFail();

    Budget::factory()->for($user)->create([
        'category_id' => $food->id,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '80.00',
        'transaction_date' => '2026-09-05',
    ]);

    $within = moneyPulseFor($user)['metrics']['budget_discipline'];
    expect($within['available'])->toBeTrue();
    expect($within['score'])->toBe(100);
    expect($within['detail']['within_count'])->toBe(1);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '40.00',
        'transaction_date' => '2026-09-06',
    ]);
    // spent 120 / 100 = 20% over → adherence 80

    $over = moneyPulseFor($user)['metrics']['budget_discipline'];
    expect($over['score'])->toBe(80);
});

test('severe budget overspend approaches zero discipline', function () {
    $user = User::factory()->create();
    fundUser($user, '5000.00');
    $food = Category::query()->where('name', 'Food')->where('is_default', true)->firstOrFail();

    Budget::factory()->for($user)->create([
        'category_id' => $food->id,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food->id,
        'amount' => '250.00',
        'transaction_date' => '2026-09-05',
    ]);

    $discipline = moneyPulseFor($user)['metrics']['budget_discipline'];
    expect($discipline['score'])->toBe(0);
});

test('no budgets marks budget discipline unavailable', function () {
    $user = User::factory()->create();
    fundUser($user, '100.00');

    $discipline = moneyPulseFor($user)['metrics']['budget_discipline'];

    expect($discipline['available'])->toBeFalse();
    expect($discipline['summary'])->toBe('No budget data');
});

test('spending stability is high for steady expenses and lower when volatile', function () {
    $service = app(FinancialHealthService::class);

    $stable = $service->calculateSpendingStability([
        ['expense' => '100.00'],
        ['expense' => '100.00'],
        ['expense' => '100.00'],
    ]);
    expect($stable['available'])->toBeTrue();
    expect($stable['score'])->toBe(100);

    $volatile = $service->calculateSpendingStability([
        ['expense' => '10.00'],
        ['expense' => '200.00'],
        ['expense' => '10.00'],
    ]);
    expect($volatile['available'])->toBeTrue();
    expect($volatile['score'])->toBeLessThan($stable['score']);
});

test('spending stability unavailable for one period or zero spending', function () {
    $service = app(FinancialHealthService::class);

    expect($service->calculateSpendingStability([['expense' => '50.00']])['available'])->toBeFalse();
    expect($service->calculateSpendingStability([
        ['expense' => '0.00'],
        ['expense' => '0.00'],
    ])['available'])->toBeFalse();
});

test('goal progress uses profile savings goal and current month balance', function () {
    $user = User::factory()->create(['savings_goal' => '1000.00']);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '700.00',
        'transaction_date' => '2026-09-01',
    ]);

    $goal = moneyPulseFor($user)['metrics']['goal_progress'];

    expect($goal['available'])->toBeTrue();
    expect($goal['score'])->toBe(70);
    expect($goal['explanation'])->toContain('monthly balance');
    expect($goal['explanation'])->toContain('700.00');
    expect($goal['explanation'])->toContain('1000.00');
    expect($goal['explanation'])->not->toContain('of the way toward');
});

test('goal progress clamps at 100 and is unavailable without a goal', function () {
    $withGoal = User::factory()->create(['savings_goal' => '100.00']);
    Transaction::factory()->for($withGoal)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '500.00',
        'transaction_date' => '2026-09-01',
    ]);
    expect(moneyPulseFor($withGoal)['metrics']['goal_progress']['score'])->toBe(100);

    $noGoal = User::factory()->create(['savings_goal' => null]);
    $unavailable = moneyPulseFor($noGoal)['metrics']['goal_progress'];
    expect($unavailable['available'])->toBeFalse();
    expect($unavailable['summary'])->toBe('No active goals');
});

test('savings consistency wording reflects monthly balance semantics', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1000.00',
        'transaction_date' => '2026-08-01',
    ]);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $savings = moneyPulseFor($user)['metrics']['savings_consistency'];

    expect($savings['available'])->toBeTrue();
    expect($savings['score'])->toBe(100);
    expect($savings['explanation'])->toContain('income − expenses');
    expect($savings['formula'])->toContain('dashboard savings');
});

test('negative balance yields zero goal progress', function () {
    $user = User::factory()->create(['savings_goal' => '500.00']);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '50.00',
        'transaction_date' => '2026-09-01',
    ]);

    expect(moneyPulseFor($user)['metrics']['goal_progress']['score'])->toBe(0);
});

test('overall status thresholds and missing metric handling', function () {
    $service = app(FinancialHealthService::class);

    $healthy = $service->calculateOverallStatus([
        ['available' => true, 'score' => 90],
        ['available' => true, 'score' => 80],
        ['available' => false, 'score' => null],
    ], true);
    expect($healthy['status'])->toBe('healthy');
    expect($healthy['score'])->toBe(85);
    expect($healthy['metrics_used'])->toBe(2);

    $watchful = $service->calculateOverallStatus([
        ['available' => true, 'score' => 70],
        ['available' => true, 'score' => 60],
    ], true);
    expect($watchful['status'])->toBe('watchful');
    expect($watchful['score'])->toBe(65);

    $attention = $service->calculateOverallStatus([
        ['available' => true, 'score' => 40],
        ['available' => true, 'score' => 50],
    ], true);
    expect($attention['status'])->toBe('needs_attention');
    expect($attention['score'])->toBe(45);

    $missing = $service->calculateOverallStatus([
        ['available' => false, 'score' => null],
    ], false);
    expect($missing['status'])->toBe('insufficient');
    expect($missing['score'])->toBeNull();
});

test('spending trend improving stable and increasing', function () {
    $service = app(FinancialHealthService::class);

    $improving = $service->calculateSpendingTrend([
        ['label' => 'Aug 2026', 'expense' => '200.00'],
        ['label' => 'Sep 2026', 'expense' => '100.00'],
    ]);
    expect($improving['available'])->toBeTrue();
    expect($improving['label'])->toBe('Improving');

    $stable = $service->calculateSpendingTrend([
        ['label' => 'Aug 2026', 'expense' => '100.00'],
        ['label' => 'Sep 2026', 'expense' => '103.00'],
    ]);
    expect($stable['label'])->toBe('Stable');

    $increasing = $service->calculateSpendingTrend([
        ['label' => 'Aug 2026', 'expense' => '100.00'],
        ['label' => 'Sep 2026', 'expense' => '120.00'],
    ]);
    expect($increasing['label'])->toBe('Increasing');

    $insufficient = $service->calculateSpendingTrend([
        ['label' => 'Sep 2026', 'expense' => '50.00'],
    ]);
    expect($insufficient['available'])->toBeFalse();
});

test('money pulse is isolated between users', function () {
    $owner = User::factory()->create(['savings_goal' => '1000.00']);
    $other = User::factory()->create(['savings_goal' => '1000.00']);

    Transaction::factory()->for($owner)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '800.00',
        'transaction_date' => '2026-08-01',
    ]);
    Transaction::factory()->for($owner)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '800.00',
        'transaction_date' => '2026-09-01',
    ]);

    Transaction::factory()->for($other)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '999.00',
        'transaction_date' => '2026-09-01',
    ]);

    $ownerPulse = moneyPulseFor($owner);
    $otherPulse = moneyPulseFor($other);

    expect($ownerPulse['metrics']['goal_progress']['score'])->toBe(80);
    expect($otherPulse['metrics']['goal_progress']['score'])->toBe(0);
    expect($ownerPulse['metrics']['savings_consistency']['score'])->toBe(100);
});

test('money pulse never invents demo percentages for empty users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.money_pulse.overall.status', 'insufficient')
            ->where('dashboard.money_pulse.overall.score', null)
            ->where('dashboard.money_pulse.metrics.savings_consistency.score', null)
            ->where('dashboard.money_pulse.metrics.budget_discipline.score', null)
        );
});

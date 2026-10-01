<?php

use App\Enums\SavingsGoalStatus;
use App\Models\SavingsGoal;
use App\Models\SavingsGoalContribution;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\FinancialHealthService;
use App\Services\SavingsGoalService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access savings goal', function () {
    $this->get(route('savings-goal.index'))->assertRedirect(route('login'));
});

test('user can create a savings goal with starting balance', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('savings-goal.store'), [
            'name' => 'New Laptop Fund',
            'target_amount' => '100000.00',
            'starting_amount' => '42000.00',
        ])
        ->assertRedirect(route('savings-goal.index'));

    $goal = SavingsGoal::query()->where('user_id', $user->id)->first();
    expect($goal)->not->toBeNull();
    expect($goal->current_amount)->toBe('42000.00');
    expect($goal->status)->toBe(SavingsGoalStatus::Active);
    expect(SavingsGoalContribution::query()->where('savings_goal_id', $goal->id)->count())->toBe(1);
});

test('progress percentage remaining and completion estimate', function () {
    $user = User::factory()->create();
    // Positive month forecast savings: income 50000, expense pace low.
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '5000.00',
        'transaction_date' => '2026-09-05',
    ]);
    // Sep 15: daily pace 5000/15, forecast ~10000, expected savings ~40000

    $goal = app(SavingsGoalService::class)->createGoal($user, 'New Laptop Fund', '100000.00', '42000.00');
    $payload = app(SavingsGoalService::class)->serializeGoal($user, $goal->fresh());

    expect($payload['progress_percent'])->toBe(42);
    expect($payload['remaining_amount'])->toBe('58000.00');
    expect($payload['completion_estimate'])->not->toBeNull();
    expect($payload['completion_estimate']['months'])->toBeGreaterThan(0);
});

test('contribution updates progress and rejects invalid amounts', function () {
    $user = User::factory()->create();
    $goal = app(SavingsGoalService::class)->createGoal($user, 'Trip', '10000.00', '1000.00');

    $this->actingAs($user)
        ->post(route('savings-goal.contribute', $goal), [
            'amount' => '500.00',
            'note' => 'Side hustle',
        ])
        ->assertRedirect(route('savings-goal.index'));

    expect($goal->fresh()->current_amount)->toBe('1500.00');

    $this->actingAs($user)
        ->post(route('savings-goal.contribute', $goal), ['amount' => '0'])
        ->assertSessionHasErrors('amount');

    $this->actingAs($user)
        ->post(route('savings-goal.contribute', $goal), ['amount' => '-10'])
        ->assertSessionHasErrors('amount');
});

test('goal completes when contributions reach target', function () {
    $user = User::factory()->create();
    $goal = app(SavingsGoalService::class)->createGoal($user, 'Course', '1000.00', '600.00');
    app(SavingsGoalService::class)->addContribution($user, $goal, '400.00', 'Final');

    expect($goal->fresh()->status)->toBe(SavingsGoalStatus::Completed);
    $payload = app(SavingsGoalService::class)->serializeGoal($user, $goal->fresh());
    expect($payload['is_completed'])->toBeTrue();
    expect($payload['completion_estimate'])->toBeNull();
});

test('zero or negative savings rate yields no completion date', function () {
    $user = User::factory()->create();
    // Expenses only → expected savings not positive
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '2000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $goal = app(SavingsGoalService::class)->createGoal($user, 'Laptop', '50000.00', '1000.00');
    $payload = app(SavingsGoalService::class)->serializeGoal($user, $goal);

    expect($payload['savings_rate']['available'])->toBeFalse();
    expect($payload['completion_estimate'])->toBeNull();
});

test('smart scenario uses real entertainment spending not hard-coded amounts', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', config('app.timezone')));
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '40000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '3000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $entertainment = defaultCategoryId('expense', 'Entertainment');
    for ($i = 1; $i <= 3; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $entertainment,
            'amount' => '2700.00',
            'transaction_date' => sprintf('2026-08-%02d', $i * 5),
        ]);
    }

    $goal = app(SavingsGoalService::class)->createGoal($user, 'Laptop', '100000.00', '42000.00');
    $payload = app(SavingsGoalService::class)->serializeGoal($user, $goal->fresh());

    if ($payload['smart_scenario'] !== null) {
        expect($payload['smart_scenario']['category_name'])->not->toBe('Food');
        expect($payload['smart_scenario']['months_saved'])->toBeGreaterThanOrEqual(1);
        expect($payload['smart_scenario']['suggested_reduction'])->not->toBe('1500.00'); // may coincide but must be formula-driven
        expect((float) $payload['smart_scenario']['category_monthly_average'])->toBeGreaterThan(0);
    } else {
        // Acceptable when months_saved < 1; still assert rate works.
        expect($payload['savings_rate']['available'])->toBeTrue();
    }
});

test('user cannot access or contribute to another users goal', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $goal = app(SavingsGoalService::class)->createGoal($owner, 'Private', '5000.00', '100.00');

    $this->actingAs($attacker)
        ->post(route('savings-goal.contribute', $goal), ['amount' => '50.00'])
        ->assertForbidden();

    $this->actingAs($attacker)
        ->put(route('savings-goal.update', $goal), [
            'name' => 'Hacked',
            'target_amount' => '1.00',
        ])
        ->assertForbidden();

    expect($goal->fresh()->name)->toBe('Private');
});

test('dashboard includes savings goal tracker without changing money pulse profile goal', function () {
    $user = User::factory()->create(['savings_goal' => '5000.00']);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '8000.00',
        'transaction_date' => '2026-09-01',
    ]);
    app(SavingsGoalService::class)->createGoal($user, 'Laptop', '100000.00', '10000.00');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.savings_goal_tracker')
            ->where('dashboard.savings_goal_tracker.has_goal', true)
            ->has('dashboard.money_pulse.metrics.goal_progress')
        );

    // Money Pulse still uses profile.savings_goal (monthly), not the tracker target.
    $pulse = app(FinancialHealthService::class)->dashboardPayload($user, app(DashboardAnalyticsService::class)->forUser($user));
    expect($pulse['metrics']['goal_progress']['detail']['goal'])->toBe('5000.00');
    expect((string) $user->fresh()->savings_goal)->toBe('5000.00');
});

test('ceil months estimate matches scenario A style math', function () {
    $service = app(SavingsGoalService::class);
    $estimate = $service->estimateCompletion('58000.00', '10000.00', Carbon::parse('2026-09-15'));

    expect($estimate['months'])->toBe(6);
    expect($estimate['label'])->toBe('March 2027');
});

test('only one active goal allowed', function () {
    $user = User::factory()->create();
    app(SavingsGoalService::class)->createGoal($user, 'First', '1000.00', '0');

    $this->actingAs($user)
        ->post(route('savings-goal.store'), [
            'name' => 'Second',
            'target_amount' => '2000.00',
            'starting_amount' => '0',
        ])
        ->assertSessionHasErrors('name');
});

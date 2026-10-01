<?php

use App\Contracts\AiAssistantProvider;
use App\Enums\BudgetAlertType;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\Ai\Providers\FakeAssistantProvider;
use App\Services\Ai\Providers\NullAssistantProvider;
use App\Services\Intelligence\ExpenseAlertBridge;
use App\Support\MoneyMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    config([
        'ai.enabled' => true,
        'ai.provider' => 'openai',
        'ai.gemini.api_key' => null,
        'ai.gemini.model' => 'gemini-3.5-flash',
        'intelligence.large_min_observations' => 5,
        'intelligence.large_multiplier' => 2.5,
        'intelligence.large_absolute_floor' => 500,
        'intelligence.large_lookback_days' => 90,
    ]);
    app()->instance(AiAssistantProvider::class, new NullAssistantProvider);
});

test('income create succeeds and creates one plan notification when gemini unavailable', function () {
    $user = User::factory()->create(['savings_goal' => 2000]);
    $categoryId = defaultCategoryId('income', 'Allowance');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => $categoryId,
            'amount' => '5000.00',
            'description' => 'Allowance',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::query()->where('user_id', $user->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->type)->toBe(TransactionType::Income);

    $notifications = $user->fresh()->notifications;
    expect($notifications)->toHaveCount(1);
    expect($notifications->first()->data['type'])->toBe('income_plan');
    expect($notifications->first()->data['transaction_id'])->toBe($transaction->id);
    expect($notifications->first()->data['action_url'])->toBe('/intelligence?plan='.$transaction->id);
    expect($notifications->first()->data['message'])->not->toContain('temporarily unavailable');
});

test('income plan total equals new income and uses user context', function () {
    $user = User::factory()->create(['savings_goal' => 3000]);
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => $food,
        'amount' => 2000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Prior meals',
    ]);
    // Seed income so context is not empty / insufficient for history path after new income.
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => 20000,
        'transaction_date' => now()->toDateString(),
    ]);

    $income = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Bonus',
    ]);

    app(IncomePlanAlertService::class)->handle($user, $income);

    $plan = app(IncomePlanAlertService::class)->planFor($user, $income->id);
    expect($plan)->not->toBeNull();
    expect($plan['total'])->toBe('5000.00');

    $sum = '0.00';
    foreach ($plan['plan'] as $row) {
        expect(MoneyMath::compare($row['amount'], '0.00'))->toBe(1);
        $sum = MoneyMath::add($sum, $row['amount']);
    }
    expect($sum)->toBe('5000.00');
});

test('duplicate income plan notification is prevented', function () {
    $user = User::factory()->create();
    $income = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => 4000,
        'transaction_date' => now()->toDateString(),
    ]);

    $service = app(IncomePlanAlertService::class);
    $service->handle($user, $income);
    $service->handle($user, $income);

    expect($user->fresh()->notifications()->count())->toBe(1);
});

test('income create still succeeds when gemini throws during narration', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    config([
        'ai.gemini.api_key' => 'test-key',
        'ai.gemini.model' => 'gemini-3.5-flash',
        'ai.gemini.api_version' => 'v1beta',
    ]);
    app()->forgetInstance(AiAssistantProvider::class);
    // Bind a fake that is available but throws when answer() is called.
    $fake = new FakeAssistantProvider;
    $fake->available = true;
    $fake->throwOnAnswer = new RuntimeException('Gemini down');
    app()->instance(AiAssistantProvider::class, $fake);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '2500.00',
            'description' => 'Gift',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($user->fresh()->notifications()->count())->toBe(1);
    expect($user->fresh()->notifications->first()->data['type'])->toBe('income_plan');
});

test('income plan is user isolated and intelligence plan query respects ownership', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $incomeA = Transaction::factory()->for($userA)->create([
        'type' => TransactionType::Income,
        'amount' => 5000,
        'transaction_date' => now()->toDateString(),
    ]);
    $incomeB = Transaction::factory()->for($userB)->create([
        'type' => TransactionType::Income,
        'amount' => 50000,
        'transaction_date' => now()->toDateString(),
    ]);

    app(IncomePlanAlertService::class)->handle($userA, $incomeA);
    app(IncomePlanAlertService::class)->handle($userB, $incomeB);

    expect($userA->fresh()->notifications)->toHaveCount(1);
    expect($userB->fresh()->notifications)->toHaveCount(1);
    expect((float) $userA->notifications->first()->data['amount'])->toBe(5000.0);
    expect((float) $userB->notifications->first()->data['amount'])->toBe(50000.0);

    $this->actingAs($userA)
        ->get(route('intelligence.index', ['plan' => $incomeB->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suggested_plan', null));

    $this->actingAs($userA)
        ->get(route('intelligence.index', ['plan' => $incomeA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('suggested_plan.transaction_id', $incomeA->id)
            ->where('suggested_plan.total', '5000.00'));
});

test('normal expense does not create unnecessary ai notifications', function () {
    $user = User::factory()->create();
    fundUser($user);

    $before = $user->notifications()->count();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '25.00',
            'description' => 'Snack',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    $types = $user->fresh()->notifications->pluck('data.type')->all();
    expect($types)->not->toContain('unusual_expense');
    expect($types)->not->toContain('income_plan');
    expect($types)->not->toContain('category_pace');
    expect($user->fresh()->notifications()->count())->toBe($before);
});

test('expense near budget limit creates budget notification via store', function () {
    $user = User::factory()->create();
    fundUser($user, '10000.00');
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '1000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '700.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '150.00',
            'description' => 'Push near limit',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    expect(BudgetAlert::query()->where('alert_type', BudgetAlertType::NearLimit->value)->count())->toBe(1);
    expect($user->fresh()->notifications->pluck('data.type')->all())->toContain('budget_near_limit');
});

test('unusual large expense creates database notification once', function () {
    $user = User::factory()->create();
    fundUser($user, '100000.00');
    $food = defaultCategoryId('expense', 'Food');

    for ($i = 0; $i < 5; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $food,
            'amount' => '100.00',
            'transaction_date' => now()->subDays(10 + $i)->toDateString(),
            'description' => 'Baseline meal '.$i,
        ]);
    }

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2500.00',
            'description' => 'Large feast',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('transactions.index'));

    $unusual = $user->fresh()->notifications->where(fn ($n) => ($n->data['type'] ?? null) === 'unusual_expense');
    expect($unusual)->toHaveCount(1);

    $txn = Transaction::query()->where('user_id', $user->id)->where('amount', '2500.00')->first();
    app(ExpenseAlertBridge::class)->handle(
        $user,
        $txn,
        ['signal_type' => 'large_transaction', 'id' => 1, 'reason' => 'dup', 'explanation' => ['amount' => '2500.00', 'category_name' => 'Food']],
    );

    expect($user->fresh()->notifications->where(fn ($n) => ($n->data['type'] ?? null) === 'unusual_expense'))->toHaveCount(1);
});

test('income plan does not mutate budgets or recurring', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '2000.00',
    ]);

    $budgetCount = Budget::query()->count();
    $limit = (string) $budget->limit_amount;

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '8000.00',
            'description' => 'Pay',
            'transaction_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    expect(Budget::query()->count())->toBe($budgetCount);
    expect((string) $budget->fresh()->limit_amount)->toBe($limit);
    expect(RecurringTransaction::query()->count())->toBe(0);
});

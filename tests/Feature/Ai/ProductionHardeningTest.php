<?php

use App\Contracts\AiAssistantProvider;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\FinancialAssistantService;
use App\Services\Ai\FinancialContextBuilder;
use App\Services\Ai\IncomeAllocationPlanner;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\Ai\Providers\NullAssistantProvider;
use App\Support\MoneyMath;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    config([
        'ai.enabled' => true,
        'ai.provider' => 'openai',
        'ai.gemini.api_key' => null,
        'ai.gemini.model' => 'gemini-3.5-flash',
    ]);
    app()->instance(AiAssistantProvider::class, new NullAssistantProvider);
});

test('food allocation is capped to observed spend not raw historical share', function () {
    $user = User::factory()->create(['savings_goal' => 0]);
    fundUser($user, '10000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '8000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => '2000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $context = app(FinancialContextBuilder::class)->forUser($user);
    $plan = app(IncomeAllocationPlanner::class)->plan($user, '30000.00', $context);

    expect($plan['total'])->toBe('30000.00');
    $sum = collect($plan['plan'])->reduce(fn ($c, $r) => MoneyMath::add($c, $r['amount']), '0.00');
    expect($sum)->toBe('30000.00');

    $food = collect($plan['plan'])->firstWhere('category', 'Food');
    $buffer = collect($plan['plan'])->firstWhere('category', 'Buffer');
    $savings = collect($plan['plan'])->firstWhere('category', 'Savings');

    expect($food)->not->toBeNull();
    // Need-capped to observed Food spend (8000), not ~21k proportional share.
    expect(MoneyMath::compare($food['amount'], '8000.00'))->toBe(0);
    expect($buffer)->not->toBeNull();
    expect(MoneyMath::compare($buffer['amount'], '1000.00'))->toBe(1);
    expect($savings)->not->toBeNull();
    expect(collect($plan['plan'])->pluck('category'))->not->toContain('Uncategorized');
});

test('recurring commitments are reserved before categories', function () {
    $user = User::factory()->create(['savings_goal' => 2000]);
    fundUser($user, '20000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '3000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    RecurringTransaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => defaultCategoryId('expense', 'Hostel/Rent'),
        'amount' => '5000.00',
        'frequency' => RecurringFrequency::Monthly,
        'start_date' => now()->subMonth()->toDateString(),
        'next_run_date' => now()->addDays(3)->toDateString(),
        'is_active' => true,
        'description' => 'Hostel rent',
    ]);

    $context = app(FinancialContextBuilder::class)->forUser($user);
    // Ensure upcoming recurring appears in context for the planner.
    if (($context['recurring']['upcoming'] ?? []) === []) {
        $context['recurring']['upcoming'] = [[
            'type' => TransactionType::Expense->value,
            'amount' => '5000.00',
            'description' => 'Hostel rent',
            'category' => 'Hostel/Rent',
            'next_run_date' => now()->addDays(3)->toDateString(),
        ]];
        $context['recurring']['monthly_committed_expense'] = '5000.00';
    }

    $plan = app(IncomeAllocationPlanner::class)->plan($user, '30000.00', $context);
    $sum = collect($plan['plan'])->reduce(fn ($c, $r) => MoneyMath::add($c, $r['amount']), '0.00');
    expect($sum)->toBe('30000.00');

    $recurring = collect($plan['plan'])->firstWhere('category', 'Recurring commitments');
    expect($recurring)->not->toBeNull();
    expect(MoneyMath::compare($recurring['amount'], '0.00'))->toBe(1);
    expect($plan['basis'])->toContain('recurring commitments');
});

test('savings goal increases savings line versus no goal', function () {
    $base = User::factory()->create(['savings_goal' => 0]);
    $withGoal = User::factory()->create(['savings_goal' => 10000]);
    foreach ([$base, $withGoal] as $user) {
        fundUser($user, '20000.00');
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '4000.00',
            'transaction_date' => now()->toDateString(),
        ]);
    }

    $planNone = app(IncomeAllocationPlanner::class)->plan(
        $base,
        '20000.00',
        app(FinancialContextBuilder::class)->forUser($base),
    );
    $planGoal = app(IncomeAllocationPlanner::class)->plan(
        $withGoal,
        '20000.00',
        app(FinancialContextBuilder::class)->forUser($withGoal),
    );

    $sNone = (float) (collect($planNone['plan'])->firstWhere('category', 'Savings')['amount'] ?? 0);
    $sGoal = (float) (collect($planGoal['plan'])->firstWhere('category', 'Savings')['amount'] ?? 0);
    expect($sGoal)->toBeGreaterThan($sNone);
});

test('budget remaining caps category allocation', function () {
    $user = User::factory()->create(['savings_goal' => 0]);
    fundUser($user, '40000.00');
    $food = defaultCategoryId('expense', 'Food');
    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '7000.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $plan = app(IncomeAllocationPlanner::class)->plan(
        $user,
        '30000.00',
        app(FinancialContextBuilder::class)->forUser($user),
    );

    $foodRow = collect($plan['plan'])->firstWhere('category', 'Food');
    expect($foodRow)->not->toBeNull();
    // Remaining budget room = 5000.
    expect(MoneyMath::compare($foodRow['amount'], '5000.00'))->toBeLessThanOrEqual(0);
});

test('hardening natural language families resolve without exact strings', function () {
    $user = User::factory()->create(['savings_goal' => 5000]);
    fundUser($user, '40000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '5000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    $svc = app(FinancialAssistantService::class);

    expect($svc->ask($user, 'Where am I losing the most money?')['intent'])->toBe('spending');
    expect($svc->ask($user, 'Where should I cut down?')['intent'])->toBe('save_more');
    expect($svc->ask($user, 'What should I change first?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'Am I spending too much anywhere?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'What should I protect first?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'Is anything putting pressure on my money?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'What needs attention?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'Where should I be more careful?')['intent'])->toBe('coach_priority');
    expect($svc->ask($user, 'How much should I keep aside?')['intent'])->toBe('coach_priority');
});

test('hardening social oos uses exact existing reply', function (string $msg) {
    $r = app(FinancialAssistantService::class)->ask(User::factory()->create(), $msg);
    expect($r['in_scope'])->toBeFalse()
        ->and($r['answer'])->toBe(
            'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.',
        )
        ->and($r['answer'])->not->toContain('Food')
        ->and($r['facts_used'])->toBeEmpty();
})->with([
    'Hi',
    'How are you?',
    "What's up?",
    'Tell me a joke',
    'Tell me a story',
    'Write an email',
    "What's the weather today?",
    'Who is Elon Musk?',
    'Explain quantum physics',
]);

test('follow up and transport context uses live data', function () {
    $user = User::factory()->create(['savings_goal' => 3000]);
    fundUser($user, '40000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '6000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => '1500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $svc = app(FinancialAssistantService::class);
    $first = $svc->ask($user, 'What should I focus on?');
    $hist = [
        ['role' => 'user', 'content' => 'What should I focus on?'],
        ['role' => 'assistant', 'content' => $first['answer']],
    ];
    expect($svc->ask($user, 'Why?', $hist)['answer'])->toContain('Food');
    expect($svc->ask($user, 'How much?', $hist)['answer'])->toMatch('/Rs/');
    expect($svc->ask($user, 'What about Food?', $hist)['answer'])->toContain('Food');
    expect($svc->ask($user, 'And Transport?', $hist)['answer'])->toContain('Transport');
});

test('income plan ownership and single notification', function () {
    $owner = User::factory()->create(['savings_goal' => 4000]);
    $other = User::factory()->create();
    fundUser($owner, '10000.00');
    $income = Transaction::factory()->for($owner)->create([
        'type' => TransactionType::Income,
        'amount' => '30000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $svc = app(IncomePlanAlertService::class);
    $svc->handle($owner, $income);
    $svc->handle($owner, $income);

    expect($svc->planFor($owner, $income->id)['total'])->toBe('30000.00');
    expect($svc->planFor($other, $income->id))->toBeNull();
    expect($owner->fresh()->notifications()->where('data->transaction_id', $income->id)->count())->toBe(1);

    $this->actingAs($other)
        ->get('/intelligence?plan='.$income->id)
        ->assertOk(); // page loads but suggested_plan should be null for other user
});

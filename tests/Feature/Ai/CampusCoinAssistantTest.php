<?php

use App\Contracts\AiAssistantProvider;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\Providers\FakeAssistantProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    config([
        'ai.enabled' => true,
        'ai.provider' => 'gemini',
        'ai.gemini.api_key' => 'test-key',
        'ai.gemini.model' => 'gemini-3.5-flash',
        'ai.assistant_rate_limit_per_minute' => 20,
        'ai.assistant_max_message_length' => 500,
    ]);
});

function bindAssistantFake(?FakeAssistantProvider $fake = null): FakeAssistantProvider
{
    $fake ??= new FakeAssistantProvider;
    $fake->nextAnswer ??= [
        'intent' => 'general',
        'in_scope' => true,
        'answer' => 'Your spending looks organized.',
        'facts_used' => ['current-month spending'],
        'needs_clarification' => false,
        'next_action' => null,
        'clarifying_question' => null,
        'disclaimer' => null,
    ];
    app()->instance(AiAssistantProvider::class, $fake);

    return $fake;
}

test('intelligence page includes campus coin ai meta', function () {
    $user = User::factory()->create();
    bindAssistantFake();

    $this->actingAs($user)
        ->get(route('intelligence.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intelligence/index')
            ->has('assistant')
            ->has('assistant.suggested_prompts')
            ->where('assistant.available', true));
});

test('guests cannot ask campus coin ai', function () {
    $this->postJson(route('intelligence.assistant.ask'), [
        'message' => 'Where did my money go?',
    ])->assertUnauthorized();
});

test('authenticated user can ask campus coin ai', function () {
    $user = User::factory()->create();
    $fake = bindAssistantFake();
    fundUser($user, '20000.00');
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => 1200,
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Where did my money go?',
        ])
        ->assertOk()
        ->assertJsonPath('response.intent', 'spending')
        ->assertJsonPath('ok', true);

    expect((string) $response->json('response.answer'))->toContain('Food');
    // Spending intent is answered deterministically from Campus Coin data.
    expect($fake->calls)->toBe(0);
});

test('user isolation for assistant financial context', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Transaction::factory()->for($a)->create([
        'type' => TransactionType::Income,
        'amount' => 30000,
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($b)->create([
        'type' => TransactionType::Income,
        'amount' => 90000,
        'transaction_date' => now()->toDateString(),
    ]);

    $fake = bindAssistantFake();

    $this->actingAs($a)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'How much income do I have this month?',
        ])
        ->assertOk();

    $income = data_get($fake->lastPayload, 'financial_context.current_period.income');
    expect($income)->toBe('30000.00');
    expect(json_encode($fake->lastPayload))->not->toContain('90000');
});

test('out of scope joke is refused without provider call', function () {
    $user = User::factory()->create();
    $fake = bindAssistantFake();

    $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Tell me a joke.',
        ])
        ->assertOk()
        ->assertJsonPath('response.in_scope', false)
        ->assertJsonPath(
            'response.answer',
            'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.',
        );

    expect($fake->calls)->toBe(0);
});

test('mutation request is refused with security protocol wording', function () {
    $user = User::factory()->create();
    $fake = bindAssistantFake();

    $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Please delete my Food transaction.',
        ])
        ->assertOk()
        ->assertJsonPath('response.intent', 'mutation_refused')
        ->assertJsonPath(
            'response.answer',
            'As a security protocol, Campus Coin AI cannot modify your data or transactions directly from the chat.',
        )
        ->assertJsonPath('response.next_action', 'Use the Transactions, Budgets, Recurring, or Settings pages to make changes manually.');

    expect($fake->calls)->toBe(0);
});

test('why follow-up after spending ask resolves with category facts', function () {
    $user = User::factory()->create();
    bindAssistantFake();
    fundUser($user, '20000.00');
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Expense,
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => 3200,
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Why?',
            'history' => [
                ['role' => 'user', 'content' => 'Where did my money go?'],
                ['role' => 'assistant', 'content' => 'This month you spent on Food.'],
            ],
        ])
        ->assertOk()
        ->json('response');

    expect($response['intent'])->toBe('spending_why');
    expect($response['answer'])->toContain('Food');
    expect($response['answer'])->toContain('3,200');
    expect($response['next_action'])->not->toBeEmpty();
    expect($response['facts_used'])->not->toBeEmpty();
});

test('history cannot leak another users amounts', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    bindAssistantFake();
    fundUser($a, '20000.00');
    fundUser($b, '90000.00');

    Transaction::factory()->for($a)->create([
        'type' => TransactionType::Expense,
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => 1100,
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($b)->create([
        'type' => TransactionType::Expense,
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => 77000,
        'transaction_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($a)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Why?',
            'history' => [
                ['role' => 'user', 'content' => 'Where did my money go?'],
                ['role' => 'assistant', 'content' => 'Prior answer mentioning 77000 would be ignored for math.'],
            ],
        ])
        ->assertOk()
        ->json('response');

    expect($response['answer'])->toContain('Food');
    expect($response['answer'])->not->toContain('77,000');
    expect($response['answer'])->not->toContain('Transport');
});

test('allocation ask returns suggested plan totaling incoming amount', function () {
    $user = User::factory()->create(['savings_goal' => 4000]);
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => 25000,
        'transaction_date' => now()->toDateString(),
    ]);

    $fake = bindAssistantFake();
    $fake->nextAnswer = [
        'intent' => 'allocation',
        'in_scope' => true,
        'answer' => 'Here is a suggested plan for your income.',
        'facts_used' => ['savings goal'],
        'needs_clarification' => false,
        'next_action' => 'Review the plan.',
        'clarifying_question' => null,
        'disclaimer' => null,
    ];

    $response = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'I received Rs 30,000. How should I plan it?',
        ])
        ->assertOk()
        ->json('response');

    expect($response['allocation_plan'])->not->toBeEmpty();
    expect($response['allocation_total'])->toBe('30000.00');

    $sum = collect($response['allocation_plan'])->sum(fn ($row) => (float) $row['amount']);
    expect($sum)->toEqualWithDelta(30000.0, 0.01);
    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('balance aware spend question does not create a transaction', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => 3800,
        'transaction_date' => now()->toDateString(),
    ]);
    bindAssistantFake();

    $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Can I spend Rs 5,000?',
        ])
        ->assertOk()
        ->assertJsonPath('response.intent', 'balance_check');

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('xss payload in message is not echoed as html', function () {
    $user = User::factory()->create();
    $fake = bindAssistantFake();
    $fake->nextAnswer = [
        'intent' => 'general',
        'in_scope' => true,
        'answer' => 'Safe answer',
        'facts_used' => [],
        'needs_clarification' => false,
    ];

    $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => '<script>alert(1)</script> Where did my money go?',
        ])
        ->assertOk()
        ->assertJsonMissing(['answer' => '<script>alert(1)</script>']);
});

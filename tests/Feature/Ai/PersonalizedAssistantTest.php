<?php

use App\Contracts\AiAssistantProvider;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\FinancialAssistantService;
use App\Services\Ai\FinancialContextBuilder;
use App\Services\Ai\Providers\FakeAssistantProvider;
use App\Services\Ai\Providers\NullAssistantProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    config([
        'ai.enabled' => true,
        'ai.provider' => 'openai',
        'ai.gemini.api_key' => null,
    ]);
    app()->instance(AiAssistantProvider::class, new NullAssistantProvider);
});

test('assistant context preserves real category names and does not invent uncategorized', function () {
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');
    $entertainment = defaultCategoryId('expense', 'Entertainment');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '3000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $entertainment,
        'amount' => '1500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $context = app(FinancialContextBuilder::class)->forUser($user);
    $names = collect($context['categories'])->pluck('name')->all();

    expect($names)->toContain('Food');
    expect($names)->toContain('Transport');
    expect($names)->toContain('Entertainment');
    expect($context['current_period']['top_named_category'])->toBe('Food');
    expect($context['current_period']['top_category'])->toBe('Food');
    expect($context['current_period']['has_uncategorized'])->toBeFalse();
    expect(collect($context['categories'])->firstWhere('name', 'Food')['category_id'])->toBe($food);
});

test('uncategorized appears only when null category_id expenses exist', function () {
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'amount' => '5000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $context = app(FinancialContextBuilder::class)->forUser($user);

    expect($context['current_period']['has_uncategorized'])->toBeTrue();
    expect($context['current_period']['top_category'])->toBe('Uncategorized');
    expect($context['current_period']['top_named_category'])->toBe('Food');
    expect(collect($context['categories'])->pluck('name')->all())->toContain('Food');
});

test('how can i save more uses named categories and budgets when gemini is down', function () {
    $user = User::factory()->create(['savings_goal' => 10000]);
    fundUser($user, '80000.00');
    $food = defaultCategoryId('expense', 'Food');
    $entertainment = defaultCategoryId('expense', 'Entertainment');

    Budget::factory()->for($user)->create([
        'category_id' => $entertainment,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '2000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $entertainment,
        'amount' => '1800.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $result = app(FinancialAssistantService::class)->ask($user, 'How can I save more money?');

    expect($result['intent'])->toBe('save_more');
    expect($result['error'])->toBeNull();
    expect($result['answer'])->toContain('Food');
    expect($result['answer'])->not->toMatch('/^Track your expenses/i');
    expect($result['answer'])->not->toContain('BASED ON');
    expect($result['answer'])->toContain('8,000');
    expect($result['facts_used'])->not->toBeEmpty();
    expect($result['next_action'])->not->toBeEmpty();
});

test('biggest expense question prefers named category Food', function () {
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '4500.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '1200.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $result = app(FinancialAssistantService::class)->ask($user, 'Which category is my biggest expense?');

    expect($result['intent'])->toBe('spending');
    expect($result['answer'])->toContain('Food');
    expect($result['answer'])->not->toContain('Uncategorized is currently your largest');
    expect($result['next_action'])->not->toBeEmpty();
});

test('off topic poem is refused without gemini', function () {
    $result = app(FinancialAssistantService::class)->ask(User::factory()->create(), 'Write me a poem about the moon.');

    expect($result['in_scope'])->toBeFalse();
    expect($result['intent'])->toBe('out_of_scope');
    expect($result['answer'])->toBe(
        'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.',
    );
});

test('uncategorized mentioned separately when named category also exists', function () {
    $user = User::factory()->create();
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '4000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'amount' => '1500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $result = app(FinancialAssistantService::class)->ask($user, 'Where did my money go?');

    expect($result['answer'])->toContain('Food');
    expect($result['answer'])->toContain('uncategorized');
    expect($result['answer'])->not->toMatch('/Uncategorized is your largest named/i');
});

test('why follow up uses prior spending intent and real data', function () {
    $user = User::factory()->create();
    fundUser($user, '40000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '2500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $result = app(FinancialAssistantService::class)->ask($user, 'Why?', [
        ['role' => 'user', 'content' => 'Where did my money go?'],
        ['role' => 'assistant', 'content' => 'You spent on Food.'],
    ]);

    expect($result['intent'])->toBe('spending_why');
    expect($result['answer'])->toContain('Food');
    expect($result['facts_used'])->not->toBeEmpty();
    expect($result['next_action'])->not->toBeEmpty();
});

test('feature help explains budgets from product behavior', function () {
    $result = app(FinancialAssistantService::class)->ask(User::factory()->create(), 'How does my budget work?');

    expect($result['intent'])->toBe('feature_budget');
    expect($result['answer'])->toContain('monthly spending limit');
    expect($result['answer'])->toContain('over budget');
});

test('income plan still totals incoming amount with category history', function () {
    $user = User::factory()->create(['savings_goal' => 2000]);
    fundUser($user, '10000.00');
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $result = app(FinancialAssistantService::class)->ask($user, 'I received Rs 5,000. How should I plan it?');

    expect($result['intent'])->toBe('allocation');
    expect($result['allocation_total'])->toBe('5000.00');
    expect(collect($result['allocation_plan'])->sum(fn ($r) => (float) $r['amount']))->toEqualWithDelta(5000.0, 0.01);
});

test('assistant remains user isolated for save advice', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    fundUser($userA, '50000.00');
    fundUser($userB, '50000.00');

    Transaction::factory()->for($userA)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '7000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($userB)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => '9000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $answerA = app(FinancialAssistantService::class)->ask($userA, 'How can I save more money?');
    $answerB = app(FinancialAssistantService::class)->ask($userB, 'How can I save more money?');

    expect($answerA['answer'])->toContain('Food');
    expect($answerA['answer'])->not->toContain('Transport');
    expect($answerB['answer'])->toContain('Transport');
    expect($answerB['answer'])->not->toContain('Food');
});

test('gemini narration can still run for open questions when provider works', function () {
    $user = User::factory()->create();
    fundUser($user, '20000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $fake = new FakeAssistantProvider;
    $fake->nextAnswer = [
        'intent' => 'general',
        'in_scope' => true,
        'answer' => 'Your Food spending is organized this month.',
        'facts_used' => ['Food'],
        'needs_clarification' => false,
    ];
    app()->instance(AiAssistantProvider::class, $fake);

    $result = app(FinancialAssistantService::class)->ask($user, 'Give me a general overview of my Campus Coin finances.');

    expect($fake->calls)->toBe(1);
    expect($result['answer'])->toContain('Food spending');
    expect($result['next_action'])->not->toBeEmpty();
    expect($fake->lastPayload['financial_context']['current_period']['top_named_category'] ?? null)->toBe('Food');
});

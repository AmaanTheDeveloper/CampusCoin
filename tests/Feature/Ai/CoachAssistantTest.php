<?php

use App\Contracts\AiAssistantProvider;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\CoachRecommendationEngine;
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

function seedCoachUser(): User
{
    $user = User::factory()->create(['savings_goal' => 10000]);
    fundUser($user, '80000.00');
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '2000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    return $user;
}

test('coach paraphrases share coach_priority intent and food focus', function () {
    $user = seedCoachUser();
    $service = app(FinancialAssistantService::class);

    $a = $service->ask($user, 'What can I do?');
    $b = $service->ask($user, 'What should I focus on?');
    $c = $service->ask($user, 'Where should I start?');

    expect($a['intent'])->toBe('coach_priority')
        ->and($b['intent'])->toBe('coach_priority')
        ->and($c['intent'])->toBe('coach_priority')
        ->and($a['answer'])->toContain('Food')
        ->and($b['answer'])->toContain('Food')
        ->and($c['answer'])->toContain('Food')
        ->and($a['next_action'])->not->toBeEmpty()
        ->and($a['answer'])->not->toMatch('/^This month you/i');
});

test('save paraphrase cut back maps to save_more', function () {
    $user = seedCoachUser();
    $result = app(FinancialAssistantService::class)->ask($user, 'Where can I cut back?');

    expect($result['intent'])->toBe('save_more')
        ->and($result['answer'])->toContain('Food')
        ->and($result['next_action'])->not->toBeEmpty();
});

test('social greetings use exact out of scope reply', function (string $message) {
    $result = app(FinancialAssistantService::class)->ask(User::factory()->create(), $message);

    expect($result['in_scope'])->toBeFalse()
        ->and($result['answer'])->toBe(
            'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.',
        );
})->with([
    'How are you?',
    'Hello',
    'Tell me a joke.',
    'What is the weather?',
    'Write me a poem about the moon.',
    'Tell me a story',
]);

test('coach follow up why and how much stay on recommendation', function () {
    $user = seedCoachUser();
    $service = app(FinancialAssistantService::class);

    $first = $service->ask($user, 'What can I do?');
    expect($first['intent'])->toBe('coach_priority');

    $why = $service->ask($user, 'Why?', [
        ['role' => 'user', 'content' => 'What can I do?'],
        ['role' => 'assistant', 'content' => $first['answer']],
    ]);
    expect($why['intent'])->toBe('spending_why')
        ->and($why['answer'])->toContain('Food')
        ->and($why['facts_used'])->not->toBeEmpty();

    $howMuch = $service->ask($user, 'How much?', [
        ['role' => 'user', 'content' => 'What can I do?'],
        ['role' => 'assistant', 'content' => $first['answer']],
    ]);
    expect($howMuch['intent'])->toBe('coach_how_much')
        ->and($howMuch['answer'])->toContain('Food')
        ->and($howMuch['answer'])->toMatch('/Rs/');

    $food = $service->ask($user, 'What about Food?', [
        ['role' => 'user', 'content' => 'What can I do?'],
        ['role' => 'assistant', 'content' => $first['answer']],
    ]);
    expect($food['intent'])->toBe('category_focus')
        ->and($food['answer'])->toContain('Food');

    $more = $service->ask($user, 'Tell me more', [
        ['role' => 'user', 'content' => 'What can I do?'],
        ['role' => 'assistant', 'content' => $first['answer']],
    ]);
    expect($more['facts_used'])->not->toBeEmpty()
        ->and($more['next_action'])->not->toBeEmpty();
});

test('uncategorized is never primary coach recommendation when named categories exist', function () {
    $user = User::factory()->create(['savings_goal' => 5000]);
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '3500.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'amount' => '6100.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $context = app(FinancialContextBuilder::class)->forUser($user);
    $rec = app(CoachRecommendationEngine::class)->recommend($context);

    expect($rec['primary_category'])->toBe('Food')
        ->and($rec['answer'])->toContain('Food')
        ->and($rec['answer'])->toContain('uncategorized')
        ->and($rec['primary_category'])->not->toBe('Uncategorized');

    $result = app(FinancialAssistantService::class)->ask($user, 'What should I focus on?');
    expect($result['answer'])->toContain('Food')
        ->and($result['answer'])->not->toMatch('/Uncategorized is your (biggest|strongest|largest named)/i');
});

test('gemini failure falls back to coach not spending dump', function () {
    $user = seedCoachUser();
    $fake = new FakeAssistantProvider;
    $fake->available = true;
    $fake->throwOnAnswer = new RuntimeException('simulated gemini failure');
    app()->instance(AiAssistantProvider::class, $fake);

    $result = app(FinancialAssistantService::class)->ask($user, 'Give me advice about my money situation this month.');

    expect($fake->calls)->toBeGreaterThan(0)
        ->and($result['intent'])->toBe('coach_priority')
        ->and($result['answer'])->toContain('Food')
        ->and($result['answer'])->not->toMatch('/^This month you\'ve spent/i');
});

test('budget pressure elevates coach priority to P1', function () {
    $user = User::factory()->create(['savings_goal' => 5000]);
    fundUser($user, '50000.00');
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '2000.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $rec = app(CoachRecommendationEngine::class)->recommend(
        app(FinancialContextBuilder::class)->forUser($user)
    );

    expect($rec['priority'])->toBe('P1')
        ->and($rec['primary_category'])->toBe('Food')
        ->and($rec['answer'])->toContain('budget');
});

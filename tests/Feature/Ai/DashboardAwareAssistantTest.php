<?php

use App\Contracts\AiAssistantProvider;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\AssistantDomainResolver;
use App\Services\Ai\CampusCoinDashboardKnowledge;
use App\Services\Ai\FinancialAssistantService;
use App\Services\Ai\Providers\FakeAssistantProvider;
use App\Services\Ai\Providers\NullAssistantProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

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

function seedDashboardAwareUser(): User
{
    $user = User::factory()->create(['savings_goal' => 10000]);
    fundUser($user, '80000.00');
    $food = defaultCategoryId('expense', 'Food');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    return $user;
}

test('domain paraphrases resolve to expected domains', function (string $message, string $domain) {
    $resolved = app(AssistantDomainResolver::class)->resolve($message);

    expect($resolved['domain'])->toBe($domain);
})->with([
    ['What is my available balance?', 'balance'],
    ['Where can I see my transactions?', 'navigation'],
    ['Which budget needs attention?', 'budgets'],
    ['What should I focus on?', 'coach'],
    ['How was my month?', 'monthly_review'],
]);

test('every knowledge path matches a registered student route name', function () {
    $knowledge = app(CampusCoinDashboardKnowledge::class);

    foreach ($knowledge->all() as $page) {
        expect(Route::has($page['route_name']))->toBeTrue("Missing route {$page['route_name']}")
            ->and($knowledge->link($page['id']))->not->toBeNull()
            ->and($knowledge->link($page['id'])['href'])->toBe($page['path']);
    }
});

test('navigation ask returns exact href from knowledge map', function () {
    $user = seedDashboardAwareUser();
    $result = app(FinancialAssistantService::class)->ask($user, 'Where can I see my budgets?');

    expect($result['intent'])->toBe('navigation')
        ->and($result['links'])->toBe([['label' => 'Open Budgets', 'href' => '/budgets']])
        ->and($result['answer'])->toContain('/budgets');
});

test('balance ask returns dashboard link and concise answer', function () {
    $user = seedDashboardAwareUser();
    $result = app(FinancialAssistantService::class)->ask($user, 'What is my available balance?');

    expect($result['intent'])->toBe('balance')
        ->and($result['answer'])->toMatch('/available balance is Rs/i')
        ->and($result['links'])->toBe([['label' => 'Open Dashboard', 'href' => '/dashboard']]);
});

test('out of scope includes name and social prompts with exact reply', function (string $message) {
    $result = app(FinancialAssistantService::class)->ask(User::factory()->create(), $message);

    expect($result['in_scope'])->toBeFalse()
        ->and($result['links'])->toBe([])
        ->and($result['answer'])->toBe(
            'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.',
        );
})->with([
    'Hi',
    'How are you?',
    'What is your name?',
    'Who are you?',
    'Tell me a joke',
    'What is the weather?',
    'Who is Elon Musk?',
    'Explain quantum physics',
]);

test('coach focus still uses coach recommendation and intelligence link', function () {
    $user = seedDashboardAwareUser();
    $result = app(FinancialAssistantService::class)->ask($user, 'What should I focus on?');

    expect($result['intent'])->toBe('coach_priority')
        ->and($result['answer'])->toContain('Food')
        ->and($result['next_action'])->not->toBeEmpty()
        ->and(collect($result['links'])->pluck('href')->all())->toContain('/intelligence');
});

test('gemini failure still returns deterministic answer with valid links', function () {
    $user = seedDashboardAwareUser();
    $fake = new FakeAssistantProvider;
    $fake->available = false;
    $fake->nextAnswer = null;
    app()->instance(AiAssistantProvider::class, $fake);

    $result = app(FinancialAssistantService::class)->ask($user, 'Where did my money go?');

    expect($result['intent'])->toBe('spending')
        ->and($result['answer'])->toContain('Food')
        ->and(collect($result['links'])->pluck('href')->all())->toContain('/transactions');
});

test('assistant links never invent foreign user ids', function () {
    $userA = seedDashboardAwareUser();
    $userB = User::factory()->create();
    fundUser($userB, '1000.00');

    $result = app(FinancialAssistantService::class)->ask($userA, 'Where can I see my transactions?');
    $hrefs = collect($result['links'])->pluck('href')->implode(' ');

    expect($hrefs)->not->toContain((string) $userB->id)
        ->and($hrefs)->not->toContain((string) $userA->id)
        ->and($result['links'])->toBe([['label' => 'Open Transactions', 'href' => '/transactions']]);
});

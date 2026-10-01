<?php

/**
 * Live HTTP E2E verification against seeded demo user (test@example.com).
 * Run: php artisan test --filter=LiveBrowserQaVerificationTest
 */

use App\Contracts\AiAssistantProvider;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\CoachRecommendationEngine;
use App\Services\Ai\FinancialAssistantService;
use App\Services\Ai\FinancialContextBuilder;
use App\Services\Ai\IncomeAllocationPlanner;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\Ai\Providers\FakeAssistantProvider;
use App\Services\Ai\Providers\NullAssistantProvider;
use App\Services\Intelligence\ExpenseAlertBridge;
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
    ]);
    app()->instance(AiAssistantProvider::class, new NullAssistantProvider);
});

function qaUser(): User
{
    $user = User::factory()->create([
        'email' => 'qa-coach@example.com',
        'savings_goal' => 8000,
    ]);
    fundUser($user, '50000.00');
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

    return $user;
}

test('NL paraphrases resolve to coach or save families with personalized Food facts', function () {
    $user = qaUser();
    $svc = app(FinancialAssistantService::class);

    $coachPrompts = [
        'What can I do?',
        'What should I do?',
        'Where should I start?',
        'What should I focus on?',
        'How can I improve my spending?',
        'What needs my attention?',
        'Can I improve my finances?',
    ];

    foreach ($coachPrompts as $prompt) {
        $r = $svc->ask($user, $prompt);
        expect($r['intent'])->toBeIn(['coach_priority', 'save_more'])
            ->and($r['answer'])->toContain('Food')
            ->and($r['facts_used'])->not->toBeEmpty()
            ->and($r['next_action'])->not->toBeEmpty()
            ->and($r['answer'])->not->toMatch('/^This month you\'ve spent/i');
    }

    $save = $svc->ask($user, 'Where can I cut back?');
    expect($save['intent'])->toBe('save_more')->and($save['answer'])->toContain('Food');

    $save2 = $svc->ask($user, 'How do I save more?');
    expect($save2['intent'])->toBe('save_more');

    $spend = $svc->ask($user, 'Where is most of my money going?');
    expect($spend['intent'])->toBe('spending')->and($spend['answer'])->toContain('Food');
});

test('intelligence page renders coach chat shell for authenticated user', function () {
    $user = qaUser();

    $this->actingAs($user)
        ->get(route('intelligence.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intelligence/index')
            ->has('assistant')
            ->has('assistant.suggested_prompts'));
});

test('assistant ask endpoint returns coach structure for What can I do', function () {
    $user = qaUser();

    $json = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), ['message' => 'What can I do?'])
        ->assertOk()
        ->json('response');

    expect($json['intent'])->toBe('coach_priority')
        ->and($json['answer'])->toContain('Food')
        ->and($json['facts_used'])->not->toBeEmpty()
        ->and($json['next_action'])->not->toBeEmpty()
        ->and($json)->toHaveKey('response_type');
});

test('follow-up chain via HTTP uses history context', function () {
    $user = qaUser();

    $first = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), ['message' => 'What can I do?'])
        ->json('response');

    $why = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'Why?',
            'history' => [
                ['role' => 'user', 'content' => 'What can I do?'],
                ['role' => 'assistant', 'content' => $first['answer']],
            ],
        ])
        ->json('response');

    expect($why['intent'])->toBe('spending_why')->and($why['answer'])->toContain('Food');

    $how = $this->actingAs($user)
        ->postJson(route('intelligence.assistant.ask'), [
            'message' => 'How much?',
            'history' => [
                ['role' => 'user', 'content' => 'What can I do?'],
                ['role' => 'assistant', 'content' => $first['answer']],
            ],
        ])
        ->json('response');

    expect($how['intent'])->toBe('coach_how_much')->and($how['answer'])->toMatch('/Rs/');
});

test('rs 30000 income plan totals exactly and dedupes notification', function () {
    $user = User::factory()->create(['savings_goal' => 5000]);
    fundUser($user, '10000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '2500.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $income = Transaction::factory()->for($user)->create([
        'type' => TransactionType::Income,
        'amount' => '30000.00',
        'transaction_date' => now()->toDateString(),
        'description' => 'QA Allowance 30000',
    ]);

    $plans = app(IncomePlanAlertService::class);
    $plans->handle($user, $income);
    $plans->handle($user, $income); // duplicate

    $plan = $plans->planFor($user, $income->id);
    expect($plan)->not->toBeNull()
        ->and($plan['total'])->toBe('30000.00')
        ->and($plan['transaction_id'])->toBe($income->id);

    $sum = collect($plan['plan'])->sum(fn ($row) => (float) $row['amount']);
    expect($sum)->toEqualWithDelta(30000.0, 0.01);

    $context = app(FinancialContextBuilder::class)->forUser($user);
    $planned = app(IncomeAllocationPlanner::class)->plan($user, '30000.00', $context);
    expect($planned['total'])->toBe('30000.00');
    expect(collect($planned['plan'])->sum(fn ($r) => (float) $r['amount']))->toEqualWithDelta(30000.0, 0.01);

    $notifCount = $user->fresh()->notifications()
        ->where('data->type', 'income_plan')
        ->where('data->transaction_id', $income->id)
        ->count();
    expect($notifCount)->toBe(1);

    // Cross-user isolation
    $other = User::factory()->create();
    expect($plans->planFor($other, $income->id))->toBeNull();
});

test('gemini payload includes coach_recommendation when narrating', function () {
    $user = qaUser();
    $fake = new FakeAssistantProvider;
    $fake->nextAnswer = [
        'intent' => 'coach_priority',
        'response_type' => 'financial_action',
        'in_scope' => true,
        'answer' => 'Focus on Food based on Campus Coin data.',
        'facts_used' => ['Food'],
        'needs_clarification' => false,
        'next_action' => 'Reduce Food slightly.',
    ];
    app()->instance(AiAssistantProvider::class, $fake);

    $result = app(FinancialAssistantService::class)->ask($user, 'What can I do?');

    expect($fake->calls)->toBeGreaterThan(0)
        ->and($fake->lastPayload)->toHaveKey('coach_recommendation')
        ->and($fake->lastPayload['coach_recommendation']['primary_category'] ?? null)->toBe('Food')
        ->and($result['answer'])->toContain('Food');
});

test('gemini http failure falls back to coach without inventing', function () {
    $user = qaUser();
    $fake = new FakeAssistantProvider;
    $fake->available = true;
    $fake->throwOnAnswer = new RuntimeException('503');
    app()->instance(AiAssistantProvider::class, $fake);

    $result = app(FinancialAssistantService::class)->ask($user, 'Narrate my Campus Coin money picture please.');

    expect($result['intent'])->toBe('coach_priority')
        ->and($result['answer'])->toContain('Food')
        ->and($result['error'])->toBeNull();
});

test('coach engine priority ladder P0 P1 P4', function () {
    // P0 negative balance
    $u0 = User::factory()->create(['savings_goal' => 0]);
    Transaction::factory()->for($u0)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '100.00',
        'transaction_date' => now()->toDateString(),
    ]);
    $r0 = app(CoachRecommendationEngine::class)->recommend(app(FinancialContextBuilder::class)->forUser($u0));
    expect($r0['priority'])->toBe('P0');

    // P1 over budget
    $u1 = User::factory()->create(['savings_goal' => 1000]);
    fundUser($u1, '40000.00');
    $food = defaultCategoryId('expense', 'Food');
    Budget::factory()->for($u1)->create([
        'category_id' => $food,
        'month' => now()->startOfMonth()->toDateString(),
        'limit_amount' => '1000.00',
    ]);
    Transaction::factory()->for($u1)->expense()->create([
        'category_id' => $food,
        'amount' => '1500.00',
        'transaction_date' => now()->toDateString(),
    ]);
    $r1 = app(CoachRecommendationEngine::class)->recommend(app(FinancialContextBuilder::class)->forUser($u1));
    expect($r1['priority'])->toBe('P1')->and($r1['primary_category'])->toBe('Food');

    // P4 healthy-ish with no goal and modest spend
    $u4 = User::factory()->create(['savings_goal' => 0]);
    fundUser($u4, '50000.00');
    Transaction::factory()->for($u4)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '200.00',
        'transaction_date' => now()->toDateString(),
    ]);
    $r4 = app(CoachRecommendationEngine::class)->recommend(app(FinancialContextBuilder::class)->forUser($u4));
    expect($r4['priority'])->toBeIn(['P3', 'P4']);
});

test('uncategorized never primary when Food exists', function () {
    $user = User::factory()->create(['savings_goal' => 2000]);
    fundUser($user, '40000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '3000.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'amount' => '9000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $rec = app(CoachRecommendationEngine::class)->recommend(app(FinancialContextBuilder::class)->forUser($user));
    expect($rec['primary_category'])->toBe('Food')
        ->and($rec['data_quality_note'])->not->toBeNull();
});

test('unusual expense notification includes what why action fields', function () {
    $user = qaUser();
    $expense = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Entertainment'),
        'amount' => '15000.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $bridge = app(ExpenseAlertBridge::class);
    $bridge->handle($user, $expense, [
        'signal_type' => 'large_transaction',
        'id' => 1,
        'reason' => 'That Rs 15,000 expense is significantly higher than your recent spending in Entertainment.',
        'explanation' => [
            'category_name' => 'Entertainment',
            'amount' => '15000.00',
        ],
        'transaction' => ['category' => 'Entertainment', 'amount' => '15000.00'],
    ]);

    $n = $user->fresh()->notifications->firstWhere(fn ($x) => ($x->data['type'] ?? null) === 'unusual_expense');
    expect($n)->not->toBeNull()
        ->and($n->data['what_happened'] ?? null)->toContain('Entertainment')
        ->and($n->data['why_it_matters'] ?? null)->not->toBeEmpty()
        ->and($n->data['suggested_action'] ?? null)->not->toBeEmpty()
        ->and($n->data['message'])->toContain('What happened');
});

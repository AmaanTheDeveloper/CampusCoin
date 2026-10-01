<?php

use App\Contracts\AiCategorizationProvider;
use App\Enums\TransactionType;
use App\Models\AiCategoryFeedback;
use App\Models\User;
use App\Services\Ai\AiCategorizationService;
use App\Services\Ai\Providers\FakeCategorizationProvider;
use App\Services\Ai\Providers\NullCategorizationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    config([
        'ai.enabled' => true,
        'ai.api_key' => 'test-key',
        'ai.rate_limit_per_minute' => 20,
        'ai.learning_min_corrections' => 2,
        'ai.cache_ttl_seconds' => 0,
        'cache.default' => 'array',
    ]);
});

function bindFakeAi(?FakeCategorizationProvider $fake = null): FakeCategorizationProvider
{
    $fake ??= new FakeCategorizationProvider;
    app()->instance(AiCategorizationProvider::class, $fake);

    return $fake;
}

test('guests cannot request ai suggestions', function () {
    $this->postJson(route('transactions.suggest-category'), [
        'type' => 'expense',
        'description' => 'Uber ride',
    ])->assertUnauthorized();
});

test('unverified users cannot request ai suggestions', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Uber ride',
        ])
        ->assertForbidden();
});

test('valid suggestion is returned and validated', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Food',
        'confidence' => 'high',
        'reason' => 'Looks like a meal.',
    ];

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Campus cafe lunch',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion.category_id', $food)
        ->assertJsonPath('suggestion.category_name', 'Food')
        ->assertJsonPath('suggestion.confidence', 'high');
});

test('invalid category name from provider is rejected', function () {
    $user = User::factory()->create();
    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'NotARealCategory',
        'confidence' => 'high',
        'reason' => 'Invented',
    ];

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Something odd',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('error', fn ($value) => is_string($value) && $value !== '');
});

test('wrong type category from provider is rejected', function () {
    $user = User::factory()->create();
    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Allowance',
        'confidence' => 'high',
        'reason' => 'Wrong type',
    ];

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Monthly money',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion', null);
});

test('provider failure falls back safely', function () {
    $user = User::factory()->create();
    $fake = bindFakeAi();
    $fake->throwOnSuggest = new RuntimeException('timeout');

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Uber to campus',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion', null)
        ->assertJsonPath('error', fn ($v) => str_contains((string) $v, 'unavailable'));
});

test('null provider marks ai unavailable without blocking', function () {
    app()->instance(AiCategorizationProvider::class, new NullCategorizationProvider);
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $this->actingAs($user)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Pizza night',
        ])
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('suggestion', null);

    fundUser($user);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'category_id' => $food,
        'amount' => '12.50',
        'description' => 'Pizza night',
        'transaction_date' => '2026-09-10',
    ])->assertRedirect(route('transactions.index'));
});

test('another users personal category is never suggested', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $gaming = $userA->categories()->create([
        'name' => 'Gaming',
        'type' => 'expense',
        'is_default' => false,
    ]);

    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Gaming',
        'confidence' => 'high',
        'reason' => 'Should not leak',
    ];

    $this->actingAs($userB)
        ->postJson(route('transactions.suggest-category'), [
            'type' => 'expense',
            'description' => 'Steam purchase',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion', null);

    expect($gaming->id)->toBeInt();
});

test('accepting suggestion records feedback and stores selected category', function () {
    $user = User::factory()->create();
    fundUser($user);
    $transport = defaultCategoryId('expense', 'Transport');
    bindFakeAi();

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'category_id' => $transport,
        'amount' => '25.00',
        'description' => 'Uber from university',
        'transaction_date' => '2026-09-11',
        'ai_suggested_category_id' => $transport,
        'ai_suggestion_accepted' => true,
    ])->assertRedirect(route('transactions.index'));

    expect(AiCategoryFeedback::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(AiCategoryFeedback::query()->first()->was_accepted)->toBeTrue();
    expect($user->transactions()->where('type', 'expense')->first()->category_id)->toBe($transport);
});

test('manual override records correction feedback', function () {
    $user = User::factory()->create();
    fundUser($user);
    $food = defaultCategoryId('expense', 'Food');
    $entertainment = defaultCategoryId('expense', 'Entertainment');

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'category_id' => $entertainment,
        'amount' => '18.00',
        'description' => 'Burger with friends',
        'transaction_date' => '2026-09-12',
        'ai_suggested_category_id' => $food,
        'ai_suggestion_accepted' => false,
    ])->assertRedirect();

    $feedback = AiCategoryFeedback::query()->first();
    expect($feedback->was_accepted)->toBeFalse();
    expect($feedback->suggested_category_id)->toBe($food);
    expect($feedback->selected_category_id)->toBe($entertainment);
});

test('user specific learning influences future suggestions', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $subscriptions = defaultCategoryId('expense', 'Subscriptions');

    AiCategoryFeedback::query()->create([
        'user_id' => $user->id,
        'transaction_type' => 'expense',
        'description' => 'Netflix',
        'normalized_description' => 'netflix',
        'suggested_category_id' => $food,
        'selected_category_id' => $subscriptions,
        'was_accepted' => false,
        'created_at' => now()->subMinute(),
    ]);
    AiCategoryFeedback::query()->create([
        'user_id' => $user->id,
        'transaction_type' => 'expense',
        'description' => 'Netflix',
        'normalized_description' => 'netflix',
        'suggested_category_id' => $food,
        'selected_category_id' => $subscriptions,
        'was_accepted' => false,
        'created_at' => now(),
    ]);

    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Food',
        'confidence' => 'high',
        'reason' => 'Should not be used',
    ];

    $result = app(AiCategorizationService::class)->suggestForUser(
        $user,
        TransactionType::Expense,
        'Netflix',
    );

    expect($result['suggestion']['category_id'])->toBe($subscriptions);
    expect($result['suggestion']['source'])->toBe('learned');
    expect($fake->suggestCalls)->toBe(0);
});

test('learning does not leak across users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $subscriptions = defaultCategoryId('expense', 'Subscriptions');

    AiCategoryFeedback::query()->create([
        'user_id' => $userA->id,
        'transaction_type' => 'expense',
        'description' => 'Netflix',
        'normalized_description' => 'netflix',
        'suggested_category_id' => $food,
        'selected_category_id' => $subscriptions,
        'was_accepted' => false,
    ]);
    AiCategoryFeedback::query()->create([
        'user_id' => $userA->id,
        'transaction_type' => 'expense',
        'description' => 'Netflix',
        'normalized_description' => 'netflix',
        'suggested_category_id' => $food,
        'selected_category_id' => $subscriptions,
        'was_accepted' => false,
    ]);

    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Entertainment',
        'confidence' => 'medium',
        'reason' => 'Generic',
    ];

    $result = app(AiCategorizationService::class)->suggestForUser(
        $userB,
        TransactionType::Expense,
        'Netflix',
    );

    expect($result['suggestion']['category_name'])->toBe('Entertainment');
    expect($result['suggestion']['source'])->toBe('ai');
});

test('rate limiting returns graceful error', function () {
    $user = User::factory()->create();
    config(['ai.rate_limit_per_minute' => 1]);
    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Transport',
        'confidence' => 'high',
        'reason' => 'Ride',
    ];

    RateLimiter::clear('ai-categorize:'.$user->id);

    $this->actingAs($user)->postJson(route('transactions.suggest-category'), [
        'type' => 'expense',
        'description' => 'Uber one',
    ])->assertOk()->assertJsonPath('suggestion.category_name', 'Transport');

    $this->actingAs($user)->postJson(route('transactions.suggest-category'), [
        'type' => 'expense',
        'description' => 'Uber two',
    ])->assertOk()->assertJsonPath('suggestion', null)
        ->assertJsonPath('error', fn ($v) => str_contains((string) $v, 'Too many'));
});

test('http facade is never called in mocked tests', function () {
    Http::fake();
    $user = User::factory()->create();
    $fake = bindFakeAi();
    $fake->nextSuggestion = [
        'category_name' => 'Food',
        'confidence' => 'high',
        'reason' => 'Meal',
    ];

    $this->actingAs($user)->postJson(route('transactions.suggest-category'), [
        'type' => 'expense',
        'description' => 'Lunch',
    ])->assertOk();

    Http::assertNothingSent();
});

test('csv enrich skips exact category matches and suggests for uncategorized', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    $fake = bindFakeAi();
    $fake->batchSuggestions = [
        1 => [
            'category_name' => 'Transport',
            'confidence' => 'high',
            'reason' => 'Ride share',
        ],
    ];

    $rows = [
        [
            'row_number' => 1,
            'date' => '2026-09-01',
            'type' => 'expense',
            'amount' => '10.00',
            'description' => 'Cafe',
            'category_name' => 'Food',
            'category_id' => $food,
            'status' => 'valid',
            'messages' => [],
        ],
        [
            'row_number' => 2,
            'date' => '2026-09-02',
            'type' => 'expense',
            'amount' => '20.00',
            'description' => 'Uber ride',
            'category_name' => null,
            'category_id' => null,
            'status' => 'warning',
            'messages' => ['Unknown category'],
        ],
    ];

    // Remap batch indexes: enrichCsvRows uses array indexes 0,1
    $fake->batchSuggestions = [
        1 => [
            'category_name' => 'Transport',
            'confidence' => 'high',
            'reason' => 'Ride share',
        ],
    ];

    $enriched = app(AiCategorizationService::class)->enrichCsvRows($user, $rows);

    expect($enriched[0]['ai_suggestion'])->toBeNull();
    expect($enriched[1]['ai_suggestion']['category_id'])->toBe($transport);
    expect($fake->batchCalls)->toBe(1);
});

test('create page exposes aiAvailable flag', function () {
    $user = User::factory()->create();
    bindFakeAi();

    $this->actingAs($user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/create')
            ->where('aiAvailable', true)
        );
});

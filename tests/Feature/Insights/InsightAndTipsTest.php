<?php

use App\Contracts\AiInsightProvider;
use App\Models\AiMonthlyInsight;
use App\Models\Bookmark;
use App\Models\Budget;
use App\Models\SavingTip;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\Providers\FakeInsightProvider;
use App\Services\Ai\Providers\NullInsightProvider;
use App\Services\Insights\MonthlyInsightService;
use App\Services\Tips\SavingTipsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow('2026-09-15 12:00:00');
    config([
        'ai.enabled' => true,
        'ai.api_key' => 'test-key',
        'ai.insight_rate_limit_per_minute' => 10,
        'cache.default' => 'array',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function bindFakeInsight(?FakeInsightProvider $fake = null): FakeInsightProvider
{
    $fake ??= new FakeInsightProvider;
    app()->instance(AiInsightProvider::class, $fake);

    return $fake;
}

test('dashboard includes insight panel and tips without calling ai', function () {
    Http::fake();
    $user = User::factory()->create(['savings_goal' => '5000.00']);
    $food = defaultCategoryId('expense', 'Food');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '3000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $fake = bindFakeInsight();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.monthly_insight')
            ->has('dashboard.saving_tips')
            ->where('dashboard.monthly_insight.insight', null)
        );

    expect($fake->calls)->toBe(0);
    Http::assertNothingSent();
});

test('insight generation persists validated ai output once per period', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2500.00',
        'transaction_date' => '2026-09-04',
    ]);

    $fake = bindFakeInsight();
    $fake->nextInsight = [
        'headline' => 'Food spending led the month',
        'summary' => 'Food was your largest expense category this month.',
        'trends' => [
            ['label' => 'Food spending increased', 'direction' => 'up', 'significance' => 'medium'],
        ],
        'actions' => ['Review frequent food purchases this month'],
    ];

    $this->actingAs($user)
        ->post(route('insights.regenerate'))
        ->assertRedirect();

    expect(AiMonthlyInsight::query()->where('user_id', $user->id)->count())->toBe(1);
    $insight = AiMonthlyInsight::query()->first();
    expect($insight->headline)->toBe('Food spending led the month');
    expect($insight->generated_source)->toBe('ai');

    $this->actingAs($user)->post(route('insights.regenerate'))->assertRedirect();
    expect(AiMonthlyInsight::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($fake->calls)->toBe(2);
});

test('malformed ai insight falls back to factual snapshot', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '1000.00',
        'transaction_date' => '2026-09-03',
    ]);

    $fake = bindFakeInsight();
    $fake->nextInsight = ['headline' => '', 'summary' => ''];

    $insight = app(MonthlyInsightService::class)->generateForUser($user, null, true);

    expect($insight->generated_source)->toBe('fallback');
    expect($insight->headline)->toContain('Food');
});

test('provider failure uses fallback and keeps app usable', function () {
    $user = User::factory()->create();
    $fake = bindFakeInsight();
    $fake->throwOnGenerate = new RuntimeException('timeout');

    $insight = app(MonthlyInsightService::class)->generateForUser($user, null, true);
    expect($insight->generated_source)->toBe('fallback');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('null provider still generates factual fallback', function () {
    app()->instance(AiInsightProvider::class, new NullInsightProvider);
    $user = User::factory()->create();

    $insight = app(MonthlyInsightService::class)->generateForUser($user, null, true);
    expect($insight->generated_source)->toBe('fallback');
    expect(app(MonthlyInsightService::class)->isAvailable())->toBeFalse();
});

test('user cannot view another users insight history', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AiMonthlyInsight::query()->create([
        'user_id' => $userA->id,
        'period' => '2026-09',
        'headline' => 'Secret',
        'summary' => 'Private',
        'insight_payload' => ['trends' => [], 'actions' => []],
        'generated_source' => 'fallback',
        'status' => 'ready',
        'generated_at' => now(),
    ]);

    $this->actingAs($userB)
        ->get(route('insights.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('insights', []));
});

test('saving tips rank budget exceeded above concentration', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '150.00',
        'transaction_date' => '2026-09-05',
    ]);

    $tips = app(SavingTipsService::class)->syncAndListForUser($user);
    expect($tips)->not->toBeEmpty();
    expect($tips[0]['impact_level'])->toBe('high');
    expect($tips[0]['tip_key'])->toStartWith('budget_exceeded_');
});

test('dismiss tip persists for the period', function () {
    $user = User::factory()->create(['savings_goal' => '10000.00']);
    $food = defaultCategoryId('expense', 'Food');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => '2026-09-02',
    ]);

    $tips = app(SavingTipsService::class)->syncAndListForUser($user);
    expect($tips)->not->toBeEmpty();
    $tipId = $tips[0]['id'];

    $this->actingAs($user)
        ->post(route('saving-tips.dismiss', $tipId))
        ->assertRedirect();

    $again = app(SavingTipsService::class)->syncAndListForUser($user);
    expect(collect($again)->pluck('id')->all())->not->toContain($tipId);
});

test('bookmarks are user scoped and toggleable', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $insight = AiMonthlyInsight::query()->create([
        'user_id' => $userA->id,
        'period' => '2026-09',
        'headline' => 'Hello',
        'summary' => 'World',
        'insight_payload' => ['trends' => [], 'actions' => []],
        'generated_source' => 'fallback',
        'status' => 'ready',
        'generated_at' => now(),
    ]);

    $this->actingAs($userA)
        ->post(route('bookmarks.toggle'), ['type' => 'insight', 'id' => $insight->id])
        ->assertRedirect();

    expect(Bookmark::query()->where('user_id', $userA->id)->count())->toBe(1);

    $this->actingAs($userB)
        ->post(route('bookmarks.toggle'), ['type' => 'insight', 'id' => $insight->id])
        ->assertNotFound();

    $this->actingAs($userB)
        ->get(route('bookmarks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('bookmarks', []));
});

test('ai xss payload is stripped on validation', function () {
    $service = app(MonthlyInsightService::class);
    $validated = $service->validateInsight([
        'headline' => '<script>alert(1)</script>Clean headline',
        'summary' => '<b>Bold</b> summary',
        'trends' => [
            ['label' => '<img src=x onerror=alert(1)>Food rose', 'direction' => 'up', 'significance' => 'high'],
        ],
        'actions' => ['<script>bad</script>Review food'],
    ], [
        'top_categories' => [['name' => 'Food', 'amount' => '10.00', 'percentage' => 100]],
        'budget_status' => [],
        'facts' => [],
    ]);

    expect($validated['headline'])->not->toContain('<script>');
    expect($validated['summary'])->not->toContain('<b>');
    expect($validated['trends'][0]['label'])->not->toContain('<img');
    expect($validated['actions'][0])->not->toContain('<script>');
});

test('unsupported invented amounts in trends are rejected', function () {
    $service = app(MonthlyInsightService::class);
    $validated = $service->validateInsight([
        'headline' => 'Spending shifted',
        'summary' => 'Review categories.',
        'trends' => [
            ['label' => 'Food rose by Rs. 999999', 'direction' => 'up', 'significance' => 'high'],
            ['label' => 'Food spending increased', 'direction' => 'up', 'significance' => 'medium'],
        ],
        'actions' => ['Review food'],
    ], [
        'top_categories' => [['name' => 'Food', 'amount' => '100.00', 'percentage' => 40]],
        'budget_status' => [],
        'income' => '0.00',
        'expense' => '100.00',
        'balance' => '-100.00',
        'facts' => [],
    ]);

    expect(collect($validated['trends'])->pluck('label')->all())->toBe(['Food spending increased']);
});

test('insight rate limit returns existing or fallback without crash', function () {
    $user = User::factory()->create();
    config(['ai.insight_rate_limit_per_minute' => 1]);
    $fake = bindFakeInsight();
    $fake->nextInsight = [
        'headline' => 'First',
        'summary' => 'First summary',
        'trends' => [],
        'actions' => [],
    ];

    RateLimiter::clear('ai-insight:'.$user->id);
    app(MonthlyInsightService::class)->generateForUser($user, null, true);
    $second = app(MonthlyInsightService::class)->generateForUser($user, null, true);

    expect($second)->toBeInstanceOf(AiMonthlyInsight::class);
});

test('admin cannot read another users tips via dismiss', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $tip = SavingTip::query()->create([
        'user_id' => $student->id,
        'tip_key' => 'test',
        'period' => '2026-09',
        'title' => 'Tip',
        'body' => 'Body',
        'impact_level' => 'low',
        'rank_score' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('saving-tips.dismiss', $tip))
        ->assertForbidden();
});

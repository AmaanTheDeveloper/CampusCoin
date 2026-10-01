<?php

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MonthlyReviewService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access monthly review', function () {
    $this->get(route('monthly-review.index'))->assertRedirect(route('login'));
});

test('monthly summary income expenses saved and savings rate', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '60000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '42500.00',
        'transaction_date' => '2026-09-10',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');

    expect($review['summary']['income'])->toBe('60000.00');
    expect($review['summary']['expenses'])->toBe('42500.00');
    expect($review['summary']['saved'])->toBe('17500.00');
    expect($review['summary']['savings_rate'])->toBe(29.17);
    expect($review['month']['start'])->toBe('2026-09-01');
    expect($review['month']['end'])->toBe('2026-09-30');
});

test('top category is highest spend with deterministic tie break', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '13200.00',
        'transaction_date' => '2026-09-05',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => '5000.00',
        'transaction_date' => '2026-09-06',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['top_category']['name'])->toBe('Food');
    expect($review['top_category']['amount'])->toBe('13200.00');
});

test('no expenses yields null top category', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['top_category'])->toBeNull();
});

test('biggest change detects meaningful increase and ignores tiny categories', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $entertainment = defaultCategoryId('expense', 'Entertainment');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $entertainment,
        'amount' => '2000.00',
        'transaction_date' => '2026-08-10',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $entertainment,
        'amount' => '2620.00',
        'transaction_date' => '2026-09-10',
    ]);
    // Tiny category should not win.
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-08-10',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '400.00',
        'transaction_date' => '2026-09-10',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['biggest_change'])->not->toBeNull();
    expect($review['biggest_change']['name'])->toBe('Entertainment');
    expect($review['biggest_change']['direction'])->toBe('up');
    expect($review['biggest_change']['change_percent'])->toBe(31.0);
});

test('zero previous month spend uses new category language not infinity', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Entertainment'),
        'amount' => '1500.00',
        'transaction_date' => '2026-09-12',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['biggest_change']['direction'])->toBe('new');
    expect($review['biggest_change']['change_percent'])->toBeNull();
    expect($review['biggest_change']['label'])->toContain('New spending');
});

test('stable spending produces stable insight without fake change', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => '2026-08-15',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5100.00',
        'transaction_date' => '2026-09-15',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['overall_trend']['direction'])->toBe('stable');
    expect($review['biggest_change'])->toBeNull();
    expect($review['insight']['text'])->toContain('stable');
});

test('budget performance counts within and exceeded', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '6000.00',
    ]);
    Budget::factory()->for($user)->create([
        'category_id' => $transport,
        'month' => '2026-09-01',
        'limit_amount' => '3000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-05',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '3500.00',
        'transaction_date' => '2026-09-06',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['budget_performance']['available'])->toBeTrue();
    expect($review['budget_performance']['within_limit'])->toBe(1);
    expect($review['budget_performance']['exceeded'])->toBe(1);
    expect($review['budget_performance']['label'])->toBe('1 / 2 budgets within limits');
});

test('no budgets shows friendly empty budget message', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['budget_performance']['available'])->toBeFalse();
    expect($review['budget_performance']['label'])->toBe('No budgets set for this month.');
});

test('actions prioritize budget overrun and cap at three', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $entertainment = defaultCategoryId('expense', 'Entertainment');
    $subs = defaultCategoryId('expense', 'Subscriptions');
    $transport = defaultCategoryId('expense', 'Transport');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    foreach ([$food, $entertainment, $subs, $transport] as $i => $categoryId) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $categoryId,
            'amount' => '2000.00',
            'transaction_date' => '2026-08-0'.($i + 1),
        ]);
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $categoryId,
            'amount' => '3000.00',
            'transaction_date' => '2026-09-0'.($i + 1),
        ]);
    }

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect(count($review['actions']))->toBeLessThanOrEqual(3);
    expect($review['actions'][0]['title'])->toContain('Food');
});

test('empty month does not fabricate insight or actions', function () {
    $user = User::factory()->create();
    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');

    expect($review['has_activity'])->toBeFalse();
    expect($review['insight']['available'])->toBeFalse();
    expect($review['actions'])->toBe([]);
    expect($review['empty_message'])->not->toBeNull();
});

test('user only sees their own review data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Transaction::factory()->for($other)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '99999.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['summary']['income'])->toBe('1000.00');
});

test('monthly review page renders for authenticated user', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '5000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->get(route('monthly-review.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monthly-review/index')
            ->where('review.summary.income', '5000.00')
            ->has('review.insight')
            ->has('review.actions')
        );
});

test('savings rate unavailable when income is zero', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '200.00',
        'transaction_date' => '2026-09-02',
    ]);

    $review = app(MonthlyReviewService::class)->forUser($user, '2026-09');
    expect($review['summary']['savings_rate'])->toBeNull();
    expect($review['summary']['saved'])->toBe('-200.00');
});

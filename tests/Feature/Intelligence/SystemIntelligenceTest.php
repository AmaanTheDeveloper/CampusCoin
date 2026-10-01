<?php

use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Models\Transaction;
use App\Models\TransactionActivity;
use App\Models\TransactionSignal;
use App\Models\User;
use App\Services\Intelligence\DuplicateDetectionService;
use App\Services\Intelligence\ForecastService;
use App\Services\Intelligence\LargeTransactionDetectionService;
use App\Services\Intelligence\RecentActivityService;
use App\Services\Intelligence\TransactionSignalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow('2026-09-15 12:00:00');
    config([
        'intelligence.large_min_observations' => 5,
        'intelligence.large_multiplier' => 2.5,
        'intelligence.large_absolute_floor' => 500,
        'intelligence.forecast_lookback_months' => 3,
        'intelligence.activity_limit' => 10,
        'intelligence.duplicate_date_window_days' => 0,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('opening edit records viewed activity and updates timestamp on repeat', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'description' => 'Lunch',
        'transaction_date' => '2026-09-01',
    ]);

    $this->actingAs($user)->get(route('transactions.edit', $txn))->assertOk();

    expect(TransactionActivity::query()->where('user_id', $user->id)->count())->toBe(1);
    $first = TransactionActivity::query()->first()->occurred_at;

    Carbon::setTestNow('2026-09-15 13:00:00');
    $this->actingAs($user)->get(route('transactions.edit', $txn))->assertOk();

    expect(TransactionActivity::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(TransactionActivity::query()->first()->occurred_at->gt($first))->toBeTrue();
});

test('successful update records edit activity but opening edit form alone does not', function () {
    $user = User::factory()->create();
    fundUser($user);
    $food = defaultCategoryId('expense', 'Food');
    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '50.00',
        'description' => 'Snack',
        'transaction_date' => '2026-09-02',
    ]);

    $this->actingAs($user)->get(route('transactions.edit', $txn))->assertOk();
    expect(TransactionActivity::query()->where('activity_type', 'edited')->count())->toBe(0);

    $this->actingAs($user)
        ->put(route('transactions.update', $txn), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '55.00',
            'description' => 'Snack updated',
            'transaction_date' => '2026-09-02',
        ])
        ->assertRedirect();

    expect(TransactionActivity::query()->where('activity_type', 'edited')->count())->toBe(1);
});

test('recent activity is user scoped and excludes soft deleted transactions', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $txnA = Transaction::factory()->for($userA)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-01',
    ]);
    $txnB = Transaction::factory()->for($userB)->expense()->create([
        'category_id' => $food,
        'amount' => '20.00',
        'transaction_date' => '2026-09-01',
    ]);

    app(RecentActivityService::class)->recordView($userA, $txnA);
    app(RecentActivityService::class)->recordView($userB, $txnB);

    $payload = app(RecentActivityService::class)->forUser($userA);
    expect(collect($payload['viewed'])->pluck('transaction_id')->all())->toBe([$txnA->id]);

    $txnA->delete();
    $afterDelete = app(RecentActivityService::class)->forUser($userA);
    expect($afterDelete['viewed'])->toBe([]);
});

test('forecast uses completed months and reports insufficient data for new users', function () {
    $user = User::factory()->create();
    $forecast = app(ForecastService::class)->forUser($user);

    expect($forecast['confidence'])->toBe('insufficient');
    expect($forecast['estimated_expense'])->toBeNull();
    expect($forecast['empty_message'])->not->toBeNull();
});

test('forecast is deterministic from completed historical months', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    foreach (['2026-06', '2026-07', '2026-08'] as $month) {
        Transaction::factory()->for($user)->income()->create([
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '3000.00',
            'transaction_date' => $month.'-05',
        ]);
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $food,
            'amount' => '1200.00',
            'transaction_date' => $month.'-10',
        ]);
    }

    // Partial current month should not skew averages.
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '9999.00',
        'transaction_date' => '2026-09-05',
    ]);

    $a = app(ForecastService::class)->forUser($user);
    $b = app(ForecastService::class)->forUser($user);

    expect($a['confidence'])->toBe('moderate');
    expect($a['months_used'])->toBe(3);
    expect($a['estimated_income'])->toBe('3000.00');
    expect($a['estimated_expense'])->toBe('1200.00');
    expect($a['estimated_balance'])->toBe('1800.00');
    expect($a)->toBe($b);
    expect($a['target_period'])->toBe('2026-10');
});

test('forecast ignores soft deleted and other users data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-08-10',
    ]);
    $deleted = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => '2026-08-11',
    ]);
    $deleted->delete();

    Transaction::factory()->for($other)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => '2026-08-12',
    ]);

    $forecast = app(ForecastService::class)->forUser($user);
    expect($forecast['estimated_expense'])->toBe('100.00');
});

test('large transaction is flagged only with enough history and high amount', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    for ($i = 1; $i <= 5; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $food,
            'amount' => '100.00',
            'description' => "Normal {$i}",
            'transaction_date' => '2026-08-0'.$i,
        ]);
    }

    $normal = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '120.00',
        'description' => 'Still normal',
        'transaction_date' => '2026-09-01',
    ]);
    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $normal))->toBeNull();

    $large = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'description' => 'Big meal',
        'transaction_date' => '2026-09-02',
    ]);
    $signal = app(LargeTransactionDetectionService::class)->evaluate($user, $large);

    expect($signal)->not->toBeNull();
    expect($signal->signal_type)->toBe(TransactionSignalType::LargeTransaction);
    expect($signal->status)->toBe(TransactionSignalStatus::Open);
});

test('large detection requires minimum observations', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-08-01',
    ]);

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-01',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->toBeNull();
});

test('exact duplicate candidates are detected and false pairs are ignored', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => 'Campus lunch',
        'transaction_date' => '2026-09-03',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => '  Campus   Lunch ',
        'transaction_date' => '2026-09-03',
    ]);
    $differentDay = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => 'Campus lunch',
        'transaction_date' => '2026-09-04',
    ]);

    $signals = app(DuplicateDetectionService::class)->evaluate($user, $b);
    expect($signals)->not->toBeEmpty();

    $false = app(DuplicateDetectionService::class)->evaluate($user, $differentDay);
    expect($false)->toBeEmpty();

    expect(Transaction::query()->count())->toBe(3);
});

test('duplicate detection ignores other users and soft deleted peers', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $mine = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '40.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-09-03',
    ]);
    Transaction::factory()->for($other)->expense()->create([
        'category_id' => $food,
        'amount' => '40.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-09-03',
    ]);
    $deleted = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '40.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-09-03',
    ]);
    $deleted->delete();

    expect(app(DuplicateDetectionService::class)->evaluate($user, $mine))->toBeEmpty();
});

test('dismiss and review persist and do not mutate transactions', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    for ($i = 1; $i <= 5; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $food,
            'amount' => '100.00',
            'transaction_date' => '2026-08-0'.$i,
        ]);
    }
    $large = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '900.00',
        'transaction_date' => '2026-09-02',
    ]);

    $signal = app(LargeTransactionDetectionService::class)->evaluate($user, $large);
    $amountBefore = $large->fresh()->amount;

    $this->actingAs($user)
        ->post(route('intelligence.signals.dismiss', $signal))
        ->assertRedirect();

    expect($signal->fresh()->status)->toBe(TransactionSignalStatus::Dismissed);
    expect($large->fresh()->amount)->toBe($amountBefore);
    expect(app(TransactionSignalService::class)->openSignalsForUser($user))->toBeEmpty();
});

test('user cannot access another users signals or intelligence data', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $txn = Transaction::factory()->for($userA)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-01',
    ]);
    $signal = TransactionSignal::query()->create([
        'user_id' => $userA->id,
        'transaction_id' => $txn->id,
        'signal_type' => TransactionSignalType::LargeTransaction,
        'severity' => 'medium',
        'status' => TransactionSignalStatus::Open,
        'reason' => 'Private',
        'fingerprint' => 'abc123',
        'detected_at' => now(),
    ]);

    $this->actingAs($userB)
        ->post(route('intelligence.signals.dismiss', $signal))
        ->assertForbidden();

    $this->actingAs($userB)
        ->get(route('intelligence.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('intelligence.signals', [])
            ->where('intelligence.activity.viewed', [])
        );

    $this->actingAs($userB)
        ->get(route('transactions.edit', $txn))
        ->assertForbidden();
});

test('dashboard includes intelligence payload without inventing forecast for empty history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.intelligence')
            ->where('dashboard.intelligence.forecast.confidence', 'insufficient')
            ->has('dashboard.monthly_insight')
        );
});

test('creating transaction evaluates signals without rolling back save on intelligence failure path', function () {
    $user = User::factory()->create();
    fundUser($user);
    $food = defaultCategoryId('expense', 'Food');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '25.00',
            'description' => 'Tea',
            'transaction_date' => '2026-09-05',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
});

test('soft deleted transaction signals are excluded from open lists', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-01',
    ]);

    TransactionSignal::query()->create([
        'user_id' => $user->id,
        'transaction_id' => $txn->id,
        'signal_type' => TransactionSignalType::LargeTransaction,
        'severity' => 'medium',
        'status' => TransactionSignalStatus::Open,
        'reason' => 'Test',
        'fingerprint' => 'soft-delete-test',
        'detected_at' => now(),
    ]);

    $txn->delete();

    expect(app(TransactionSignalService::class)->openSignalsForUser($user))->toBeEmpty();
});

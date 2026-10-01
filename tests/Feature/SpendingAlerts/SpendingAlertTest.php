<?php

use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Models\Transaction;
use App\Models\TransactionSignal;
use App\Models\User;
use App\Services\Intelligence\LargeTransactionDetectionService;
use App\Services\Intelligence\TransactionSignalService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

function seedCategoryHistory(User $user, int $categoryId, string $amount, int $count = 5, string $month = '2026-08'): void
{
    for ($i = 1; $i <= $count; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $categoryId,
            'amount' => $amount,
            'description' => "History {$i}",
            'transaction_date' => sprintf('%s-%02d', $month, min($i, 28)),
        ]);
    }
}

test('guests cannot access spending alerts', function () {
    $this->get(route('spending-alerts.index'))->assertRedirect(route('login'));
});

test('authenticated user can open spending alerts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spending-alerts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('spending-alerts/index')
            ->has('alerts.unread')
            ->has('alerts.earlier')
            ->where('error', null)
        );
});

test('normal expense near average is not flagged', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '1200.00');

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '1000.00',
        'transaction_date' => '2026-09-10',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->toBeNull();
});

test('expense at least multiplier times average is flagged with explanation', function () {
    $user = User::factory()->create();
    $entertainment = defaultCategoryId('expense', 'Entertainment');
    seedCategoryHistory($user, $entertainment, '3100.00');

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $entertainment,
        'amount' => '8500.00',
        'description' => 'Concert',
        'transaction_date' => '2026-09-12',
    ]);

    $signal = app(LargeTransactionDetectionService::class)->evaluate($user, $txn);

    expect($signal)->not->toBeNull();
    expect($signal->signal_type)->toBe(TransactionSignalType::LargeTransaction);
    expect($signal->payload['deviation_ratio'])->toBeGreaterThanOrEqual(2.5);
    expect($signal->payload['baseline_average'])->toBe('3100.00');
    expect($signal->reason)->toContain('recent average');
});

test('exactly at multiplier threshold is flagged', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '100.00');

    // 2.5 × 100 = 250, floor is 500 → need amount >= 500. Use avg 200 so threshold 500.
    Transaction::query()->where('user_id', $user->id)->delete();
    seedCategoryHistory($user, $food, '200.00');

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '500.00',
        'transaction_date' => '2026-09-10',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->not->toBeNull();
});

test('category specific comparison does not mix food and entertainment', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $entertainment = defaultCategoryId('expense', 'Entertainment');

    seedCategoryHistory($user, $food, '100.00');
    seedCategoryHistory($user, $entertainment, '3000.00');

    $foodSpike = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-10',
    ]);

    $signal = app(LargeTransactionDetectionService::class)->evaluate($user, $foodSpike);
    expect($signal)->not->toBeNull();
    expect($signal->payload['category_name'])->toBe('Food');
    expect((float) $signal->payload['baseline_average'])->toBe(100.0);
});

test('insufficient category history does not invent an average', function () {
    $user = User::factory()->create();
    $transport = defaultCategoryId('expense', 'Transport');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '50.00',
        'transaction_date' => '2026-08-01',
    ]);

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-10',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->toBeNull();
});

test('history outside lookback window is ignored', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    for ($i = 1; $i <= 5; $i++) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $food,
            'amount' => '100.00',
            'transaction_date' => '2026-01-0'.$i,
        ]);
    }

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-10',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->toBeNull();
});

test('income is never flagged as unusual spending', function () {
    $user = User::factory()->create();
    $allowance = defaultCategoryId('income', 'Allowance');

    for ($i = 1; $i <= 5; $i++) {
        Transaction::factory()->for($user)->income()->create([
            'category_id' => $allowance,
            'amount' => '100.00',
            'transaction_date' => '2026-08-0'.$i,
        ]);
    }

    $txn = Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-10',
    ]);

    expect(app(LargeTransactionDetectionService::class)->evaluate($user, $txn))->toBeNull();
});

test('creating unusual expense flashes spending alert without failing save', function () {
    $user = User::factory()->create();
    fundUser($user, '20000.00');
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '100.00');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '800.00',
            'description' => 'Big lunch',
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Transaction added.')
        ->assertSessionHas('spending_alert');

    expect(Transaction::query()->where('description', 'Big lunch')->exists())->toBeTrue();
    expect(TransactionSignal::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('creating normal expense does not flash spending alert', function () {
    $user = User::factory()->create();
    fundUser($user, '20000.00');
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '100.00');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '110.00',
            'description' => 'Normal lunch',
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success')
        ->assertSessionMissing('spending_alert');
});

test('same transaction cannot create duplicate unusual alerts', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '100.00');

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-10',
    ]);

    $service = app(LargeTransactionDetectionService::class);
    expect($service->evaluate($user, $txn))->not->toBeNull();
    expect($service->evaluate($user, $txn))->not->toBeNull();
    expect(TransactionSignal::query()->where('transaction_id', $txn->id)->count())->toBe(1);
});

test('user only sees own spending alerts and cannot access another users signal', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($owner, $food, '100.00');

    $txn = Transaction::factory()->for($owner)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-10',
    ]);
    $signal = app(LargeTransactionDetectionService::class)->evaluate($owner, $txn);

    $this->actingAs($attacker)
        ->get(route('spending-alerts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('alerts.open_count', 0));

    $this->actingAs($attacker)
        ->post(route('intelligence.signals.dismiss', $signal))
        ->assertForbidden();

    $this->actingAs($attacker)
        ->get(route('transactions.show', $txn))
        ->assertForbidden();
});

test('marking alert reviewed moves it out of unread', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    seedCategoryHistory($user, $food, '100.00');

    $txn = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-10',
    ]);
    $signal = app(LargeTransactionDetectionService::class)->evaluate($user, $txn);

    $this->actingAs($user)
        ->post(route('intelligence.signals.review', $signal))
        ->assertRedirect();

    expect($signal->fresh()->status)->toBe(TransactionSignalStatus::Reviewed);

    $payload = app(TransactionSignalService::class)->spendingAlertsForUser($user);
    expect($payload['open_count'])->toBe(0);
    expect($payload['earlier'])->not->toBeEmpty();
});

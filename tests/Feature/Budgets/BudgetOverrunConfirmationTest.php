<?php

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\Carbon;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('expense under budget saves without confirmation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '7000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '1000.00',
            'description' => 'Lunch',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->where('user_id', $user->id)->where('description', 'Lunch')->exists())->toBeTrue();
});

test('expense exactly at budget limit saves without confirmation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '9000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '1000.00',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionMissing('budget_overrun');
});

test('expense exceeding budget requires confirmation and does not persist without it', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '9000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2000.00',
            'description' => 'Over budget meal',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('budget_overrun')
        ->assertSessionHas('budget_overrun', function (array $payload): bool {
            return $payload['limit'] === '10000.00'
                && $payload['resulting_spend'] === '11000.00'
                && $payload['over_amount'] === '1000.00'
                && $payload['exceeds'] === true;
        });

    expect(Transaction::query()->where('user_id', $user->id)->where('description', 'Over budget meal')->exists())->toBeFalse();
});

test('confirmed over-budget expense persists when balance is sufficient', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '9000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2000.00',
            'description' => 'Confirmed overrun',
            'transaction_date' => '2026-09-20',
            'confirm_budget_overrun' => true,
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->where('user_id', $user->id)->where('description', 'Confirmed overrun')->exists())->toBeTrue();
    expect((string) $budget->fresh()->limit_amount)->toBe('10000.00');

    $payload = app(BudgetService::class)->enrichBudgets($user, collect([$budget->fresh()]))[0];
    expect($payload['spent'])->toBe('11000.00');
    expect($payload['remaining'])->toBe('0.00');
    expect($payload['over_budget'])->toBe('1000.00');
});

test('insufficient balance still blocks even with budget confirmation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '1500.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '3000.00',
            'transaction_date' => '2026-09-20',
            'confirm_budget_overrun' => true,
        ])
        ->assertSessionHasErrors('amount')
        ->assertSessionDoesntHaveErrors('budget_overrun');

    expect(Transaction::query()->where('user_id', $user->id)->where('type', 'expense')->count())->toBe(0);
});

test('no budget for category skips overrun confirmation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '5000.00',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionMissing('budget_overrun');
});

test('income never triggers budget overrun confirmation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '5000.00',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionMissing('budget_overrun');
});

test('edit expense excludes original amount from overrun calculation', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $original = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '1000.00',
        'transaction_date' => '2026-09-10',
    ]);

    // Replacement: 9000 - 1000 + 2500 = 10500 → confirm required
    $this->actingAs($user)
        ->from(route('transactions.edit', $original))
        ->put(route('transactions.update', $original), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2500.00',
            'description' => $original->description,
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.edit', $original))
        ->assertSessionHasErrors('budget_overrun')
        ->assertSessionHas('budget_overrun', function (array $payload): bool {
            return $payload['spent_before'] === '8000.00'
                && $payload['resulting_spend'] === '10500.00'
                && $payload['over_amount'] === '500.00';
        });

    expect((string) $original->fresh()->amount)->toBe('1000.00');

    $this->actingAs($user)
        ->put(route('transactions.update', $original), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2500.00',
            'description' => $original->description,
            'transaction_date' => '2026-09-10',
            'confirm_budget_overrun' => true,
        ])
        ->assertRedirect(route('transactions.index'));

    expect((string) $original->fresh()->amount)->toBe('2500.00');
});

test('already exceeded budget still requires confirmation for additional expense', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '12000.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '500.00',
            'transaction_date' => '2026-09-20',
        ])
        ->assertSessionHasErrors('budget_overrun')
        ->assertSessionHas('budget_overrun', function (array $payload): bool {
            return $payload['already_exceeded'] === true
                && $payload['prior_over_amount'] === '2000.00'
                && $payload['over_amount'] === '2500.00';
        });
});

test('server recalculates overrun using fresh spending not stale client totals', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '8000.00',
        'transaction_date' => '2026-09-05',
    ]);

    // Another tab added Rs 1,000 after the form loaded with spent=8000.
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '1000.00',
        'transaction_date' => '2026-09-06',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '2000.00',
            'transaction_date' => '2026-09-20',
            // Client might think 8000+2000=10000 is fine — server sees 9000+2000.
        ])
        ->assertSessionHasErrors('budget_overrun')
        ->assertSessionHas('budget_overrun', function (array $payload): bool {
            return $payload['spent_before'] === '9000.00'
                && $payload['resulting_spend'] === '11000.00';
        });
});

test('user a budget does not affect user b expense confirmation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($userB)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    Budget::factory()->for($userA)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);

    $this->actingAs($userB)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '5000.00',
            'transaction_date' => '2026-09-20',
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionMissing('budget_overrun');
});

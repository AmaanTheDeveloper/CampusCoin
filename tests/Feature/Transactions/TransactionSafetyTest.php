<?php

use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow('2026-09-26 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('future transaction date is rejected on create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '100.00',
            'transaction_date' => '2026-09-27',
        ])
        ->assertSessionHasErrors('transaction_date')
        ->assertRedirect(route('transactions.create'));

    expect(Transaction::count())->toBe(0);
});

test('today and past transaction dates are accepted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '100.00',
            'transaction_date' => '2026-09-26',
        ])
        ->assertRedirect(route('transactions.index'));

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '50.00',
            'transaction_date' => '2026-09-01',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::count())->toBe(2);
});

test('future date is rejected on update while historical date remains editable', function () {
    $user = User::factory()->create();
    $txn = Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '200.00',
        'transaction_date' => '2026-09-20',
    ]);

    $this->actingAs($user)
        ->put(route('transactions.update', $txn), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '200.00',
            'transaction_date' => '2026-09-20',
            'description' => 'Still valid',
        ])
        ->assertRedirect(route('transactions.index'));

    $this->actingAs($user)
        ->put(route('transactions.update', $txn), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '200.00',
            'transaction_date' => '2026-10-01',
        ])
        ->assertSessionHasErrors('transaction_date');

    expect($txn->fresh()->transaction_date->toDateString())->toBe('2026-09-20');
});

test('expense equal to balance is accepted and expense above balance is rejected', function () {
    $user = User::factory()->create();
    fundUser($user, '5000.00');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '5000.00',
            'transaction_date' => '2026-09-10',
            'description' => 'Exact balance',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(AvailableBalance::forUser($user))->toBe('0.00');

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '0.01',
            'transaction_date' => '2026-09-11',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::query()->where('type', 'expense')->count())->toBe(1);
});

test('zero balance blocks any positive expense', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '1.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::count())->toBe(0);
});

test('income is not blocked by balance rule', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '999.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::count())->toBe(1);
});

test('expense edit excludes original amount from available funds', function () {
    $user = User::factory()->create();
    fundUser($user, '10000.00');
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '4000.00',
        'transaction_date' => '2026-09-05',
    ]);
    $editable = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '3000.00',
        'transaction_date' => '2026-09-06',
    ]);

    // Available excluding editable = 10000 - 4000 = 6000. Down to 2500 OK.
    $this->actingAs($user)
        ->put(route('transactions.update', $editable), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '2500.00',
            'transaction_date' => '2026-09-06',
        ])
        ->assertRedirect(route('transactions.index'));

    expect((string) $editable->fresh()->amount)->toBe('2500.00');

    // Up to 6000 OK (exact remaining).
    $this->actingAs($user)
        ->put(route('transactions.update', $editable), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '6000.00',
            'transaction_date' => '2026-09-06',
        ])
        ->assertRedirect(route('transactions.index'));

    // Beyond available (6000 + epsilon) rejected; original untouched beyond last success.
    $this->actingAs($user)
        ->put(route('transactions.update', $editable), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '6000.01',
            'transaction_date' => '2026-09-06',
        ])
        ->assertSessionHasErrors('amount');

    expect((string) $editable->fresh()->amount)->toBe('6000.00');
});

test('create and edit pages expose today and available balance props', function () {
    $user = User::factory()->create();
    fundUser($user, '1234.56');

    $this->actingAs($user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today', '2026-09-26')
            ->where('available_balance', '1234.56')
        );

    $expense = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '200.00',
        'transaction_date' => '2026-09-10',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.edit', $expense))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today', '2026-09-26')
            ->where('available_balance', '1234.56') // excludes the expense being edited
        );
});

test('balance calculation is scoped to authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    fundUser($userA, '100.00');
    fundUser($userB, '99999.00');

    $this->actingAs($userA)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '101.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::query()->where('user_id', $userA->id)->where('type', 'expense')->count())->toBe(0);
});

test('stale client balance cannot bypass server check', function () {
    $user = User::factory()->create();
    fundUser($user, '5000.00');

    // Concurrent drain of funds before expensive submit.
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '4000.00',
        'transaction_date' => '2026-09-09',
    ]);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '4000.00', // would have been OK at earlier UI balance of 5000
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('amount');

    expect(Transaction::query()
        ->where('user_id', $user->id)
        ->whereDate('transaction_date', '2026-09-10')
        ->count())->toBe(0);
});

test('archiving income is rejected when expenses would become uncovered', function () {
    $user = User::factory()->create();
    $income = Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '60.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->delete(route('transactions.destroy', $income))
        ->assertRedirect()
        ->assertSessionHasErrors('transaction');

    expect($income->fresh()->trashed())->toBeFalse();
    expect(AvailableBalance::forUser($user))->toBe('40.00');
});

test('reducing income is rejected when expenses would become uncovered', function () {
    $user = User::factory()->create();
    $income = Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '60.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.edit', $income))
        ->put(route('transactions.update', $income), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '50.00',
            'transaction_date' => '2026-09-01',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('amount');

    expect($income->fresh()->amount)->toBe('100.00');
    expect(AvailableBalance::forUser($user))->toBe('40.00');
});

test('restoring expense is rejected when available balance is insufficient', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-09-01',
    ]);
    $expense = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '40.00',
        'transaction_date' => '2026-09-05',
    ]);
    $expense->delete();

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '80.00',
        'transaction_date' => '2026-09-06',
    ]);

    expect(AvailableBalance::forUser($user))->toBe('20.00');

    $this->actingAs($user)
        ->from(route('transactions.index', ['status' => 'archived']))
        ->post(route('transactions.restore', $expense->id))
        ->assertRedirect()
        ->assertSessionHasErrors('transaction');

    expect($expense->fresh()->trashed())->toBeTrue();
});

test('csv preview rejects future dates and insufficient expenses', function () {
    $user = User::factory()->create();
    fundUser($user, '100.00');

    $csv = UploadedFile::fake()->createWithContent(
        'import.csv',
        "date,type,amount,description,category\n".
        '2026-09-27,expense,10,Future,Food'."\n".
        '2026-09-01,expense,150,Too much,Food'."\n".
        '2026-09-01,expense,50,Ok,Food'."\n"
    );

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.invalid', 2)
            ->where('summary.valid', 1)
        );
});

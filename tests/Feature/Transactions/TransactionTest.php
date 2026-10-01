<?php

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AvailableBalance;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedDefaultCategories();
});

test('guests cannot access transactions', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
});

test('unverified users cannot access transactions', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('verified users can access transactions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk();
});

test('user can create income transaction', function () {
    $user = User::factory()->create();
    $categoryId = defaultCategoryId('income', 'Allowance');

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => $categoryId,
            'amount' => '1500.50',
            'description' => 'Monthly allowance',
            'transaction_date' => '2026-09-01',
            'user_id' => 99999,
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::first();

    expect($transaction)->not->toBeNull();
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->type)->toBe(TransactionType::Income);
    expect($transaction->category_id)->toBe($categoryId);
    expect((string) $transaction->amount)->toBe('1500.50');
    expect($transaction->description)->toBe('Monthly allowance');
});

test('user can create expense transaction', function () {
    $user = User::factory()->create();
    fundUser($user);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '42.00',
            'description' => 'Campus cafe',
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->exists())->toBeTrue();
});

test('new transaction requires category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => '10.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('category_id');
});

test('wrong type category is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '10.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('category_id');
});

test('another users category is rejected', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Category::factory()->for($other)->expense()->create(['name' => 'Secret']);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $foreign->id,
            'amount' => '10.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('category_id');
});

test('own personal category is accepted', function () {
    $user = User::factory()->create();
    fundUser($user);
    $category = Category::factory()->for($user)->expense()->create(['name' => 'Lab Fee']);

    $this->actingAs($user)
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => $category->id,
            'amount' => '15.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertRedirect(route('transactions.index'));

    expect(Transaction::where('category_id', $category->id)->exists())->toBeTrue();
});

test('invalid type is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'deposit',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '10.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('type')
        ->assertRedirect(route('transactions.create'));
});

test('zero amount is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '0',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('amount');
});

test('negative amount is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '-5.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('amount');
});

test('invalid date is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '10.00',
            'transaction_date' => 'not-a-date',
        ])
        ->assertSessionHasErrors('transaction_date');
});

test('overly long description is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '10.00',
            'description' => str_repeat('a', 256),
            'transaction_date' => '2026-09-10',
        ])
        ->assertSessionHasErrors('description');
});

test('user sees only own transactions', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Transaction::factory()->for($userA)->create(['description' => 'User A only']);
    Transaction::factory()->for($userB)->create(['description' => 'User B only']);

    $this->actingAs($userA)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'User A only')
        );
});

test('transactions are ordered newest first', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->create([
        'description' => 'Older',
        'transaction_date' => '2026-08-01',
    ]);
    Transaction::factory()->for($user)->create([
        'description' => 'Newer',
        'transaction_date' => '2026-09-20',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.description', 'Newer')
            ->where('transactions.data.1.description', 'Older')
        );
});

test('income filter works and stays user scoped', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Transaction::factory()->for($user)->income()->create(['description' => 'My income']);
    Transaction::factory()->for($user)->expense()->create(['description' => 'My expense']);
    Transaction::factory()->for($other)->income()->create(['description' => 'Other income']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['type' => 'income']))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'My income')
        );
});

test('expense filter works', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create();
    Transaction::factory()->for($user)->expense()->create(['description' => 'Only expense']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['type' => 'expense']))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Only expense')
        );
});

test('category filter is user scoped', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'description' => 'My food',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'description' => 'My transport',
    ]);
    Transaction::factory()->for($other)->expense()->create([
        'category_id' => $food,
        'description' => 'Other food',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['category_id' => $food]))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'My food')
        );

    $foreign = Category::factory()->for($other)->expense()->create(['name' => 'Hidden']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['category_id' => $foreign->id]))
        ->assertForbidden();
});

test('historical uncategorized transaction remains valid', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create([
        'category_id' => null,
        'description' => 'Legacy',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.description', 'Legacy')
            ->where('transactions.data.0.category', null)
        );

    expect($transaction->fresh()->category_id)->toBeNull();
});

test('editing can assign category', function () {
    $user = User::factory()->create();
    fundUser($user);
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'description' => 'Legacy',
        'amount' => '12.00',
    ]);
    $food = defaultCategoryId('expense', 'Food');

    $this->actingAs($user)
        ->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'category_id' => $food,
            'amount' => '12.00',
            'description' => 'Legacy',
            'transaction_date' => '2026-09-12',
        ])
        ->assertRedirect(route('transactions.index'));

    expect($transaction->fresh()->category_id)->toBe($food);
});

test('date range filter works', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->create([
        'description' => 'In range',
        'transaction_date' => '2026-09-15',
    ]);
    Transaction::factory()->for($user)->create([
        'description' => 'Out of range',
        'transaction_date' => '2026-08-01',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'In range')
        );
});

test('description search works', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->create(['description' => 'Campus cafe lunch']);
    Transaction::factory()->for($user)->create(['description' => 'Bus fare']);

    $this->actingAs($user)
        ->get(route('transactions.index', ['search' => 'cafe']))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Campus cafe lunch')
        );
});

test('pagination works', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->count(16)->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 15)
            ->where('transactions.last_page', 2)
        );
});

test('user can update own transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'amount' => '10.00',
        'description' => 'Old',
        'category_id' => defaultCategoryId('expense', 'Food'),
    ]);

    $this->actingAs($user)
        ->put(route('transactions.update', $transaction), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Gift'),
            'amount' => '25.50',
            'description' => 'Updated',
            'transaction_date' => '2026-09-12',
            'user_id' => User::factory()->create()->id,
        ])
        ->assertRedirect(route('transactions.index'));

    $transaction->refresh();

    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->type)->toBe(TransactionType::Income);
    expect((string) $transaction->amount)->toBe('25.50');
    expect($transaction->description)->toBe('Updated');
});

test('user cannot update another users transaction', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '99.00',
            'transaction_date' => '2026-09-12',
        ])
        ->assertForbidden();
});

test('user can archive own transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Transaction archived successfully.')
        ->assertSessionHas('archived_transaction_id', $transaction->id);

    expect(Transaction::find($transaction->id))->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id)->deleted_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page->has('transactions.data', 0));
});

test('user cannot archive another users transaction', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();

    expect(Transaction::find($transaction->id))->not->toBeNull();
});

test('archived transaction appears in archived filter and not in active totals', function () {
    $user = User::factory()->create();
    fundUser($user, '100.00');

    $active = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-10',
    ]);
    $archived = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '25.00',
        'transaction_date' => '2026-09-11',
        'description' => 'Archived lunch',
    ]);
    $archived->delete();

    expect(AvailableBalance::forUser($user))->toBe('90.00');

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
            ->has('transactions.data', 2)
            ->where('transactions.data', fn ($rows) => collect($rows)->pluck('id')->doesntContain($archived->id)
                && collect($rows)->pluck('id')->contains($active->id))
        );

    $this->actingAs($user)
        ->get(route('transactions.index', ['status' => 'archived']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'archived')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $archived->id)
            ->where('transactions.data.0.is_archived', true)
            ->where('transactions.data.0.description', 'Archived lunch')
        );
});

test('user can restore own archived transaction', function () {
    $user = User::factory()->create();
    fundUser($user, '100.00');
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '15.00',
        'transaction_date' => '2026-09-10',
        'description' => 'Restore me',
    ]);
    $transaction->delete();

    expect(AvailableBalance::forUser($user))->toBe('100.00');

    $this->actingAs($user)
        ->post(route('transactions.restore', $transaction->id))
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Transaction restored successfully.');

    expect(Transaction::find($transaction->id))->not->toBeNull();
    expect(Transaction::find($transaction->id)->deleted_at)->toBeNull();
    expect(Transaction::find($transaction->id)->description)->toBe('Restore me');
    expect(AvailableBalance::forUser($user))->toBe('85.00');

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 2)
        );

    expect(Transaction::withTrashed()->where('description', 'Restore me')->count())->toBe(1);
});

test('user cannot restore another users archived transaction', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();
    $transaction->delete();

    $this->actingAs($attacker)
        ->post(route('transactions.restore', $transaction->id))
        ->assertForbidden();

    expect(Transaction::withTrashed()->find($transaction->id)->trashed())->toBeTrue();
});

test('repeated archive and restore are idempotent and do not duplicate', function () {
    $user = User::factory()->create();
    fundUser($user, '100.00');
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-10',
        'description' => 'Idempotent row',
    ]);

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))->assertRedirect();
    $this->actingAs($user)->delete(route('transactions.destroy', $transaction->id))->assertRedirect();

    expect(Transaction::withTrashed()->where('description', 'Idempotent row')->count())->toBe(1);
    expect(Transaction::withTrashed()->find($transaction->id)->trashed())->toBeTrue();

    $this->actingAs($user)->post(route('transactions.restore', $transaction->id))->assertRedirect();
    $this->actingAs($user)->post(route('transactions.restore', $transaction->id))->assertRedirect();

    expect(Transaction::where('description', 'Idempotent row')->count())->toBe(1);
    expect(Transaction::find($transaction->id)->trashed())->toBeFalse();
});

test('archived transaction cannot be edited until restored', function () {
    $user = User::factory()->create();
    fundUser($user);
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-10',
    ]);
    $transaction->delete();

    $this->actingAs($user)
        ->get(route('transactions.edit', $transaction->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->put(route('transactions.update', $transaction->id), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'amount' => '12.00',
            'transaction_date' => '2026-09-10',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transaction.is_archived', true)
        );
});

test('user can view own transaction details', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '42.00',
        'description' => 'Campus cafe',
        'transaction_date' => '2026-09-10',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/show')
            ->where('transaction.id', $transaction->id)
            ->where('transaction.description', 'Campus cafe')
            ->where('transaction.amount', '42.00')
            ->where('transaction.type', 'expense')
            ->where('transaction.category.name', 'Food')
        );
});

test('user cannot view another users transaction details', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->get(route('transactions.show', $transaction))
        ->assertForbidden();
});

test('user cannot view another users edit page', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $transaction = Transaction::factory()->for($owner)->create();

    $this->actingAs($attacker)
        ->get(route('transactions.edit', $transaction))
        ->assertForbidden();
});

test('transactions index still loads and backend edit route remains available', function () {
    $user = User::factory()->create();
    fundUser($user);
    $transaction = Transaction::factory()->for($user)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-10',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/index')
            ->has('transactions.data')
        );

    // Backend edit entry point is preserved (even though list UI uses View).
    $this->actingAs($user)
        ->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('transactions/edit'));
});

test('soft deleted transaction remains in database with deleted_at', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction));

    $row = DB::table('transactions')->where('id', $transaction->id)->first();

    expect($row)->not->toBeNull();
    expect($row->deleted_at)->not->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id)->amount)->toBe($transaction->amount);
    expect(Transaction::withTrashed()->find($transaction->id)->type)->toBe($transaction->type);
});

test('admin role does not grant cross-user transaction access', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $transaction = Transaction::factory()->for($owner)->create();

    $this->actingAs($admin)
        ->get(route('transactions.edit', $transaction))
        ->assertForbidden();
});

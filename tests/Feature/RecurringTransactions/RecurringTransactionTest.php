<?php

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionOccurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\RecurringApprovalNotification;
use App\Services\RecurringOccurrenceApprovalService;
use App\Services\RecurringTransactionGenerator;
use App\Support\AvailableBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('recurring index renders empty state for user with no rules', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('recurring-transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recurring-transactions/index')
            ->has('recurringTransactions', 0));
});

test('create page provides application today for start date default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('recurring-transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recurring-transactions/create')
            ->where('today', now()->toDateString()));
});

test('past start_date is rejected on create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('recurring-transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '50.00',
            'frequency' => 'monthly',
            'start_date' => '2026-09-27',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('start_date');
});

test('user can create recurring transaction with today or future start', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('recurring-transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '50000.00',
            'description' => 'Monthly allowance',
            'frequency' => 'monthly',
            'start_date' => '2026-09-28',
            'end_date' => null,
            'is_active' => true,
            'user_id' => 999,
        ])
        ->assertRedirect(route('recurring-transactions.index'));

    $rule = RecurringTransaction::first();

    expect($rule)->not->toBeNull();
    expect($rule->user_id)->toBe($user->id);
    expect($rule->next_run_date->toDateString())->toBe('2026-09-28');
    expect($rule->frequency)->toBe(RecurringFrequency::Monthly);
});

test('empty end_date string is accepted as null on create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('recurring-transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '100.00',
            'description' => 'No end',
            'frequency' => 'weekly',
            'start_date' => '2026-09-29',
            'end_date' => '',
            'is_active' => true,
        ])
        ->assertRedirect(route('recurring-transactions.index'));

    $rule = RecurringTransaction::query()->where('user_id', $user->id)->first();

    expect($rule)->not->toBeNull();
    expect($rule->end_date)->toBeNull();
});

test('future start_date is allowed on recurring definitions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('recurring-transactions.store'), [
            'type' => 'income',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '50.00',
            'frequency' => 'monthly',
            'start_date' => '2030-01-15',
            'is_active' => true,
        ])
        ->assertRedirect(route('recurring-transactions.index'));

    $rule = RecurringTransaction::query()->where('user_id', $user->id)->first();

    expect($rule->start_date->toDateString())->toBe('2030-01-15');
    expect($rule->next_run_date->toDateString())->toBe('2030-01-15');
});

test('user can update recurring transaction', function () {
    $user = User::factory()->create();
    $rule = RecurringTransaction::factory()->for($user)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'amount' => '10.00',
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($user)
        ->put(route('recurring-transactions.update', $rule), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('expense', 'Subscriptions'),
            'amount' => '15.00',
            'description' => 'Updated sub',
            'frequency' => 'weekly',
            'start_date' => $rule->start_date->toDateString(),
            'is_active' => true,
        ])
        ->assertRedirect(route('recurring-transactions.index'));

    expect((string) $rule->fresh()->amount)->toBe('15.00');
    expect($rule->fresh()->frequency)->toBe(RecurringFrequency::Weekly);
});

test('user can pause and resume recurring transaction', function () {
    $user = User::factory()->create();
    $rule = RecurringTransaction::factory()->for($user)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'is_active' => true,
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($user)
        ->post(route('recurring-transactions.toggle', $rule))
        ->assertRedirect(route('recurring-transactions.index'));

    expect($rule->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->post(route('recurring-transactions.toggle', $rule))
        ->assertRedirect(route('recurring-transactions.index'));

    expect($rule->fresh()->is_active)->toBeTrue();
});

test('user can delete recurring rule', function () {
    $user = User::factory()->create();
    $rule = RecurringTransaction::factory()->for($user)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($user)
        ->delete(route('recurring-transactions.destroy', $rule))
        ->assertRedirect(route('recurring-transactions.index'));

    expect(RecurringTransaction::find($rule->id))->toBeNull();
    expect(RecurringTransaction::withTrashed()->find($rule->id))->not->toBeNull();
});

test('unauthorized access to recurring denied', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $rule = RecurringTransaction::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($attacker)
        ->get(route('recurring-transactions.edit', $rule))
        ->assertForbidden();

    $this->actingAs($attacker)
        ->delete(route('recurring-transactions.destroy', $rule))
        ->assertForbidden();
});

test('due recurring creates pending occurrence without posting transaction', function () {
    Notification::fake();
    $user = User::factory()->create();
    $categoryId = defaultCategoryId('income', 'Allowance');

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'income',
        'category_id' => $categoryId,
        'amount' => '50000.00',
        'description' => 'Allowance',
        'frequency' => 'monthly',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-01']);

    expect(Transaction::query()->where('user_id', $user->id)->count())->toBe(0);

    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->first();
    expect($occurrence)->not->toBeNull();
    expect($occurrence->status)->toBe(RecurringOccurrenceStatus::PendingApproval);
    expect($occurrence->transaction_id)->toBeNull();
    expect($occurrence->occurrence_date->toDateString())->toBe('2026-09-01');
    expect($rule->fresh()->next_run_date->toDateString())->toBe('2026-10-01');

    Notification::assertSentTo($user, RecurringApprovalNotification::class);
});

test('repeated generation does not duplicate pending occurrences', function () {
    $user = User::factory()->create();

    RecurringTransaction::factory()->for($user)->create([
        'type' => 'expense',
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'amount' => '9.99',
        'frequency' => 'weekly',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-01']);
    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-01']);

    expect(RecurringTransactionOccurrence::count())->toBe(1);
    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(0);
});

test('past transactions remain after recurring rule deletion', function () {
    $user = User::factory()->create();

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-08-01',
    ]);

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'expense',
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'amount' => '9.99',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-01']);
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();
    app(RecurringOccurrenceApprovalService::class)->approve($user, $occurrence);

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);

    $this->actingAs($user)->delete(route('recurring-transactions.destroy', $rule));

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
});

test('monthly date edge cases clamp to last day', function () {
    $generator = app(RecurringTransactionGenerator::class);

    $from = Carbon::parse('2026-01-31');
    $next = $generator->advanceDate($from, RecurringFrequency::Monthly, 31);
    expect($next->toDateString())->toBe('2026-02-28');

    $from = Carbon::parse('2024-01-31');
    $next = $generator->advanceDate($from, RecurringFrequency::Monthly, 31);
    expect($next->toDateString())->toBe('2024-02-29');

    $from = Carbon::parse('2026-02-28');
    $next = $generator->advanceDate($from, RecurringFrequency::Monthly, 31);
    expect($next->toDateString())->toBe('2026-03-31');

    $from = Carbon::parse('2026-03-31');
    $next = $generator->advanceDate($from, RecurringFrequency::Monthly, 30);
    expect($next->toDateString())->toBe('2026-04-30');
});

test('admin cannot access another users recurring rule', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $rule = RecurringTransaction::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($admin)
        ->get(route('recurring-transactions.edit', $rule))
        ->assertForbidden();
});

test('paused recurring rule is not generated', function () {
    $user = User::factory()->create();

    RecurringTransaction::factory()->for($user)->create([
        'type' => 'income',
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => false,
    ]);

    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-01']);

    expect(RecurringTransactionOccurrence::count())->toBe(0);
    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('unaffordable recurring expense still creates pending occurrence without posting', function () {
    $user = User::factory()->create();

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'expense',
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'amount' => '50.00',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    $result = app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-01'));

    expect($result['generated'])->toBe(1);
    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
    expect(RecurringTransactionOccurrence::where('recurring_transaction_id', $rule->id)->count())->toBe(1);
    expect($rule->fresh()->next_run_date->toDateString())->toBe('2026-10-01');
});

test('recurring expense posts only after approve when wallet is funded', function () {
    $user = User::factory()->create();

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'expense',
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'amount' => '25.00',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-01'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('recurring-occurrences.approve', $occurrence))
        ->assertSessionHasErrors('amount');

    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100.00',
        'transaction_date' => '2026-08-15',
    ]);

    $this->actingAs($user)
        ->post(route('recurring-occurrences.approve', $occurrence))
        ->assertRedirect();

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
    expect(AvailableBalance::forUser($user))->toBe('75.00');
});

test('future as-of date is clamped so occurrences are never future dated', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

    $user = User::factory()->create();

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'income',
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10.00',
        'frequency' => 'weekly',
        'start_date' => '2026-09-20',
        'next_run_date' => '2026-09-20',
        'is_active' => true,
    ]);

    Artisan::call('campuscoin:generate-recurring', ['--date' => '2026-09-30']);

    expect(RecurringTransactionOccurrence::count())->toBe(0);
    expect($rule->fresh()->next_run_date->toDateString())->toBe('2026-09-20');
});

test('weekly recurrence advances by one week', function () {
    $generator = app(RecurringTransactionGenerator::class);
    $next = $generator->advanceDate(Carbon::parse('2026-09-01'), RecurringFrequency::Weekly, 1);

    expect($next->toDateString())->toBe('2026-09-08');
    expect($next->dayOfWeek)->toBe(Carbon::parse('2026-09-01')->dayOfWeek);
});

test('income category rejected for expense recurring rule', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('recurring-transactions.store'), [
            'type' => 'expense',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'amount' => '10.00',
            'frequency' => 'monthly',
            'start_date' => '2026-09-28',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('category_id');
});

test('attacker cannot pause another users recurring rule', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $rule = RecurringTransaction::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'type' => 'expense',
        'is_active' => true,
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
    ]);

    $this->actingAs($attacker)
        ->post(route('recurring-transactions.toggle', $rule))
        ->assertForbidden();

    expect($rule->fresh()->is_active)->toBeTrue();
});

test('unexpected occurrence create failure does not advance next_run_date', function () {
    $user = User::factory()->create();

    $rule = RecurringTransaction::factory()->for($user)->create([
        'type' => 'income',
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10.00',
        'frequency' => 'weekly',
        'start_date' => '2026-09-01',
        'next_run_date' => '2026-09-01',
        'is_active' => true,
    ]);

    $event = 'eloquent.creating: '.RecurringTransactionOccurrence::class;
    Event::listen($event, function () {
        throw new RuntimeException('Simulated persistence failure');
    });

    try {
        $result = app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-01'));
    } finally {
        Event::forget($event);
    }

    expect($result['generated'])->toBe(0);
    expect($result['skipped'])->toBe(1);
    expect(RecurringTransactionOccurrence::count())->toBe(0);
    expect($rule->fresh()->next_run_date->toDateString())->toBe('2026-09-01');
});

test('recurring generator is registered on the application schedule', function () {
    $this->artisan('schedule:list')
        ->assertSuccessful()
        ->expectsOutputToContain('campuscoin:generate-recurring');
});

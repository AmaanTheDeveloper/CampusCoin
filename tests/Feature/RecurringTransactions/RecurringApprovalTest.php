<?php

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
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

function makeDueExpenseRule(User $user, string $amount = '3000.00'): RecurringTransaction
{
    return RecurringTransaction::factory()->for($user)->create([
        'type' => 'expense',
        'category_id' => defaultCategoryId('expense', 'Subscriptions'),
        'amount' => $amount,
        'description' => 'Gemini',
        'frequency' => 'monthly',
        'start_date' => '2026-09-28',
        'next_run_date' => '2026-09-28',
        'is_active' => true,
    ]);
}

test('approve creates exactly one transaction and deducts balance once', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $rule = makeDueExpenseRule($user);
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('recurring-occurrences.approve', $occurrence))
        ->assertRedirect();

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
    expect(AvailableBalance::forUser($user))->toBe('7000.00');
    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Approved);
});

test('reject creates no transaction and does not deduct balance', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $rule = makeDueExpenseRule($user);
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)
        ->post(route('recurring-occurrences.reject', $occurrence))
        ->assertRedirect();

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(0);
    expect(AvailableBalance::forUser($user))->toBe('10000.00');
    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::Rejected);
    expect($rule->fresh()->is_active)->toBeTrue();
});

test('rejected occurrence gets next-day reminder and still posts only on approve', function () {
    Notification::fake();

    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $rule = makeDueExpenseRule($user);
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    Notification::assertSentTo($user, RecurringApprovalNotification::class);
    Notification::fake();

    app(RecurringOccurrenceApprovalService::class)->reject($user, $occurrence);

    // Same day: no duplicate reminder
    $remindedSameDay = app(RecurringTransactionGenerator::class)->sendReminders(Carbon::parse('2026-09-28'));
    expect($remindedSameDay)->toBe(0);
    Notification::assertNothingSent();

    Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', config('app.timezone')));
    $reminded = app(RecurringTransactionGenerator::class)->sendReminders(Carbon::parse('2026-09-29'));
    expect($reminded)->toBe(1);
    Notification::assertSentTo($user, RecurringApprovalNotification::class);

    // Reject again day 2
    app(RecurringOccurrenceApprovalService::class)->reject($user, $occurrence->fresh());
    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(0);
    expect(AvailableBalance::forUser($user))->toBe('10000.00');

    Carbon::setTestNow(Carbon::parse('2026-09-30 12:00:00', config('app.timezone')));
    app(RecurringTransactionGenerator::class)->sendReminders(Carbon::parse('2026-09-30'));

    $result = app(RecurringOccurrenceApprovalService::class)->approve($user, $occurrence->fresh());
    expect($result['created'])->toBeTrue();
    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
    expect(AvailableBalance::forUser($user))->toBe('7000.00');

    // Duplicate approve is idempotent
    $again = app(RecurringOccurrenceApprovalService::class)->approve($user, $occurrence->fresh());
    expect($again['created'])->toBeFalse();
    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
    expect(AvailableBalance::forUser($user))->toBe('7000.00');
});

test('approve with insufficient balance fails safely and leaves occurrence pending', function () {
    $user = User::factory()->create();
    $rule = makeDueExpenseRule($user, '50.00');
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)
        ->from(route('recurring-transactions.index'))
        ->post(route('recurring-occurrences.approve', $occurrence))
        ->assertRedirect()
        ->assertSessionHasErrors('amount');

    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::PendingApproval);
    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('user cannot approve or reject another users occurrence', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $rule = makeDueExpenseRule($owner, '10.00');
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($attacker)
        ->post(route('recurring-occurrences.approve', $occurrence))
        ->assertForbidden();

    $this->actingAs($attacker)
        ->post(route('recurring-occurrences.reject', $occurrence))
        ->assertForbidden();

    expect(Transaction::where('user_id', $owner->id)->count())->toBe(0);
    expect($occurrence->fresh()->status)->toBe(RecurringOccurrenceStatus::PendingApproval);
});

test('http duplicate approve posts only one transaction', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '10000.00',
        'transaction_date' => '2026-09-01',
    ]);
    $rule = makeDueExpenseRule($user);
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)->post(route('recurring-occurrences.approve', $occurrence))->assertRedirect();
    $this->actingAs($user)->post(route('recurring-occurrences.approve', $occurrence))->assertRedirect();

    expect(Transaction::where('user_id', $user->id)->where('type', 'expense')->count())->toBe(1);
    expect(AvailableBalance::forUser($user))->toBe('7000.00');
});

test('notifications list includes recurring approve reject actions', function () {
    $user = User::factory()->create();
    $rule = makeDueExpenseRule($user, '10.00');
    app(RecurringTransactionGenerator::class)->generateDue(Carbon::parse('2026-09-28'));
    $occurrence = RecurringTransactionOccurrence::query()->where('recurring_transaction_id', $rule->id)->firstOrFail();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/index')
            ->has('notifications', 1)
            ->where('notifications.0.occurrence_id', $occurrence->id)
            ->where('notifications.0.type', 'recurring_approval'));
});

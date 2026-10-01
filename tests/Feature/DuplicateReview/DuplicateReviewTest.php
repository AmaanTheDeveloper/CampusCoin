<?php

use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Models\Transaction;
use App\Models\TransactionSignal;
use App\Models\User;
use App\Services\Intelligence\DuplicateDetectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedDefaultCategories();
    Storage::fake('local');
});

function duplicateCsv(string $contents, string $name = 'dup.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('guests cannot access duplicate review', function () {
    $this->get(route('duplicate-review.index'))->assertRedirect(route('login'));
});

test('exact duplicate and normalized merchant variations are detected', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => 'McDonalds',
        'transaction_date' => '2026-09-25',
    ]);

    $signals = app(DuplicateDetectionService::class)->evaluate($user, $b);

    expect($signals)->toHaveCount(1);
    expect($signals[0]->signal_type)->toBe(TransactionSignalType::DuplicateTransaction);
    expect($signals[0]->status)->toBe(TransactionSignalStatus::Open);
    expect($signals[0]->reason)->toContain('Same merchant');
    expect(app(DuplicateDetectionService::class)->normalizeDescription("McDonald's"))
        ->toBe(app(DuplicateDetectionService::class)->normalizeDescription('MCDONALDS'));
});

test('same amount different date is not a high-confidence duplicate when window is zero', function () {
    config(['intelligence.duplicate_date_window_days' => 0]);
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    $later = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-28',
    ]);

    expect(app(DuplicateDetectionService::class)->evaluate($user, $later))->toBeEmpty();
});

test('different amount or merchant is not a duplicate', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    $diffAmount = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '650.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    $diffMerchant = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => 'KFC',
        'transaction_date' => '2026-09-25',
    ]);

    expect(app(DuplicateDetectionService::class)->evaluate($user, $diffAmount))->toBeEmpty();
    expect(app(DuplicateDetectionService::class)->evaluate($user, $diffMerchant))->toBeEmpty();
});

test('other users identical transactions never create cross-user candidates', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($other)->expense()->create([
        'category_id' => $food,
        'amount' => '40.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-09-03',
    ]);
    $mine = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '40.00',
        'description' => 'Coffee',
        'transaction_date' => '2026-09-03',
    ]);

    expect(app(DuplicateDetectionService::class)->evaluate($user, $mine))->toBeEmpty();
});

test('fingerprint is direction independent and does not recreate resolved pairs', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'description' => 'Bus',
        'transaction_date' => '2026-09-10',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'description' => 'Bus',
        'transaction_date' => '2026-09-10',
    ]);

    $fromA = app(DuplicateDetectionService::class)->evaluate($user, $a);
    $fromB = app(DuplicateDetectionService::class)->evaluate($user, $b);

    expect(TransactionSignal::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($fromA)->not->toBeEmpty();
    expect($fromB)->not->toBeEmpty();

    $signal = TransactionSignal::query()->where('user_id', $user->id)->first();
    $this->actingAs($user)
        ->post(route('duplicate-review.keep-both', $signal))
        ->assertRedirect(route('duplicate-review.index'));

    expect($signal->fresh()->status)->toBe(TransactionSignalStatus::KeptBoth);
    expect(app(DuplicateDetectionService::class)->evaluate($user, $b))->toBeEmpty();
    expect(TransactionSignal::query()->where('user_id', $user->id)->where('status', 'open')->count())->toBe(0);
});

test('keep both leaves transactions unchanged', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '50.00',
        'description' => 'Snack',
        'transaction_date' => '2026-09-01',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '50.00',
        'description' => 'Snack',
        'transaction_date' => '2026-09-01',
    ]);
    $signals = app(DuplicateDetectionService::class)->evaluate($user, $b);
    $signal = $signals[0];

    $this->actingAs($user)
        ->post(route('duplicate-review.keep-both', $signal))
        ->assertRedirect();

    expect(Transaction::query()->whereKey([$a->id, $b->id])->count())->toBe(2);
    expect($a->fresh()->trashed())->toBeFalse();
    expect($b->fresh()->trashed())->toBeFalse();
});

test('remove duplicate requires confirmation and soft-archives only the selected transaction', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '75.00',
        'description' => 'Cafe',
        'transaction_date' => '2026-09-02',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '75.00',
        'description' => 'Cafe',
        'transaction_date' => '2026-09-02',
    ]);
    $signal = app(DuplicateDetectionService::class)->evaluate($user, $b)[0];

    $this->actingAs($user)
        ->post(route('duplicate-review.remove', $signal), [
            'remove_transaction_id' => $b->id,
        ])
        ->assertSessionHasErrors('confirm');

    $this->actingAs($user)
        ->post(route('duplicate-review.remove', $signal), [
            'remove_transaction_id' => $b->id,
            'confirm' => '1',
        ])
        ->assertRedirect(route('duplicate-review.index'));

    expect($b->fresh()->trashed())->toBeTrue();
    expect($a->fresh()->trashed())->toBeFalse();
    expect($signal->fresh()->status)->toBe(TransactionSignalStatus::RemovedDuplicate);
});

test('user cannot resolve or remove another users duplicate candidate', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $a = Transaction::factory()->for($owner)->expense()->create([
        'category_id' => $food,
        'amount' => '20.00',
        'description' => 'Tea',
        'transaction_date' => '2026-09-05',
    ]);
    $b = Transaction::factory()->for($owner)->expense()->create([
        'category_id' => $food,
        'amount' => '20.00',
        'description' => 'Tea',
        'transaction_date' => '2026-09-05',
    ]);
    $signal = app(DuplicateDetectionService::class)->evaluate($owner, $b)[0];

    $this->actingAs($attacker)
        ->post(route('duplicate-review.keep-both', $signal))
        ->assertForbidden();

    $this->actingAs($attacker)
        ->post(route('duplicate-review.remove', $signal), [
            'remove_transaction_id' => $b->id,
            'confirm' => '1',
        ])
        ->assertForbidden();

    expect($b->fresh()->trashed())->toBeFalse();
    expect($signal->fresh()->status)->toBe(TransactionSignalStatus::Open);
    expect($a->id)->toBeInt();
});

test('csv import detects within-batch and against existing db without blocking import', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '100000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);

    $csv = duplicateCsv(
        "date,type,amount,description,category\n".
        "2026-09-25,expense,850,McDonalds,Food\n".
        "2026-09-25,expense,850,McDonald's,Food\n"
    );

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk();

    $token = session('csv_import.token');

    $this->actingAs($user)
        ->post(route('transactions.import.confirm'), [
            'token' => $token,
            'idempotency_key' => 'dup-import-1',
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_result.possible_duplicates');

    $result = session('import_result');
    expect($result['imported'])->toBe(2);
    expect($result['possible_duplicates'])->toBeGreaterThan(0);

    $open = TransactionSignal::query()
        ->where('user_id', $user->id)
        ->where('signal_type', TransactionSignalType::DuplicateTransaction->value)
        ->where('status', TransactionSignalStatus::Open->value)
        ->count();
    expect($open)->toBeGreaterThan(0);
});

test('re-import does not create endless duplicate alerts for resolved pairs', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $a = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '120.00',
        'description' => 'Bookstore',
        'transaction_date' => '2026-09-12',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '120.00',
        'description' => 'Bookstore',
        'transaction_date' => '2026-09-12',
    ]);
    $signal = app(DuplicateDetectionService::class)->evaluate($user, $b)[0];
    $this->actingAs($user)->post(route('duplicate-review.keep-both', $signal));

    $before = TransactionSignal::query()->where('user_id', $user->id)->count();

    // Re-evaluate same pair — must not recreate open signal.
    expect(app(DuplicateDetectionService::class)->evaluate($user, $a))->toBeEmpty();
    expect(app(DuplicateDetectionService::class)->evaluate($user, $b))->toBeEmpty();
    expect(TransactionSignal::query()->where('user_id', $user->id)->count())->toBe($before);
});

test('edited transaction clears stale open duplicate when no longer matching', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'description' => "McDonald's",
        'transaction_date' => '2026-09-25',
    ]);
    app(DuplicateDetectionService::class)->evaluate($user, $b);
    expect(TransactionSignal::query()->where('status', 'open')->count())->toBe(1);

    $b->update(['amount' => '250.00']);
    app(DuplicateDetectionService::class)->evaluate($user, $b->fresh());

    expect(TransactionSignal::query()->where('status', 'open')->count())->toBe(0);
});

test('archiving a paired transaction resolves open duplicate candidates', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '33.00',
        'description' => 'Parking',
        'transaction_date' => '2026-09-08',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '33.00',
        'description' => 'Parking',
        'transaction_date' => '2026-09-08',
    ]);
    app(DuplicateDetectionService::class)->evaluate($user, $b);

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $b))
        ->assertRedirect();

    expect(TransactionSignal::query()->where('status', 'open')->count())->toBe(0);
});

test('duplicate review page lists pending candidates', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '15.00',
        'description' => 'Pen',
        'transaction_date' => '2026-09-09',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '15.00',
        'description' => 'Pen',
        'transaction_date' => '2026-09-09',
    ]);
    app(DuplicateDetectionService::class)->evaluate($user, $b);

    $this->actingAs($user)
        ->get(route('duplicate-review.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('duplicate-review/index')
            ->where('review.pending_count', 1)
            ->has('review.pending', 1)
        );
});

test('empty description does not create duplicate candidates', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'description' => null,
        'transaction_date' => '2026-09-11',
    ]);
    $b = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'description' => '   ',
        'transaction_date' => '2026-09-11',
    ]);

    expect(app(DuplicateDetectionService::class)->evaluate($user, $b))->toBeEmpty();
});

test('moderate match appears when date window allows adjacent days', function () {
    config(['intelligence.duplicate_date_window_days' => 1]);
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '200.00',
        'description' => 'Uber',
        'transaction_date' => '2026-09-20',
    ]);
    $next = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '200.00',
        'description' => 'Uber',
        'transaction_date' => '2026-09-21',
    ]);

    $signals = app(DuplicateDetectionService::class)->evaluate($user, $next);
    expect($signals)->not->toBeEmpty();
    expect($signals[0]->payload['match_type'] ?? null)->toBe(DuplicateDetectionService::MATCH_MODERATE);
});

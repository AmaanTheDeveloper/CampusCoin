<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedDefaultCategories();
    Storage::fake('local');
});

function makeCsv(string $contents, string $name = 'import.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('valid csv preview does not persist data', function () {
    $user = User::factory()->create();
    $csv = makeCsv("date,type,amount,description,category\n2026-09-01,income,50000,Monthly allowance,Allowance\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('transactions/import-preview')
            ->where('summary.valid', 1)
            ->where('summary.invalid', 0)
        );

    expect(Transaction::count())->toBe(0);
});

test('confirmation persists valid rows', function () {
    $user = User::factory()->create();
    $csv = makeCsv("date,type,amount,description,category\n2026-09-01,income,50000,Monthly allowance,Allowance\n2026-09-02,expense,1200,Lunch,Food\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk();

    $token = session('csv_import.token');
    expect($token)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('transactions.import.confirm'), [
            'token' => $token,
            'idempotency_key' => 'test-key-1',
        ])
        ->assertRedirect(route('transactions.import.create'));

    expect(Transaction::where('user_id', $user->id)->count())->toBe(2);
});

test('invalid extension rejected', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $file])
        ->assertSessionHasErrors('csv');
});

test('oversized file rejected', function () {
    $user = User::factory()->create();
    config(['campuscoin.csv_import.max_size_kb' => 1]);
    $csv = UploadedFile::fake()->create('big.csv', 5, 'text/csv');

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertSessionHasErrors('csv');
});

test('missing headers detected', function () {
    $user = User::factory()->create();
    $csv = makeCsv("foo,bar\n1,2\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertSessionHasErrors('csv');
});

test('invalid date type amount and category detected', function () {
    $user = User::factory()->create();
    fundUser($user, '1000.00');
    $csv = makeCsv(
        "date,type,amount,description,category\n".
        "bad-date,income,10,A,Allowance\n".
        "2026-09-01,deposit,10,B,Allowance\n".
        "2026-09-01,expense,-5,C,Food\n".
        "2026-09-01,expense,10,D,Gym\n"
    );

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.invalid', 3)
            ->where('summary.warnings', 1)
        );
});

test('another users category cannot be referenced by name', function () {
    $user = User::factory()->create();
    fundUser($user, '1000.00');
    $other = User::factory()->create();
    Category::factory()->for($other)->expense()->create(['name' => 'SecretCat']);

    $csv = makeCsv("date,type,amount,description,category\n2026-09-01,expense,10,X,SecretCat\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.warnings', 1)
            ->where('rows.0.category_id', null)
        );
});

test('user_id in csv cannot change ownership', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $csv = makeCsv("date,type,amount,description,category,user_id\n2026-09-01,income,10,Hack,Allowance,{$other->id}\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk();

    $token = session('csv_import.token');

    $this->actingAs($user)
        ->post(route('transactions.import.confirm'), [
            'token' => $token,
            'idempotency_key' => 'own-1',
        ]);

    $transaction = Transaction::first();
    expect($transaction->user_id)->toBe($user->id);
    expect($transaction->user_id)->not->toBe($other->id);
});

test('accidental double submission protected', function () {
    $user = User::factory()->create();
    $csv = makeCsv("date,type,amount,description,category\n2026-09-01,income,10,Once,Allowance\n");

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk();

    $token = session('csv_import.token');

    $this->actingAs($user)->post(route('transactions.import.confirm'), [
        'token' => $token,
        'idempotency_key' => 'same-key',
    ]);

    $this->actingAs($user)->post(route('transactions.import.confirm'), [
        'token' => $token,
        'idempotency_key' => 'same-key',
    ])->assertRedirect(route('transactions.import.create'));

    expect(Transaction::where('user_id', $user->id)->count())->toBe(1);
});

test('result summary accurate', function () {
    $user = User::factory()->create();
    $csv = makeCsv(
        "date,type,amount,description,category\n".
        "2026-09-01,income,10,Good,Allowance\n".
        "bad,expense,10,Bad,Food\n"
    );

    $this->actingAs($user)
        ->post(route('transactions.import.preview'), ['csv' => $csv])
        ->assertOk();

    $token = session('csv_import.token');

    $this->actingAs($user)->post(route('transactions.import.confirm'), [
        'token' => $token,
        'idempotency_key' => 'summary-1',
    ])->assertSessionHas('import_result', function ($result) {
        return $result['imported'] === 1
            && $result['skipped'] === 1
            && $result['errors'] === 1;
    });
});

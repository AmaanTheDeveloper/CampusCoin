<?php

use App\Enums\BudgetAlertType;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\BudgetAlert;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', config('app.timezone')));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access budgets', function () {
    $this->get(route('budgets.index'))->assertRedirect(route('login'));
});

test('unverified users cannot access budgets', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('budgets.index'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('verified user can create expense budget', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'limit_amount' => '10000.00',
            'user_id' => 999,
        ])
        ->assertRedirect(route('budgets.index'));

    $budget = Budget::first();
    expect($budget->user_id)->toBe($user->id);
    expect($budget->month->toDateString())->toBe('2026-09-01');
    expect((string) $budget->limit_amount)->toBe('10000.00');
});

test('income category is rejected for budgets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09-01',
            'category_id' => defaultCategoryId('income', 'Allowance'),
            'limit_amount' => '1000.00',
        ])
        ->assertSessionHasErrors('category_id');
});

test('another users category is rejected for budgets', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Category::factory()->for($other)->expense()->create(['name' => 'Secret']);

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09-01',
            'category_id' => $foreign->id,
            'limit_amount' => '1000.00',
        ])
        ->assertSessionHasErrors('category_id');
});

test('duplicate monthly budget is rejected', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '5000.00',
    ]);

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09-01',
            'category_id' => $food,
            'limit_amount' => '8000.00',
        ])
        ->assertSessionHasErrors('category_id');
});

test('month filter returns empty list without budgets for that month', function () {
    $user = User::factory()->create();

    Budget::factory()->for($user)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    $this->actingAs($user)
        ->get(route('budgets.index', ['month' => '2026-08-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budgets/index')
            ->where('filters.month', '2026-08-01')
            ->has('budgets', 0)
        );
});

test('create page exposes only expense categories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('budgets.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budgets/create')
            ->where('defaultMonth', '2026-09')
            ->has('categories')
            ->where('categories', fn ($categories) => collect($categories)->every(fn ($c) => $c['type'] === 'expense'))
        );
});

test('invalid zero and negative budget amounts are rejected', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09-01',
            'category_id' => $food,
            'limit_amount' => '0',
        ])
        ->assertSessionHasErrors('limit_amount');

    $this->actingAs($user)
        ->post(route('budgets.store'), [
            'month' => '2026-09-01',
            'category_id' => $food,
            'limit_amount' => '-10',
        ])
        ->assertSessionHasErrors('limit_amount');
});

test('user sees own budgets only', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Budget::factory()->for($userA)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);
    Budget::factory()->for($userB)->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'month' => '2026-09-01',
        'limit_amount' => '2000.00',
    ]);

    $this->actingAs($userA)
        ->get(route('budgets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('budgets', 1)
            ->where('budgets.0.category.name', 'Food')
        );
});

test('budget calculations and statuses are correct', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-10',
    ]);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '9999.00',
        'transaction_date' => '2026-09-10',
    ]);
    $deleted = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '4000.00',
        'transaction_date' => '2026-09-11',
    ]);
    $deleted->delete();

    $payload = app(BudgetService::class)->enrichBudgets($user, collect([$budget]))[0];

    expect($payload['spent'])->toBe('5000.00');
    expect($payload['remaining'])->toBe('5000.00');
    expect($payload['percentage'])->toBe(50.0);
    expect($payload['status'])->toBe(BudgetStatus::Healthy->value);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '3500.00',
        'transaction_date' => '2026-09-12',
    ]);
    $near = app(BudgetService::class)->enrichBudgets($user, collect([$budget->fresh()]))[0];
    expect($near['percentage'])->toBe(85.0);
    expect($near['status'])->toBe(BudgetStatus::NearLimit->value);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2000.00',
        'transaction_date' => '2026-09-13',
    ]);
    $exceeded = app(BudgetService::class)->enrichBudgets($user, collect([$budget->fresh()]))[0];
    expect($exceeded['spent'])->toBe('10500.00');
    expect($exceeded['remaining'])->toBe('0.00');
    expect($exceeded['over_budget'])->toBe('500.00');
    expect($exceeded['percentage'])->toBe(105.0);
    expect($exceeded['status'])->toBe(BudgetStatus::Exceeded->value);
});

test('exactly at budget shows zero remaining and zero over budget', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10000.00',
        'transaction_date' => '2026-09-10',
    ]);

    $payload = app(BudgetService::class)->enrichBudgets($user, collect([$budget]))[0];

    expect($payload['spent'])->toBe('10000.00');
    expect($payload['remaining'])->toBe('0.00');
    expect($payload['over_budget'])->toBe('0.00');
    expect($payload['percentage'])->toBe(100.0);
    expect($payload['status'])->toBe(BudgetStatus::Exceeded->value);
});

test('future dated expense in budget month counts toward spent', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '250.00',
        'transaction_date' => '2026-09-28',
    ]);

    $payload = app(BudgetService::class)->enrichBudgets($user, collect([$budget]))[0];
    expect($payload['spent'])->toBe('250.00');
});

test('user can update and delete own budget but not another users', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $budget = Budget::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    $this->actingAs($owner)
        ->put(route('budgets.update', $budget), [
            'month' => '2026-09-01',
            'category_id' => defaultCategoryId('expense', 'Transport'),
            'limit_amount' => '2000.00',
        ])
        ->assertRedirect(route('budgets.index'));

    expect((string) $budget->fresh()->limit_amount)->toBe('2000.00');

    $this->actingAs($attacker)
        ->put(route('budgets.update', $budget), [
            'month' => '2026-09-01',
            'category_id' => defaultCategoryId('expense', 'Food'),
            'limit_amount' => '1.00',
        ])
        ->assertForbidden();

    Transaction::factory()->for($owner)->expense()->create([
        'category_id' => defaultCategoryId('expense', 'Transport'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($owner)
        ->delete(route('budgets.destroy', $budget))
        ->assertRedirect(route('budgets.index'));

    expect(Budget::find($budget->id))->toBeNull();
    expect(Transaction::where('user_id', $owner->id)->count())->toBe(1);

    $other = Budget::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-10-01',
        'limit_amount' => '500.00',
    ]);

    $this->actingAs($attacker)
        ->delete(route('budgets.destroy', $other))
        ->assertForbidden();
});

test('dashboard budget vs actual matches transaction history', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '10000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2500.50',
        'transaction_date' => '2026-09-05',
    ]);
    // Ensure has_transactions for dashboard content
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Allowance'),
        'amount' => '50000.00',
        'transaction_date' => '2026-09-01',
    ]);

    $historySpent = (string) $user->transactions()
        ->where('type', 'expense')
        ->where('category_id', $food)
        ->whereDate('transaction_date', '>=', '2026-09-01')
        ->whereDate('transaction_date', '<', '2026-10-01')
        ->sum('amount');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.budgets', 1)
            ->where('dashboard.budgets.0.spent', number_format((float) $historySpent, 2, '.', ''))
            ->where('dashboard.budgets.0.limit', '10000.00')
            ->where('dashboard.budgets.0.status', 'healthy')
        );
});

test('dashboard shows budget empty state when none exist', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->income()->create([
        'category_id' => defaultCategoryId('income', 'Gift'),
        'amount' => '10.00',
        'transaction_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('dashboard.budgets', []));
});

test('near limit and exceeded alerts are generated once', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '850.00',
        'transaction_date' => '2026-09-05',
    ]);

    $service = app(BudgetService::class);
    $payload = $service->enrichBudgets($user, collect([$budget]));
    $service->evaluateAlerts($user, $payload);
    $service->evaluateAlerts($user, $payload);

    expect(BudgetAlert::where('budget_id', $budget->id)->where('alert_type', BudgetAlertType::NearLimit)->count())->toBe(1);
    expect($user->notifications()->count())->toBe(1);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '200.00',
        'transaction_date' => '2026-09-06',
    ]);

    $payload = $service->enrichBudgets($user, collect([$budget->fresh()]));
    $service->evaluateAlerts($user, $payload);
    $service->evaluateAlerts($user, $payload);

    expect(BudgetAlert::where('budget_id', $budget->id)->count())->toBe(2);
    expect($user->notifications()->count())->toBe(2);
});

test('no alert below near limit threshold', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '700.00',
        'transaction_date' => '2026-09-05',
    ]);

    $service = app(BudgetService::class);
    $payload = $service->enrichBudgets($user, collect([$budget]));
    $service->evaluateAlerts($user, $payload);

    expect(BudgetAlert::count())->toBe(0);
    expect($user->notifications()->count())->toBe(0);
});

test('notifications are user owned and mark read works', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $budget = Budget::factory()->for($userA)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);
    // 85% → near-limit only (exactly one notification for mark-as-read coverage)
    Transaction::factory()->for($userA)->expense()->create([
        'category_id' => $food,
        'amount' => '85.00',
        'transaction_date' => '2026-09-05',
    ]);

    $service = app(BudgetService::class);
    $payload = $service->enrichBudgets($userA, collect([$budget]));
    $service->evaluateAlerts($userA, $payload);

    expect($userA->unreadNotifications()->count())->toBe(1);

    $this->actingAs($userB)
        ->get(route('notifications.index'))
        ->assertInertia(fn ($page) => $page->where('notifications', []));

    $notificationId = $userA->notifications()->first()->id;

    $this->actingAs($userB)
        ->post(route('notifications.read', $notificationId))
        ->assertNotFound();

    $this->actingAs($userA)
        ->post(route('notifications.read', $notificationId))
        ->assertRedirect();

    expect($userA->fresh()->unreadNotifications()->count())->toBe(0);
});
test('mark all notifications as read', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $budget = Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '100.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '120.00',
        'transaction_date' => '2026-09-05',
    ]);

    $service = app(BudgetService::class);
    $service->evaluateAlerts($user, $service->enrichBudgets($user, collect([$budget])));

    expect($user->unreadNotifications()->count())->toBeGreaterThan(0);

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('category with budget cannot be deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->expense()->create(['name' => 'Gym']);
    Budget::factory()->for($user)->create([
        'category_id' => $category->id,
        'month' => '2026-09-01',
        'limit_amount' => '500.00',
    ]);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertSessionHasErrors('category');

    expect(Category::find($category->id))->not->toBeNull();
});

test('budget aggregation avoids n plus one for multiple budgets', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');

    Budget::factory()->for($user)->create(['category_id' => $food, 'month' => '2026-09-01', 'limit_amount' => '1000']);
    Budget::factory()->for($user)->create(['category_id' => $transport, 'month' => '2026-09-01', 'limit_amount' => '1000']);

    foreach (range(1, 40) as $i) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $i % 2 === 0 ? $food : $transport,
            'amount' => '10.00',
            'transaction_date' => '2026-09-10',
        ]);
    }

    $queries = 0;
    DB::listen(fn () => $queries++);

    app(BudgetService::class)->enrichBudgets($user, $user->budgets()->with('category')->get());

    expect($queries)->toBeLessThan(8);
});

test('admin does not see another users budgets', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();

    Budget::factory()->for($owner)->create([
        'category_id' => defaultCategoryId('expense', 'Food'),
        'month' => '2026-09-01',
        'limit_amount' => '1000',
    ]);

    $this->actingAs($admin)
        ->get(route('budgets.index'))
        ->assertInertia(fn ($page) => $page->where('budgets', []));
});

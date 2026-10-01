<?php

use App\Enums\TransactionType;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\TipTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedDefaultCategories();
});

test('admin dashboard exposes kpi aggregates', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $category = Category::query()->whereNull('user_id')->where('type', TransactionType::Expense)->firstOrFail();

    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Expense,
        'category_id' => $category->id,
        'amount' => '25.00',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->has('dashboard.kpis.total_students')
            ->has('dashboard.charts.user_growth')
            ->where('dashboard.kpis.total_students.value', 1)
            ->where('dashboard.kpis.total_transactions.value', 1));
});

test('admin can list and view students', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create(['name' => 'Ada Student']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/users/index')->has('users.data', 1));

    $this->actingAs($admin)
        ->get(route('admin.users.show', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->where('student.email', $student->email));
});

test('admin can disable and enable a student account without deleting data', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $category = Category::query()->whereNull('user_id')->firstOrFail();
    Transaction::factory()->for($student)->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->post(route('admin.users.disable', $student), ['confirm' => true])
        ->assertRedirect();

    expect($student->fresh()->disabled_at)->not->toBeNull();
    expect($student->transactions()->count())->toBe(1);

    auth()->logout();

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $student->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->actingAs($admin)
        ->post(route('admin.users.enable', $student))
        ->assertRedirect();

    expect($student->fresh()->disabled_at)->toBeNull();
});

test('admin reset access sends password reset and keeps financial data', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $category = Category::query()->whereNull('user_id')->firstOrFail();
    Transaction::factory()->for($student)->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->post(route('admin.users.reset-access', $student), ['confirm' => true])
        ->assertRedirect();

    Notification::assertSentTo($student, ResetPassword::class);
    expect($student->transactions()->count())->toBe(1);
});

test('admin can manage system categories only', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $personal = Category::factory()->for($student)->create(['name' => 'Personal Only']);

    $this->actingAs($admin)
        ->post(route('admin.categories.store'), [
            'name' => 'Campus Cafe',
            'type' => TransactionType::Expense->value,
            'is_active' => true,
        ])
        ->assertRedirect();

    $system = Category::query()->whereNull('user_id')->where('name', 'Campus Cafe')->first();
    expect($system)->not->toBeNull();
    expect($system->is_default)->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $personal), [
            'name' => 'Hacked',
            'type' => TransactionType::Expense->value,
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('admin announcements and tip templates crud', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.announcements.store'), [
            'title' => 'Welcome week',
            'message' => 'CampusCoin is live.',
            'priority' => 'normal',
            'status' => 'active',
            'starts_at' => now()->subHour()->toISOString(),
            'ends_at' => now()->addWeek()->toISOString(),
        ])
        ->assertRedirect();

    expect(Announcement::query()->where('title', 'Welcome week')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.saving-tips.store'), [
            'title' => 'Cook at home',
            'body' => 'Batch cook on Sundays.',
            'category_label' => 'Food',
            'impact_level' => 'high',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(TipTemplate::query()->where('title', 'Cook at home')->exists())->toBeTrue();
});

test('admin analytics and intelligence pages load', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.analytics'))->assertOk();
    $this->actingAs($admin)->get(route('admin.intelligence'))->assertOk();
});

test('admin dashboard and analytics load with mixed income and expense transactions', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $expenseCategory = Category::query()->whereNull('user_id')->where('type', TransactionType::Expense)->firstOrFail();
    $incomeCategory = Category::query()->whereNull('user_id')->where('type', TransactionType::Income)->firstOrFail();

    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Income,
        'category_id' => $incomeCategory->id,
        'amount' => '150.00',
        'transaction_date' => now()->subDays(2)->toDateString(),
    ]);
    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Expense,
        'category_id' => $expenseCategory->id,
        'amount' => '40.00',
        'transaction_date' => now()->subDay()->toDateString(),
    ]);
    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Expense,
        'category_id' => $expenseCategory->id,
        'amount' => '25.50',
        'transaction_date' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('dashboard.kpis.total_transactions.value', 3)
            ->has('dashboard.charts.income_vs_expense.points')
            ->has('dashboard.charts.income_vs_expense.income_total')
            ->has('dashboard.charts.income_vs_expense.expense_total'));

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/analytics')
            ->has('charts.income_vs_expense.points')
            ->has('charts.income_vs_expense.income_total')
            ->has('charts.income_vs_expense.expense_total'));
});

test('admin dashboard income versus expense handles transaction type enums without casting errors', function () {
    $student = User::factory()->create();
    $expenseCategory = Category::query()->whereNull('user_id')->where('type', TransactionType::Expense)->firstOrFail();
    $incomeCategory = Category::query()->whereNull('user_id')->where('type', TransactionType::Income)->firstOrFail();

    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Income,
        'category_id' => $incomeCategory->id,
        'amount' => '200.00',
        'transaction_date' => now()->toDateString(),
    ]);
    Transaction::factory()->for($student)->create([
        'type' => TransactionType::Expense,
        'category_id' => $expenseCategory->id,
        'amount' => '75.00',
        'transaction_date' => now()->toDateString(),
    ]);

    $service = app(AdminDashboardService::class);
    // Inclusive upper bound for drivers that compare cast datetimes lexicographically.
    $from = now()->subDay()->startOfDay();
    $to = now()->addDay()->endOfDay();

    $series = $service->incomeVsExpense($from, $to);

    expect($series['income_count'])->toBe(1)
        ->and($series['expense_count'])->toBe(1)
        ->and($series['income_total'])->toBe('200.00')
        ->and($series['expense_total'])->toBe('75.00')
        ->and($series['points'])->not->toBeEmpty();

    $todayPoint = collect($series['points'])->first(
        fn (array $point) => $point['income'] > 0 || $point['expense'] > 0
    );

    expect($todayPoint)->not->toBeNull()
        ->and($todayPoint['income'])->toBe(200.0)
        ->and($todayPoint['expense'])->toBe(75.0);
});

test('admin settings remain inside the admin shell', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertRedirect(route('admin.settings.appearance'));

    $this->actingAs($admin)
        ->get(route('admin.settings.appearance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/appearance'));

    $this->actingAs($admin)
        ->get(route('admin.settings.profile'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/profile'));

    $this->actingAs($admin)
        ->get(route('admin.settings.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/password'));
});

test('students cannot access admin management routes', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.announcements.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.saving-tips.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.analytics'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.intelligence'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.settings.appearance'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.notifications.index'))->assertForbidden();
});

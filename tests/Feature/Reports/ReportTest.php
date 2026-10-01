<?php

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\DashboardAnalyticsService;
use App\Services\ReportService;
use App\Support\MoneyMath;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedDefaultCategories();
    Carbon::setTestNow('2026-09-15 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('guests cannot access reports', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});

test('unverified users cannot access reports', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertRedirect(route('verification.notice'));
});

test('verified users see current month default report', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '10000.00',
        'transaction_date' => '2026-09-02',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '2500.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('report.filters.from', '2026-09-01')
            ->where('report.filters.to', '2026-09-30')
            ->where('report.summary.income', '10000.00')
            ->where('report.summary.expense', '2500.00')
            ->where('report.summary.balance', '7500.00')
            ->where('report.period.week_starts_on', 'monday')
        );
});

test('report totals reconcile with dashboard for current month', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '8000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '3200.00',
        'transaction_date' => '2026-09-10',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '800.00',
        'transaction_date' => '2026-09-20',
    ]);

    $dashboard = app(DashboardAnalyticsService::class)->forUser($user);
    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['summary']['income'])->toBe($dashboard['summary']['income']);
    expect($report['summary']['expense'])->toBe($dashboard['summary']['expense']);
    expect($report['summary']['balance'])->toBe($dashboard['summary']['balance']);
    expect($report['summary']['savings_rate'])->toBe($dashboard['summary']['savings_rate']);
});

test('category breakdown sums to total expenses including uncategorized', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-09-03',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => null,
        'amount' => '40.00',
        'transaction_date' => '2026-09-04',
        'description' => 'Legacy uncategorized',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    $sum = '0.00';
    foreach ($report['category_breakdown'] as $row) {
        $sum = MoneyMath::add($sum, $row['amount']);
    }

    expect($sum)->toBe($report['summary']['expense']);
    expect(collect($report['category_breakdown'])->pluck('name')->all())->toContain('Uncategorized');
});

test('category filter applies to expenses only', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '300.00',
        'transaction_date' => '2026-09-05',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $transport,
        'amount' => '200.00',
        'transaction_date' => '2026-09-06',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['category' => $food]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.income', '5000.00')
            ->where('report.summary.expense', '300.00')
            ->where('report.summary.balance', '4700.00')
        );
});

test('income source filter applies to income only', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $allowance = defaultCategoryId('income', 'Allowance');
    $job = defaultCategoryId('income', 'Part-time Job');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '4000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->income()->create([
        'category_id' => $job,
        'amount' => '1500.00',
        'transaction_date' => '2026-09-02',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '900.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['income_source' => $allowance]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.income', '4000.00')
            ->where('report.summary.expense', '900.00')
        );
});

test('custom date range filters transactions', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-09-05',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '200.00',
        'transaction_date' => '2026-09-20',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['from' => '2026-09-01', 'to' => '2026-09-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.expense', '100.00')
            ->where('report.filters.from', '2026-09-01')
            ->where('report.filters.to', '2026-09-15')
        );
});

test('six month trend is independent of selected date range', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '1000.00',
        'transaction_date' => '2026-08-10',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '50.00',
        'transaction_date' => '2026-09-05',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-15', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['six_month_trend'])->toHaveCount(6);
    expect(collect($report['six_month_trend'])->pluck('month')->all())->toContain('2026-08');
    expect(collect($report['six_month_trend'])->firstWhere('month', '2026-08')['income'])->toBe('1000.00');
    expect($report['summary']['expense'])->toBe('50.00');
});

test('soft deleted transactions are excluded from reports', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    $kept = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '100.00',
        'transaction_date' => '2026-09-05',
    ]);
    $deleted = Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '500.00',
        'transaction_date' => '2026-09-06',
    ]);
    $deleted->delete();

    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['summary']['expense'])->toBe('100.00');
    expect(collect($report['transactions']['data'])->pluck('id')->all())->toContain($kept->id);
    expect(collect($report['transactions']['data'])->pluck('id')->all())->not->toContain($deleted->id);
});

test('another users category is rejected', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $personal = $userB->categories()->create([
        'name' => 'Secret Expense',
        'type' => 'expense',
        'is_default' => false,
    ]);

    $this->actingAs($userA)
        ->get(route('reports.index', ['category' => $personal->id]))
        ->assertSessionHasErrors('category');
});

test('income category cannot be used as category filter', function () {
    $user = User::factory()->create();
    $allowance = defaultCategoryId('income', 'Allowance');

    $this->actingAs($user)
        ->get(route('reports.index', ['category' => $allowance]))
        ->assertSessionHasErrors('category');
});

test('from after to is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index', ['from' => '2026-09-20', 'to' => '2026-09-01']))
        ->assertSessionHasErrors('from');
});

test('weekly summary uses monday week starts and partial weeks', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    // Tuesday Sep 1 2026
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-01',
    ]);
    // Monday Sep 7
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '20.00',
        'transaction_date' => '2026-09-07',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-07', null, false, null);
    $weekly = app(ReportService::class)->weeklyExpenseSummary($user, $filters);

    expect($weekly)->not->toBeEmpty();
    expect($weekly[0]['start'])->toBe('2026-09-01');
    expect($weekly[0]['end'])->toBe('2026-09-06');
    expect($weekly[0]['amount'])->toBe('10.00');
    expect($weekly[1]['start'])->toBe('2026-09-07');
    expect($weekly[1]['amount'])->toBe('20.00');
});

test('zero income yields null savings rate', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '50.00',
        'transaction_date' => '2026-09-05',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['summary']['savings_rate'])->toBeNull();
    expect($report['summary']['balance'])->toBe('-50.00');
});

test('budget actual reconciles with report category expense for same month', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Budget::factory()->for($user)->create([
        'category_id' => $food,
        'month' => '2026-09-01',
        'limit_amount' => '1000.00',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '275.50',
        'transaction_date' => '2026-09-12',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-30', $food, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);
    $budgetPayload = app(BudgetService::class)->currentMonthBudgetVsActual($user);

    expect($report['summary']['expense'])->toBe('275.50');
    expect($budgetPayload[0]['spent'])->toBe('275.50');
});

test('user never sees another users report data', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($userA)->expense()->create([
        'category_id' => $food,
        'amount' => '999.00',
        'transaction_date' => '2026-09-05',
        'description' => 'Secret A',
    ]);

    $this->actingAs($userB)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.summary.expense', '0.00')
            ->where('report.transactions.total', 0)
        );
});

test('pagination preserves filters in query string', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->count(20)->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-10',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['category' => $food, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.filters.category', $food)
            ->where('report.transactions.current_page', 2)
            ->where('report.transactions.per_page', 15)
        );
});

test('empty report disables has_data and export contract fields exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.has_data', false)
            ->has('report.period.generated_label')
            ->has('report.filter_labels')
            ->has('report.period.from')
            ->has('report.period.to')
            ->has('report.six_month_trend', 6)
        );
});

test('filtered report exposes filter labels matching export branding state', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '5000.00',
        'transaction_date' => '2026-09-01',
    ]);
    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '120.00',
        'transaction_date' => '2026-09-08',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-15',
            'category' => $food,
            'income_source' => $allowance,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.has_data', true)
            ->where('report.filters.from', '2026-09-01')
            ->where('report.filters.to', '2026-09-15')
            ->where('report.filters.category', $food)
            ->where('report.filters.income_source', $allowance)
            ->where('report.summary.income', '5000.00')
            ->where('report.summary.expense', '120.00')
            ->has('report.filter_labels')
            ->where('report.period.from', '2026-09-01')
            ->where('report.period.to', '2026-09-15')
        );

    $filters = new ReportFilters('2026-09-01', '2026-09-15', $food, false, $allowance);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['filter_labels'])->not->toBeEmpty();
    expect(collect($report['filter_labels'])->implode(' '))->toContain('Food');
    expect(collect($report['filter_labels'])->implode(' '))->toContain('Allowance');
});

test('report period fields used for export filenames are sanitized date strings', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '10.00',
        'transaction_date' => '2026-09-05',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['period']['from'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($report['period']['to'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($report['period']['from'])->not->toContain('..');
    expect($report['period']['to'])->not->toContain('/');
});

test('large dataset report remains correct without n plus one category loads', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');
    $transport = defaultCategoryId('expense', 'Transport');
    $allowance = defaultCategoryId('income', 'Allowance');

    Transaction::factory()->for($user)->income()->create([
        'category_id' => $allowance,
        'amount' => '20000.00',
        'transaction_date' => '2026-09-01',
    ]);

    foreach (range(1, 250) as $i) {
        Transaction::factory()->for($user)->expense()->create([
            'category_id' => $i % 2 === 0 ? $food : $transport,
            'amount' => '10.00',
            'transaction_date' => '2026-09-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
        ]);
    }

    DB::enableQueryLog();
    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($report['summary']['expense'])->toBe('2500.00');
    expect($report['summary']['income'])->toBe('20000.00');
    expect(count($queries))->toBeLessThan(40);
});

test('admin does not see another users report data', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($student)->expense()->create([
        'category_id' => $food,
        'amount' => '777.00',
        'transaction_date' => '2026-09-05',
    ]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.summary.expense', '0.00'));
});

test('future dated transaction in range is included', function () {
    $user = User::factory()->create();
    $food = defaultCategoryId('expense', 'Food');

    Transaction::factory()->for($user)->expense()->create([
        'category_id' => $food,
        'amount' => '33.00',
        'transaction_date' => '2026-09-28',
    ]);

    $filters = new ReportFilters('2026-09-01', '2026-09-30', null, false, null);
    $report = app(ReportService::class)->forUser($user, $filters);

    expect($report['summary']['expense'])->toBe('33.00');
});

test('report failure returns safe error and logs without stack trace', function () {
    Event::fake([MessageLogged::class]);

    $user = User::factory()->create();

    $this->mock(ReportService::class, function ($mock) {
        $mock->shouldReceive('forUser')->once()->andThrow(new RuntimeException('simulated report failure'));
    });

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('report', null)
            ->where('error', 'Your report data couldn\'t be loaded. Please try again.')
        );

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($user) {
        return $event->level === 'error'
            && $event->message === 'reports.load_failed'
            && ($event->context['user_id'] ?? null) === $user->id
            && ($event->context['exception'] ?? null) === RuntimeException::class
            && ($event->context['message'] ?? null) === 'simulated report failure'
            && isset($event->context['from'], $event->context['to'])
            && ! array_key_exists('trace', $event->context);
    });
});

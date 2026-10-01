<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Enums\TransactionSignalStatus;
use App\Enums\TransactionSignalType;
use App\Enums\TransactionType;
use App\Models\AdminActivityLog;
use App\Models\AiCategoryFeedback;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\TipTemplate;
use App\Models\Transaction;
use App\Models\TransactionSignal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates real system-wide metrics for the Admin Control Center dashboard.
 *
 * Active student definition: role=student, not disabled, and at least one
 * transaction with transaction_date (or created_at fallback) in the last 30 days.
 */
class AdminDashboardService
{
    public const ACTIVE_WINDOW_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function build(string $period = '30d'): array
    {
        $period = $this->normalizePeriod($period);
        [$from, $to, $prevFrom, $prevTo] = $this->periodBounds($period);

        return [
            'period' => $period,
            'period_options' => $this->periodOptions(),
            'active_definition' => 'Students with at least one transaction in the last '.self::ACTIVE_WINDOW_DAYS.' days.',
            'kpis' => $this->kpis($from, $to, $prevFrom, $prevTo),
            'charts' => [
                'user_growth' => $this->userGrowthSeries($from, $to),
                'transaction_activity' => $this->transactionActivitySeries($from, $to, $period),
                'income_vs_expense' => $this->incomeVsExpense($from, $to),
                'category_popularity' => $this->categoryPopularity($from, $to, 8),
            ],
            'alerts' => $this->alerts(),
            'activity' => $this->recentActivity(12),
            'intelligence_summary' => $this->intelligenceSummary(),
            'quick_links' => [
                ['label' => 'Users', 'href' => '/admin/users'],
                ['label' => 'Categories', 'href' => '/admin/categories'],
                ['label' => 'Announcements', 'href' => '/admin/announcements'],
                ['label' => 'Saving Tips', 'href' => '/admin/saving-tips'],
                ['label' => 'Analytics', 'href' => '/admin/analytics'],
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function periodOptions(): array
    {
        return [
            ['value' => '7d', 'label' => '7D'],
            ['value' => '30d', 'label' => '30D'],
            ['value' => '90d', 'label' => '90D'],
            ['value' => '6m', 'label' => '6M'],
            ['value' => '12m', 'label' => '12M'],
        ];
    }

    public function normalizePeriod(string $period): string
    {
        $allowed = ['7d', '30d', '90d', '6m', '12m'];

        return in_array($period, $allowed, true) ? $period : '30d';
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    public function periodBounds(string $period): array
    {
        $to = now()->endOfDay();
        $from = match ($period) {
            '7d' => now()->subDays(6)->startOfDay(),
            '90d' => now()->subDays(89)->startOfDay(),
            '6m' => now()->subMonthsNoOverflow(5)->startOfMonth(),
            '12m' => now()->subMonthsNoOverflow(11)->startOfMonth(),
            default => now()->subDays(29)->startOfDay(),
        };

        $days = max(1, $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return [$from, $to, $prevFrom, $prevTo];
    }

    /**
     * @return array<string, mixed>
     */
    public function kpis(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $students = User::query()->where('role', Role::Student);
        $totalStudents = (clone $students)->count();
        $activeStudents = $this->activeStudentCount();

        $txBase = Transaction::query();
        $totalTransactions = (clone $txBase)->count();
        $incomeCount = (clone $txBase)->where('type', TransactionType::Income)->count();
        $expenseCount = (clone $txBase)->where('type', TransactionType::Expense)->count();

        $periodStudents = User::query()
            ->where('role', Role::Student)
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $prevStudents = User::query()
            ->where('role', Role::Student)
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->count();

        $periodTx = Transaction::query()->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])->count();
        $prevTx = Transaction::query()->whereBetween('transaction_date', [$prevFrom->toDateString(), $prevTo->toDateString()])->count();

        $topCategory = $this->mostUsedCategory();

        return [
            'total_students' => [
                'label' => 'Total Students',
                'value' => $totalStudents,
                'href' => '/admin/users',
                'comparison' => $this->comparison($periodStudents, $prevStudents),
                'supporting' => 'Registered student accounts',
            ],
            'active_students' => [
                'label' => 'Active Students',
                'value' => $activeStudents,
                'href' => '/admin/users?status=active',
                'comparison' => null,
                'supporting' => 'Active in last '.self::ACTIVE_WINDOW_DAYS.' days',
            ],
            'total_transactions' => [
                'label' => 'Total Transactions',
                'value' => $totalTransactions,
                'href' => '/admin/analytics',
                'comparison' => $this->comparison($periodTx, $prevTx),
                'supporting' => 'All recorded transactions',
            ],
            'income_records' => [
                'label' => 'Income Records',
                'value' => $incomeCount,
                'href' => '/admin/analytics',
                'comparison' => null,
                'supporting' => 'System-wide income entries',
            ],
            'expense_records' => [
                'label' => 'Expense Records',
                'value' => $expenseCount,
                'href' => '/admin/analytics',
                'comparison' => null,
                'supporting' => 'System-wide expense entries',
            ],
            'most_used_category' => [
                'label' => 'Most Used Category',
                'value' => $topCategory['name'] ?? '—',
                'numeric_value' => $topCategory['count'] ?? 0,
                'href' => '/admin/categories',
                'comparison' => null,
                'supporting' => $topCategory
                    ? ($topCategory['count'].' transactions · '.$topCategory['type'])
                    : 'Not enough category usage yet',
            ],
        ];
    }

    public function activeStudentCount(): int
    {
        $since = now()->subDays(self::ACTIVE_WINDOW_DAYS)->toDateString();

        return User::query()
            ->where('role', Role::Student)
            ->whereNull('disabled_at')
            ->whereHas('transactions', function ($query) use ($since) {
                $query->whereDate('transaction_date', '>=', $since);
            })
            ->count();
    }

    /**
     * @return array{name: string, count: int, type: string}|null
     */
    public function mostUsedCategory(): ?array
    {
        $row = Transaction::query()
            ->select('category_id', DB::raw('COUNT(*) as usage_count'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByDesc('usage_count')
            ->first();

        if ($row === null) {
            return null;
        }

        $category = Category::query()->find($row->category_id);
        if ($category === null) {
            return null;
        }

        return [
            'name' => $category->name,
            'count' => (int) $row->usage_count,
            'type' => $category->type->value,
        ];
    }

    /**
     * @return array{label: string, percent: float|null, direction: string|null}
     */
    public function comparison(int $current, int $previous): array
    {
        if ($previous <= 0 && $current <= 0) {
            return [
                'label' => 'Not enough history',
                'percent' => null,
                'direction' => null,
            ];
        }

        if ($previous <= 0) {
            return [
                'label' => 'New vs prior period',
                'percent' => null,
                'direction' => 'up',
            ];
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);

        return [
            'label' => 'vs previous period',
            'percent' => $percent,
            'direction' => $percent >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    public function userGrowthSeries(Carbon $from, Carbon $to): array
    {
        $rows = User::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as aggregate'))
            ->where('role', Role::Student)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $cursor = $from->copy()->startOfDay();
        $series = [];
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return list<array{date: string, label: string, count: int}>
     */
    public function transactionActivitySeries(Carbon $from, Carbon $to, string $period): array
    {
        $bucket = in_array($period, ['6m', '12m'], true) ? 'month' : 'day';

        if ($bucket === 'month') {
            $driver = DB::connection()->getDriverName();
            $bucketExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m-01', transaction_date)"
                : "DATE_FORMAT(transaction_date, '%Y-%m-01')";

            $rows = Transaction::query()
                ->select(DB::raw("{$bucketExpr} as bucket"), DB::raw('COUNT(*) as aggregate'))
                ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->pluck('aggregate', 'bucket');

            $cursor = $from->copy()->startOfMonth();
            $series = [];
            while ($cursor->lte($to)) {
                $key = $cursor->format('Y-m-01');
                $series[] = [
                    'date' => $key,
                    'label' => $cursor->format('M Y'),
                    'count' => (int) ($rows[$key] ?? 0),
                ];
                $cursor->addMonthNoOverflow();
            }

            return $series;
        }

        $rows = Transaction::query()
            ->select(DB::raw('DATE(transaction_date) as day'), DB::raw('COUNT(*) as aggregate'))
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $cursor = $from->copy()->startOfDay();
        $series = [];
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $cursor->format('M j'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return array{income_total: string, expense_total: string, income_count: int, expense_count: int, points: list<array{label: string, income: float, expense: float}>}
     */
    public function incomeVsExpense(Carbon $from, Carbon $to): array
    {
        $incomeTotal = (float) Transaction::query()
            ->where('type', TransactionType::Income)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
        $expenseTotal = (float) Transaction::query()
            ->where('type', TransactionType::Expense)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $rows = Transaction::query()
            ->select(
                DB::raw('DATE(transaction_date) as day'),
                'type',
                DB::raw('SUM(amount) as total'),
            )
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('day', 'type')
            ->orderBy('day')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            $day = $row->day;
            $byDay[$day] ??= ['income' => 0.0, 'expense' => 0.0];

            $type = $row->type instanceof TransactionType
                ? $row->type
                : TransactionType::tryFrom((string) $row->type);

            if ($type === TransactionType::Income) {
                $byDay[$day]['income'] = (float) $row->total;
            } else {
                $byDay[$day]['expense'] = (float) $row->total;
            }
        }

        $points = [];
        $cursor = $from->copy()->startOfDay();
        $step = $from->diffInDays($to) > 60 ? 7 : 1;
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $points[] = [
                'label' => $cursor->format('M j'),
                'income' => (float) ($byDay[$key]['income'] ?? 0),
                'expense' => (float) ($byDay[$key]['expense'] ?? 0),
            ];
            $cursor->addDays($step);
        }

        return [
            'income_total' => number_format($incomeTotal, 2, '.', ''),
            'expense_total' => number_format($expenseTotal, 2, '.', ''),
            'income_count' => Transaction::query()
                ->where('type', TransactionType::Income)
                ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'expense_count' => Transaction::query()
                ->where('type', TransactionType::Expense)
                ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
                ->count(),
            'points' => $points,
        ];
    }

    /**
     * @return list<array{id: int, name: string, type: string, count: int, percent: float}>
     */
    public function categoryPopularity(Carbon $from, Carbon $to, int $limit = 8): array
    {
        $rows = Transaction::query()
            ->select('category_id', DB::raw('COUNT(*) as usage_count'))
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('category_id')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();

        $total = max(1, (int) $rows->sum('usage_count'));
        $categoryIds = $rows->pluck('category_id')->all();
        $categories = Category::query()->whereIn('id', $categoryIds)->get()->keyBy('id');

        return $rows->map(function ($row) use ($categories, $total) {
            $category = $categories->get($row->category_id);

            return [
                'id' => (int) $row->category_id,
                'name' => $category?->name ?? 'Unknown',
                'type' => $category?->type->value ?? 'expense',
                'count' => (int) $row->usage_count,
                'percent' => round(((int) $row->usage_count / $total) * 100, 1),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{id: string, severity: string, title: string, detail: string}>
     */
    public function alerts(): array
    {
        $alerts = [];

        $openDuplicates = TransactionSignal::query()
            ->where('signal_type', TransactionSignalType::DuplicateTransaction)
            ->where('status', TransactionSignalStatus::Open)
            ->count();
        if ($openDuplicates >= 10) {
            $alerts[] = [
                'id' => 'open-duplicates',
                'severity' => 'warning',
                'title' => 'Elevated duplicate candidates',
                'detail' => $openDuplicates.' open duplicate signals across the platform.',
            ];
        }

        $openUnusual = TransactionSignal::query()
            ->where('signal_type', TransactionSignalType::LargeTransaction)
            ->where('status', TransactionSignalStatus::Open)
            ->count();
        if ($openUnusual >= 20) {
            $alerts[] = [
                'id' => 'open-unusual',
                'severity' => 'warning',
                'title' => 'Unusual spending volume',
                'detail' => $openUnusual.' open unusual-spend signals awaiting review.',
            ];
        }

        $registrations24h = User::query()
            ->where('role', Role::Student)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $registrationsPrev24h = User::query()
            ->where('role', Role::Student)
            ->whereBetween('created_at', [now()->subDays(2), now()->subDay()])
            ->count();
        if ($registrationsPrev24h > 0 && $registrations24h >= max(5, $registrationsPrev24h * 3)) {
            $alerts[] = [
                'id' => 'registration-spike',
                'severity' => 'info',
                'title' => 'Sudden registration increase',
                'detail' => $registrations24h.' new students in the last 24 hours.',
            ];
        }

        $disabledStudents = User::query()->where('role', Role::Student)->whereNotNull('disabled_at')->count();
        if ($disabledStudents > 0) {
            $alerts[] = [
                'id' => 'disabled-students',
                'severity' => 'info',
                'title' => 'Disabled student accounts',
                'detail' => $disabledStudents.' student account(s) currently disabled.',
            ];
        }

        return $alerts;
    }

    /**
     * @return list<array{id: string, title: string, context: string|null, occurred_at: string, icon: string}>
     */
    public function recentActivity(int $limit = 12): array
    {
        $items = collect();

        AdminActivityLog::query()
            ->with('admin:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (AdminActivityLog $log) use ($items) {
                $items->push([
                    'id' => 'admin-'.$log->id,
                    'title' => $log->description,
                    'context' => $log->admin?->name,
                    'occurred_at' => $log->created_at?->toIso8601String(),
                    'icon' => 'shield',
                    'sort' => $log->created_at?->timestamp ?? 0,
                ]);
            });

        User::query()
            ->where('role', Role::Student)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'created_at'])
            ->each(function (User $user) use ($items) {
                $items->push([
                    'id' => 'student-'.$user->id,
                    'title' => 'New student registered',
                    'context' => $user->name,
                    'occurred_at' => $user->created_at?->toIso8601String(),
                    'icon' => 'user',
                    'sort' => $user->created_at?->timestamp ?? 0,
                ]);
            });

        Announcement::query()
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'status', 'updated_at'])
            ->each(function (Announcement $announcement) use ($items) {
                $items->push([
                    'id' => 'announcement-'.$announcement->id,
                    'title' => 'Announcement updated',
                    'context' => $announcement->title.' · '.$announcement->status->value,
                    'occurred_at' => $announcement->updated_at?->toIso8601String(),
                    'icon' => 'megaphone',
                    'sort' => $announcement->updated_at?->timestamp ?? 0,
                ]);
            });

        TipTemplate::query()
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'title', 'updated_at'])
            ->each(function (TipTemplate $tip) use ($items) {
                $items->push([
                    'id' => 'tip-'.$tip->id,
                    'title' => 'Saving tip template updated',
                    'context' => $tip->title,
                    'occurred_at' => $tip->updated_at?->toIso8601String(),
                    'icon' => 'lightbulb',
                    'sort' => $tip->updated_at?->timestamp ?? 0,
                ]);
            });

        return $items
            ->sortByDesc('sort')
            ->take($limit)
            ->values()
            ->map(fn (array $item) => collect($item)->except('sort')->all())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function intelligenceSummary(): array
    {
        $total = AiCategoryFeedback::query()->count();
        $accepted = AiCategoryFeedback::query()->where('was_accepted', true)->count();
        $corrected = AiCategoryFeedback::query()->where('was_accepted', false)->count();
        $rate = $total > 0 ? round(($accepted / $total) * 100, 1) : null;

        $openDuplicates = TransactionSignal::query()
            ->where('signal_type', TransactionSignalType::DuplicateTransaction)
            ->where('status', TransactionSignalStatus::Open)
            ->count();
        $openUnusual = TransactionSignal::query()
            ->where('signal_type', TransactionSignalType::LargeTransaction)
            ->where('status', TransactionSignalStatus::Open)
            ->count();

        return [
            'has_data' => $total > 0 || $openDuplicates > 0 || $openUnusual > 0,
            'suggestions' => $total,
            'accepted' => $accepted,
            'corrected' => $corrected,
            'acceptance_rate' => $rate,
            'open_duplicates' => $openDuplicates,
            'open_unusual' => $openUnusual,
            'href' => '/admin/intelligence',
        ];
    }
}

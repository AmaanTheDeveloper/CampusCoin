<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAnalyticsController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): Response
    {
        $period = $dashboard->normalizePeriod((string) $request->query('period', '30d'));
        [$from, $to, $prevFrom, $prevTo] = $dashboard->periodBounds($period);
        $payload = $dashboard->build($period);

        return Inertia::render('admin/analytics', [
            'period' => $period,
            'period_options' => $dashboard->periodOptions(),
            'summary' => [
                'total_students' => $payload['kpis']['total_students']['value'],
                'active_students' => $payload['kpis']['active_students']['value'],
                'transactions' => $payload['kpis']['total_transactions']['value'],
                'income_total' => $payload['charts']['income_vs_expense']['income_total'],
                'expense_total' => $payload['charts']['income_vs_expense']['expense_total'],
            ],
            'charts' => $payload['charts'],
            'comparisons' => [
                'students' => $dashboard->comparison(
                    User::query()->where('role', Role::Student)->whereBetween('created_at', [$from, $to])->count(),
                    User::query()->where('role', Role::Student)->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                ),
            ],
            'active_definition' => $payload['active_definition'],
        ]);
    }
}

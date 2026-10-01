<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): Response
    {
        $period = $dashboard->normalizePeriod((string) $request->query('period', '30d'));

        return Inertia::render('admin/dashboard', [
            'dashboard' => $dashboard->build($period),
        ]);
    }
}

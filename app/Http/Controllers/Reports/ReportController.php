<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Services\ReportService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request, ReportService $reports): Response
    {
        $filters = $request->filters();

        try {
            $report = $reports->forUser(
                $request->user(),
                $filters,
                (int) $request->integer('page', 1),
            );
        } catch (Throwable $e) {
            Log::error('reports.load_failed', [
                'user_id' => $request->user()?->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'from' => $filters->from,
                'to' => $filters->to,
            ]);

            return Inertia::render('reports/index', [
                'report' => null,
                'error' => 'Your report data couldn\'t be loaded. Please try again.',
            ]);
        }

        return Inertia::render('reports/index', [
            'report' => $report,
            'error' => null,
        ]);
    }
}

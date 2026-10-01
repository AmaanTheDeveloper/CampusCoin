<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminIntelligenceService;
use Inertia\Inertia;
use Inertia\Response;

class AdminIntelligenceController extends Controller
{
    public function __invoke(AdminIntelligenceService $intelligence): Response
    {
        return Inertia::render('admin/intelligence', [
            'intelligence' => $intelligence->build(),
        ]);
    }
}

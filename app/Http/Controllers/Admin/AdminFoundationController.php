<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minimal admin authorization entry point.
 * Not an admin dashboard — Phase 10 will deliver admin UI.
 */
class AdminFoundationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/foundation');
    }
}

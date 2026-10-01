<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYear;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.appearance');
    }

    public function appearance(): Response
    {
        return Inertia::render('admin/settings/appearance');
    }

    public function profile(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('admin/settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'academicYearOptions' => AcademicYear::options(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'academic_year' => $user->academic_year?->value,
                'monthly_allowance' => $user->monthly_allowance,
                'savings_goal' => $user->savings_goal,
                'email_verified_at' => $user->email_verified_at,
            ],
        ]);
    }

    public function password(Request $request): Response
    {
        return Inertia::render('admin/settings/password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }
}

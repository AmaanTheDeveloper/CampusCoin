<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
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

    /**
     * Update the authenticated user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($user->isAdmin() && str_contains((string) url()->previous(), '/admin/settings')) {
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);
        } else {
            $user->fill($validated);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->isAdmin() && str_contains((string) url()->previous(), '/admin/settings')) {
            return to_route('admin.settings.profile');
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

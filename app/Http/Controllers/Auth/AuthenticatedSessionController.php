<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user?->isAdmin()) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        if ($user?->isBankUser()) {
            $bank = $user->primaryBank();
            if ($bank === null || ! $bank->isActive()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = match ($bank?->status?->value) {
                    'pending', 'under_review' => 'Your bank registration is still pending verification. A platform administrator must approve it before you can open the Bank Portal.',
                    'rejected' => 'Your bank registration was rejected.'.($bank->rejection_reason ? ' '.$bank->rejection_reason : ''),
                    'suspended' => 'Your bank account is currently suspended. Please contact the platform administrator.',
                    default => 'Your bank partnership is not active. Please contact the platform administrator.',
                };

                return redirect()
                    ->route('login')
                    ->with('status', $message);
            }

            return redirect()->intended(route('bank.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

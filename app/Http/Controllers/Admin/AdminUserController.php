<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $users = User::query()
            ->where('role', Role::Student)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->whereNull('disabled_at'))
            ->when($status === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
            ->withCount([
                'transactions',
                'transactions as income_count' => fn ($q) => $q->where('type', TransactionType::Income),
                'transactions as expense_count' => fn ($q) => $q->where('type', TransactionType::Expense),
            ])
            ->withMax('transactions as last_transaction_at', 'transaction_date')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->isDisabled() ? 'disabled' : 'active',
                'transactions_count' => $user->transactions_count,
                'income_count' => $user->income_count,
                'expense_count' => $user->expense_count,
                'joined_at' => $user->created_at?->toIso8601String(),
                'last_activity_at' => $user->last_transaction_at,
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['all', 'active', 'disabled'], true) ? $status : 'all',
            ],
        ]);
    }

    public function show(User $user): Response
    {
        abort_unless($user->isStudent(), 404);

        $incomeCount = $user->transactions()->where('type', TransactionType::Income)->count();
        $expenseCount = $user->transactions()->where('type', TransactionType::Expense)->count();
        $lastActivity = $user->transactions()->max('transaction_date');

        return Inertia::render('admin/users/show', [
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->isDisabled() ? 'disabled' : 'active',
                'joined_at' => $user->created_at?->toIso8601String(),
                'disabled_at' => $user->disabled_at?->toIso8601String(),
                'last_activity_at' => $lastActivity,
                'transactions_count' => $user->transactions()->count(),
                'income_count' => $incomeCount,
                'expense_count' => $expenseCount,
                'academic_year' => $user->academic_year?->value,
            ],
        ]);
    }

    public function disable(Request $request, User $user, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        abort_unless($user->isStudent(), 404);

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        if ($user->isDisabled()) {
            return back()->with('success', 'Account is already disabled.');
        }

        $user->forceFill(['disabled_at' => now()])->save();

        $logger->log($request->user(), 'user.disable', "Disabled student account {$user->email}", $user);

        $notifications->notifyAdmins(
            eventType: 'student.disabled',
            title: 'Student account disabled',
            message: "{$user->name}'s account was disabled. Financial history was preserved.",
            severity: 'warning',
            actionUrl: '/admin/users/'.$user->id,
            actionLabel: 'View student',
        );

        return back()->with('success', 'Student account disabled. Financial history was preserved.');
    }

    public function enable(Request $request, User $user, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        abort_unless($user->isStudent(), 404);

        if (! $user->isDisabled()) {
            return back()->with('success', 'Account is already active.');
        }

        $user->forceFill(['disabled_at' => null])->save();

        $logger->log($request->user(), 'user.enable', "Enabled student account {$user->email}", $user);

        $notifications->notifyAdmins(
            eventType: 'student.enabled',
            title: 'Student account enabled',
            message: "{$user->name}'s account was re-enabled.",
            severity: 'success',
            actionUrl: '/admin/users/'.$user->id,
            actionLabel: 'View student',
        );

        return back()->with('success', 'Student account enabled.');
    }

    public function resetAccess(Request $request, User $user, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        abort_unless($user->isStudent(), 404);

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['reset' => __($status)]);
        }

        $logger->log($request->user(), 'user.reset_access', "Sent password reset for {$user->email}", $user);

        $notifications->notifyAdmins(
            eventType: 'student.reset_access',
            title: 'Password reset sent',
            message: "A password reset email was sent for {$user->name}.",
            severity: 'info',
            actionUrl: '/admin/users/'.$user->id,
            actionLabel: 'View student',
        );

        return back()->with('success', 'Password reset email sent. Financial data was not modified.');
    }
}

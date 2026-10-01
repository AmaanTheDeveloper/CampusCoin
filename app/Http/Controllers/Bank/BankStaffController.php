<?php

namespace App\Http\Controllers\Bank;

use App\Enums\Loans\BankStaffRole;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankMembership;
use App\Models\User;
use App\Services\Loans\LoanAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class BankStaffController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');

        $membership = $request->user()?->bankMemberships()
            ->where('bank_id', $bank->id)
            ->where('is_active', true)
            ->first();

        abort_unless(
            $membership && in_array($membership->staff_role, [BankStaffRole::Owner, BankStaffRole::Admin], true),
            403,
        );

        $staff = $bank->memberships()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (BankMembership $row) => [
                'id' => $row->id,
                'staff_role' => $row->staff_role->value,
                'is_active' => $row->is_active,
                'user' => $row->user?->only(['id', 'name', 'email']),
            ]);

        return Inertia::render('bank/staff/index', [
            'eyebrow' => 'Staff',
            'title' => 'Bank staff',
            'description' => 'Invite reviewers and admins for your bank only.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'staff' => $staff,
            'can_manage' => true,
        ]);
    }

    public function store(Request $request, LoanAuditService $audit): RedirectResponse
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');

        $membership = $request->user()?->bankMemberships()
            ->where('bank_id', $bank->id)
            ->where('is_active', true)
            ->first();

        abort_unless(
            $membership && in_array($membership->staff_role, [BankStaffRole::Owner, BankStaffRole::Admin], true),
            403,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'staff_role' => ['required', Rule::in(['admin', 'officer'])],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->forceFill([
            'role' => Role::Bank,
            'email_verified_at' => now(),
        ])->save();

        BankMembership::query()->create([
            'bank_id' => $bank->id,
            'user_id' => $user->id,
            'staff_role' => BankStaffRole::from($validated['staff_role']),
            'is_active' => true,
        ]);

        $audit->log($request->user(), 'bank.staff_invited', $bank, $user->email, $bank->id);

        return back()->with('success', "Invited {$user->name} as {$validated['staff_role']}.");
    }
}

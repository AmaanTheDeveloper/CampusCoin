<?php

namespace App\Http\Controllers\Admin\Banks;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\Loans\BankRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBankController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $statusCounts = [
            'pending' => Bank::query()->where('status', 'pending')->count(),
            'approved' => Bank::query()->where('status', 'approved')->count(),
            'rejected' => Bank::query()->where('status', 'rejected')->count(),
            'suspended' => Bank::query()->where('status', 'suspended')->count(),
        ];

        $banks = Bank::query()
            ->withCount(['products', 'applications', 'loans', 'memberships', 'verificationDocuments'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('official_email', 'like', "%{$search}%")
                        ->orWhere('representative_email', 'like', "%{$search}%")
                        ->orWhere('reference_code', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Bank $bank) => [
                'id' => $bank->id,
                'name' => $bank->name,
                'slug' => $bank->slug,
                'reference_code' => $bank->reference_code,
                'official_email' => $bank->official_email,
                'status' => $bank->status->value,
                'status_label' => $bank->status->label(),
                'authorized_representative' => $bank->authorized_representative,
                'products_count' => $bank->products_count,
                'applications_count' => $bank->applications_count,
                'loans_count' => $bank->loans_count,
                'memberships_count' => $bank->memberships_count,
                'documents_count' => $bank->verification_documents_count,
                'created_at' => $bank->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/banks/index', [
            'eyebrow' => 'Admin',
            'title' => 'Partner banks',
            'description' => 'Review bank registration requests and manage active partners.',
            'banks' => $banks,
            'status_counts' => $statusCounts,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Bank $bank): Response
    {
        $bank->load([
            'verificationDocuments',
            'memberships.user:id,name,email',
            'approvedBy:id,name,email',
        ]);
        $bank->loadCount(['products', 'applications', 'loans']);

        return Inertia::render('admin/banks/show', [
            'eyebrow' => 'Admin',
            'title' => $bank->name,
            'description' => 'Review registration details and verification documents.',
            'bank' => [
                'id' => $bank->id,
                'name' => $bank->name,
                'slug' => $bank->slug,
                'reference_code' => $bank->reference_code,
                'official_email' => $bank->official_email,
                'phone' => $bank->phone,
                'address' => $bank->address,
                'city' => $bank->city,
                'country' => $bank->country,
                'registration_number' => $bank->registration_number,
                'authorized_representative' => $bank->authorized_representative,
                'representative_email' => $bank->representative_email,
                'representative_phone' => $bank->representative_phone,
                'status' => $bank->status->value,
                'status_label' => $bank->status->label(),
                'rejection_reason' => $bank->rejection_reason,
                'suspension_reason' => $bank->suspension_reason,
                'approved_at' => $bank->approved_at?->toIso8601String(),
                'suspended_at' => $bank->suspended_at?->toIso8601String(),
                'created_at' => $bank->created_at?->toIso8601String(),
                'approved_by' => $bank->approvedBy?->only(['id', 'name', 'email']),
                'products_count' => $bank->products_count,
                'applications_count' => $bank->applications_count,
                'loans_count' => $bank->loans_count,
                'memberships' => $bank->memberships->map(fn ($m) => [
                    'id' => $m->id,
                    'staff_role' => $m->staff_role->value,
                    'is_active' => $m->is_active,
                    'user' => $m->user?->only(['id', 'name', 'email']),
                ]),
                'verification_documents' => $bank->verificationDocuments->map(fn ($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'original_name' => $doc->original_name,
                    'mime_type' => $doc->mime_type,
                    'size_bytes' => $doc->size_bytes,
                    'status' => $doc->status,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function approve(
        Request $request,
        Bank $bank,
        BankRegistrationService $registration,
    ): RedirectResponse {
        $registration->approve($bank, $request->user());

        return back()->with('success', 'Bank approved.');
    }

    public function reject(
        Request $request,
        Bank $bank,
        BankRegistrationService $registration,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $registration->reject($bank, $request->user(), $data['reason']);

        return back()->with('success', 'Bank rejected.');
    }

    public function suspend(
        Request $request,
        Bank $bank,
        BankRegistrationService $registration,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $registration->suspend($bank, $request->user(), $data['reason']);

        return back()->with('success', 'Bank suspended.');
    }

    public function reactivate(
        Request $request,
        Bank $bank,
        BankRegistrationService $registration,
    ): RedirectResponse {
        $registration->reactivate($bank, $request->user());

        return back()->with('success', 'Bank reactivated.');
    }
}

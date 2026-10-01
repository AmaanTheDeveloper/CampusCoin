<?php

namespace App\Http\Controllers\Bank;

use App\Enums\Loans\LoanApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\LoanApplication;
use App\Services\Loans\LoanWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $applications = $bank->applications()
            ->with(['user:id,name,email', 'product:id,name'])
            ->when(
                $status !== 'all' && LoanApplicationStatus::tryFrom($status),
                fn ($q) => $q->where('status', $status),
            )
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('reference', 'like', $like)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like))
                        ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LoanApplication $application) => [
                'id' => $application->id,
                'reference' => $application->reference,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'requested_amount' => (string) $application->requested_amount,
                'requested_tenure_months' => $application->requested_tenure_months,
                'student' => [
                    'id' => $application->user?->id,
                    'name' => $application->user?->name,
                    'email' => $application->user?->email,
                ],
                'product' => $application->product?->name,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('bank/applications/index', [
            'eyebrow' => 'Applications',
            'title' => 'Loan applications',
            'description' => 'Review and decide on student loan applications.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'applications' => $applications,
            'filters' => [
                'status' => LoanApplicationStatus::tryFrom($status)?->value ?? 'all',
                'search' => $search,
            ],
        ]);
    }

    public function show(Request $request, LoanApplication $application): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankApplication($bank, $application);

        $application->load([
            'user:id,name,email',
            'product',
            'documents',
            'events' => fn ($q) => $q->latest('created_at')->limit(30),
            'loan:id,loan_application_id,reference,status',
        ]);

        return Inertia::render('bank/applications/show', [
            'eyebrow' => 'Applications',
            'title' => $application->reference,
            'description' => 'Review documents, notes, and take an underwriting action.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'application' => [
                'id' => $application->id,
                'reference' => $application->reference,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'requested_amount' => (string) $application->requested_amount,
                'requested_tenure_months' => $application->requested_tenure_months,
                'purpose' => $application->purpose,
                'applicant_notes' => $application->applicant_notes,
                'rejection_reason' => $application->rejection_reason,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'decided_at' => $application->decided_at?->toIso8601String(),
                'student' => [
                    'id' => $application->user?->id,
                    'name' => $application->user?->name,
                    'email' => $application->user?->email,
                ],
                'product' => $application->product ? [
                    'id' => $application->product->id,
                    'name' => $application->product->name,
                    'annual_rate_percent' => (string) $application->product->annual_rate_percent,
                ] : null,
                'documents' => $application->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'original_name' => $doc->original_name,
                    'status' => $doc->status,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ]),
                'events' => $application->events->map(fn ($event) => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'note' => $event->note,
                    'created_at' => $event->created_at?->toIso8601String(),
                ]),
                'loan_id' => $application->loan?->id,
                'loan_reference' => $application->loan?->reference,
            ],
        ]);
    }

    public function underReview(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankApplication($bank, $application);

        $workflow->markUnderReview($application, $request->user());

        return back()->with('success', 'Application marked under review.');
    }

    public function requestInfo(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankApplication($bank, $application);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $workflow->requestMoreInformation($application, $request->user(), $validated['note']);

        return back()->with('success', 'Requested more information from the student.');
    }

    public function approve(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankApplication($bank, $application);

        $loan = $workflow->approve($application, $request->user());

        return redirect()
            ->route('bank.loans.show', $loan)
            ->with('success', "Application approved. Loan {$loan->reference} created.");
    }

    public function reject(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankApplication($bank, $application);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $workflow->reject($application, $request->user(), $validated['reason']);

        return back()->with('success', 'Application rejected.');
    }

    private function assertBankApplication(Bank $bank, LoanApplication $application): void
    {
        abort_unless($application->bank_id === $bank->id, 404);
    }
}

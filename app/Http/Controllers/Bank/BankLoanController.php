<?php

namespace App\Http\Controllers\Bank;

use App\Enums\Loans\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Loan;
use App\Services\Loans\LoanWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankLoanController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $status = (string) $request->query('status', 'all');

        $loans = $bank->loans()
            ->with(['user:id,name,email', 'product:id,name'])
            ->when(
                $status !== 'all' && LoanStatus::tryFrom($status),
                fn ($q) => $q->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Loan $loan) => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'principal_amount' => (string) $loan->principal_amount,
                'outstanding_balance' => (string) $loan->outstanding_balance,
                'student' => $loan->user?->name,
                'product' => $loan->product?->name,
                'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
            ]);

        return Inertia::render('bank/loans/index', [
            'eyebrow' => 'Loans',
            'title' => 'Booked loans',
            'description' => 'Track agreements, disbursements, and active student loans.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'loans' => $loans,
            'filters' => [
                'status' => LoanStatus::tryFrom($status)?->value ?? 'all',
            ],
        ]);
    }

    public function show(Request $request, Loan $loan): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankLoan($bank, $loan);

        $loan->load([
            'user:id,name,email',
            'product:id,name,annual_rate_percent',
            'installments',
            'disbursement',
            'payments' => fn ($q) => $q->latest()->limit(20),
        ]);

        return Inertia::render('bank/loans/show', [
            'eyebrow' => 'Loans',
            'title' => $loan->reference,
            'description' => 'Loan schedule, disbursement status, and recent payments.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'loan' => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'principal_amount' => (string) $loan->principal_amount,
                'tenure_months' => $loan->tenure_months,
                'annual_rate_percent' => (string) $loan->annual_rate_percent,
                'installment_amount' => (string) $loan->installment_amount,
                'total_repayable' => (string) $loan->total_repayable,
                'outstanding_balance' => (string) $loan->outstanding_balance,
                'agreement_accepted_at' => $loan->agreement_accepted_at?->toIso8601String(),
                'disbursed_at' => $loan->disbursed_at?->toIso8601String(),
                'completed_at' => $loan->completed_at?->toIso8601String(),
                'student' => [
                    'id' => $loan->user?->id,
                    'name' => $loan->user?->name,
                    'email' => $loan->user?->email,
                ],
                'product' => $loan->product?->name,
                'disbursement' => $loan->disbursement ? [
                    'id' => $loan->disbursement->id,
                    'amount' => (string) $loan->disbursement->amount,
                    'status' => $loan->disbursement->status?->value ?? $loan->disbursement->status,
                    'reference' => $loan->disbursement->reference,
                    'confirmed_at' => $loan->disbursement->confirmed_at?->toIso8601String(),
                    'finance_posted' => $loan->disbursement->transaction_id !== null,
                ] : null,
                'installments' => $loan->installments->map(fn ($row) => [
                    'id' => $row->id,
                    'installment_number' => $row->installment_number,
                    'due_date' => $row->due_date?->toDateString(),
                    'amount_due' => (string) $row->amount_due,
                    'amount_paid' => (string) $row->amount_paid,
                    'status' => $row->status->value,
                    'status_label' => $row->status->label(),
                ]),
                'payments' => $loan->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => (string) $payment->amount,
                    'status' => $payment->status->value,
                    'status_label' => $payment->status->label(),
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function confirmDisbursement(
        Request $request,
        Loan $loan,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankLoan($bank, $loan);

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:255'],
        ]);

        $workflow->confirmDisbursement(
            $loan,
            $request->user(),
            $validated['reference'] ?? null,
            $validated['destination'] ?? null,
        );

        return back()->with('success', 'Disbursement confirmed. Loan is active and a loan inflow was posted to the student finance ledger.');
    }

    private function assertBankLoan(Bank $bank, Loan $loan): void
    {
        abort_unless($loan->bank_id === $bank->id, 404);
    }
}

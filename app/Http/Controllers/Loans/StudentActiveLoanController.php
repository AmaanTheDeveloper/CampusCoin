<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Services\Loans\LoanWorkflowService;
use App\Services\Loans\Payments\LoanPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentActiveLoanController extends Controller
{
    public function show(Request $request, Loan $loan): Response
    {
        $this->assertOwner($request, $loan);

        $loan->load([
            'product:id,name,allows_partial_payments,annual_rate_percent',
            'bank:id,name',
            'installments',
            'disbursement',
            'payments' => fn ($q) => $q->latest()->limit(20),
        ]);

        return Inertia::render('loans/active/show', [
            'eyebrow' => 'Active loan',
            'title' => $loan->reference,
            'description' => 'Accept the agreement, review your schedule, and pay installments.',
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
                'product' => $loan->product ? [
                    'id' => $loan->product->id,
                    'name' => $loan->product->name,
                    'allows_partial_payments' => $loan->product->allows_partial_payments,
                ] : null,
                'bank' => $loan->bank?->only(['id', 'name']),
                'disbursement' => $loan->disbursement ? [
                    'status' => $loan->disbursement->status?->value ?? $loan->disbursement->status,
                    'reference' => $loan->disbursement->reference,
                    'confirmed_at' => $loan->disbursement->confirmed_at?->toIso8601String(),
                    'amount' => (string) $loan->disbursement->amount,
                    'transaction_id' => $loan->disbursement->transaction_id,
                ] : null,
                'installments' => $loan->installments->map(fn (LoanInstallment $row) => [
                    'id' => $row->id,
                    'installment_number' => $row->installment_number,
                    'due_date' => $row->due_date?->toDateString(),
                    'amount_due' => (string) $row->amount_due,
                    'amount_paid' => (string) $row->amount_paid,
                    'remaining' => $row->remainingAmount(),
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
                    'transaction_id' => $payment->transaction_id,
                ]),
            ],
        ]);
    }

    public function acceptAgreement(
        Request $request,
        Loan $loan,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        $this->assertOwner($request, $loan);

        $workflow->acceptAgreement($loan, $request->user());

        return back()->with('success', 'Agreement accepted. Waiting for bank disbursement.');
    }

    public function payInstallment(
        Request $request,
        Loan $loan,
        LoanInstallment $installment,
        LoanPaymentService $payments,
    ): RedirectResponse {
        $this->assertOwner($request, $loan);
        abort_unless($installment->loan_id === $loan->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $result = $payments->initiate($request->user(), $installment, (string) $data['amount']);

        return redirect()->away($result['checkout_url']);
    }

    private function assertOwner(Request $request, Loan $loan): void
    {
        abort_unless($loan->user_id === $request->user()->id, 403);
    }
}

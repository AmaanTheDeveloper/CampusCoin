<?php

namespace App\Http\Controllers\Loans;

use App\Enums\Loans\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\LoanPayment;
use App\Services\Loans\Payments\LoanPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoanPaymentController extends Controller
{
    public function sandbox(Request $request, LoanPayment $payment): Response
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payment->load(['loan:id,reference,outstanding_balance', 'installment']);

        return Inertia::render('loans/payments/sandbox', [
            'eyebrow' => 'Sandbox checkout',
            'title' => 'Complete test payment',
            'description' => 'Simulate a card payment for local development. No real money is moved.',
            'payment' => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status->value,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'loan' => $payment->loan?->only(['id', 'reference']),
                'installment' => $payment->installment ? [
                    'id' => $payment->installment->id,
                    'installment_number' => $payment->installment->installment_number,
                    'due_date' => $payment->installment->due_date?->toDateString(),
                ] : null,
            ],
        ]);
    }

    public function completeSandbox(
        Request $request,
        LoanPayment $payment,
        LoanPaymentService $payments,
    ): RedirectResponse {
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless(in_array($payment->status, [PaymentStatus::Initiated, PaymentStatus::Pending], true), 422);

        $data = $request->validate([
            'outcome' => ['required', 'in:succeeded,failed'],
            'card_last_four' => ['nullable', 'string', 'size:4'],
        ]);

        $payload = [
            'provider_transaction_id' => $payment->provider_transaction_id,
            'event_id' => 'sandbox_'.Str::lower(Str::random(12)),
            'event_type' => 'payment.'.$data['outcome'],
            'status' => $data['outcome'],
            'card_last_four' => $data['card_last_four'] ?? '4242',
            'failure_reason' => $data['outcome'] === 'failed' ? 'Sandbox decline' : null,
        ];

        $updated = $payments->handleWebhook($payload, null);
        $updated->load(['loan:id,outstanding_balance', 'installment']);

        $loanId = $payment->loan_id;
        $succeeded = $data['outcome'] === 'succeeded';

        $redirect = redirect()->route('loans.active.show', $loanId);

        $result = [
            'outcome' => $succeeded ? 'succeeded' : 'failed',
            'amount' => (string) $updated->amount,
            'reference' => $updated->reference,
            'installment_number' => $updated->installment?->installment_number,
            'paid_at' => $updated->paid_at?->toIso8601String() ?? now()->toIso8601String(),
            'remaining_balance' => $updated->loan ? (string) $updated->loan->outstanding_balance : null,
            'reason' => $succeeded ? null : (string) (($updated->meta['failure'] ?? null) ?: 'Sandbox decline'),
        ];

        if ($succeeded) {
            return $redirect
                ->with('success', 'Payment successful.')
                ->with('payment_result', $result);
        }

        return $redirect
            ->with('error', 'Payment failed. No installment was marked as paid.')
            ->with('payment_result', $result);
    }

    public function webhook(Request $request, LoanPaymentService $payments): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Loan-Payment-Signature');

        $payment = $payments->handleWebhook($payload, $signature);

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
        ]);
    }
}

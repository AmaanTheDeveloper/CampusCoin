<?php

namespace App\Services\Loans\Payments;

use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Loans\PaymentStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanPaymentEvent;
use App\Models\User;
use App\Services\Loans\LoanAuditService;
use App\Services\Loans\LoanFinanceLedgerService;
use App\Services\Loans\LoanNotificationService;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

interface PaymentProvider
{
    /**
     * @return array{checkout_url: string, provider_transaction_id: string}
     */
    public function initiateCheckout(LoanPayment $payment): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhook(array $payload, ?string $signature): bool;
}

class SandboxPaymentProvider implements PaymentProvider
{
    public function initiateCheckout(LoanPayment $payment): array
    {
        $providerId = 'sandbox_'.Str::lower(Str::random(16));

        return [
            'checkout_url' => route('loans.payments.sandbox', ['payment' => $payment->id]),
            'provider_transaction_id' => $providerId,
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature): bool
    {
        // Sandbox accepts signed payloads with shared secret or unsigned local events.
        $secret = (string) config('services.loans.webhook_secret', 'campuscoin-sandbox-secret');

        if ($signature === null || $signature === '') {
            return app()->environment(['local', 'testing']);
        }

        $expected = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $secret);

        return hash_equals($expected, $signature);
    }
}

class LoanPaymentService
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly LoanAuditService $audit,
        private readonly LoanNotificationService $notifications,
        private readonly LoanFinanceLedgerService $ledger,
    ) {}

    public function initiate(User $student, LoanInstallment $installment, string $amount): array
    {
        $loan = $installment->loan;
        if ($loan->user_id !== $student->id) {
            abort(403);
        }

        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed], true)) {
            throw ValidationException::withMessages(['loan' => 'Loan is not active for payments.']);
        }

        $remaining = $installment->remainingAmount();
        $amount = MoneyMath::normalize($amount);

        if (MoneyMath::compare($amount, '0.00') !== 1) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if (MoneyMath::compare($amount, $remaining) === 1) {
            throw ValidationException::withMessages(['amount' => 'Payment exceeds remaining installment amount.']);
        }

        $product = $loan->product;
        if (
            MoneyMath::compare($amount, $remaining) === -1
            && ! ($product?->allows_partial_payments ?? false)
        ) {
            throw ValidationException::withMessages(['amount' => 'Partial payments are not allowed for this product.']);
        }

        $payment = LoanPayment::query()->create([
            'reference' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'loan_id' => $loan->id,
            'loan_installment_id' => $installment->id,
            'user_id' => $student->id,
            'bank_id' => $loan->bank_id,
            'amount' => $amount,
            'currency' => 'PKR',
            'status' => PaymentStatus::Initiated,
            'provider' => 'sandbox',
            'payment_method_type' => 'card',
        ]);

        $checkout = $this->provider->initiateCheckout($payment);
        $payment->forceFill([
            'provider_transaction_id' => $checkout['provider_transaction_id'],
            'status' => PaymentStatus::Pending,
        ])->save();

        $this->audit->log($student, 'payment.initiated', $payment, null, $loan->bank_id);

        return [
            'payment' => $payment->fresh(),
            'checkout_url' => $checkout['checkout_url'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, ?string $signature): LoanPayment
    {
        if (! $this->provider->verifyWebhook($payload, $signature)) {
            abort(403, 'Invalid payment webhook signature.');
        }

        $providerTx = (string) ($payload['provider_transaction_id'] ?? '');
        $eventId = (string) ($payload['event_id'] ?? ('evt_'.hash('sha256', json_encode($payload))));

        if ($providerTx === '') {
            throw ValidationException::withMessages(['provider_transaction_id' => 'Missing provider transaction id.']);
        }

        if (LoanPaymentEvent::query()->where('provider_event_id', $eventId)->exists()) {
            $existing = LoanPayment::query()->where('provider_transaction_id', $providerTx)->firstOrFail();

            return $existing;
        }

        return DB::transaction(function () use ($payload, $providerTx, $eventId) {
            $payment = LoanPayment::query()
                ->where('provider_transaction_id', $providerTx)
                ->lockForUpdate()
                ->firstOrFail();

            LoanPaymentEvent::query()->create([
                'loan_payment_id' => $payment->id,
                'provider_event_id' => $eventId,
                'event_type' => (string) ($payload['event_type'] ?? 'payment.updated'),
                'payload_hash' => hash('sha256', json_encode($payload)),
                'payload' => $payload,
                'processed' => true,
            ]);

            $status = (string) ($payload['status'] ?? 'failed');

            if ($status === 'succeeded') {
                return $this->markSucceeded($payment, $payload);
            }

            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'meta' => array_merge($payment->meta ?? [], ['failure' => $payload['failure_reason'] ?? 'Payment failed']),
            ])->save();

            $this->notifications->paymentFailed($payment);

            return $payment->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markSucceeded(LoanPayment $payment, array $payload = []): LoanPayment
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $payload) {
            $payment = LoanPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $installment = LoanInstallment::query()->whereKey($payment->loan_installment_id)->lockForUpdate()->firstOrFail();
            $loan = Loan::query()->whereKey($payment->loan_id)->lockForUpdate()->firstOrFail();

            $newPaid = MoneyMath::add((string) $installment->amount_paid, (string) $payment->amount);
            $remaining = MoneyMath::sub((string) $installment->amount_due, $newPaid);

            $installmentStatus = match (true) {
                MoneyMath::compare($remaining, '0.00') <= 0 => InstallmentStatus::Paid,
                MoneyMath::compare($newPaid, '0.00') === 1 => InstallmentStatus::PartiallyPaid,
                default => $installment->status,
            };

            $installment->forceFill([
                'amount_paid' => $newPaid,
                'status' => $installmentStatus,
                'paid_at' => $installmentStatus === InstallmentStatus::Paid ? now() : $installment->paid_at,
            ])->save();

            $loan->forceFill([
                'outstanding_balance' => MoneyMath::sub((string) $loan->outstanding_balance, (string) $payment->amount),
            ])->save();

            if (MoneyMath::compare((string) $loan->outstanding_balance, '0.00') <= 0) {
                $loan->forceFill([
                    'outstanding_balance' => '0.00',
                    'status' => LoanStatus::Completed,
                    'completed_at' => now(),
                ])->save();
            }

            $transaction = $this->ledger->recordRepaymentExpense($loan->fresh(['product']), $payment);

            $payment->forceFill([
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
                'card_last_four' => $payload['card_last_four'] ?? '4242',
                'transaction_id' => $transaction->id,
                'meta' => array_merge($payment->meta ?? [], ['sandbox' => true]),
            ])->save();

            $this->audit->log(
                $payment->user,
                'payment.succeeded',
                $payment,
                'Installment payment posted to finance ledger.',
                $payment->bank_id,
                [
                    'transaction_id' => $transaction->id,
                    'installment_id' => $installment->id,
                    'amount' => (string) $payment->amount,
                ],
            );
            $this->notifications->paymentSucceeded($payment);

            return $payment->fresh(['installment', 'loan']);
        });
    }
}

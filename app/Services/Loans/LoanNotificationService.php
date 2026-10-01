<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPayment;
use App\Models\User;
use App\Notifications\LoanLifecycleNotification;
use Illuminate\Support\Facades\Notification;

class LoanNotificationService
{
    public function applicationSubmitted(LoanApplication $application): void
    {
        $application->loadMissing('user', 'bank');

        $application->user?->notify(new LoanLifecycleNotification([
            'type' => 'loan_application_submitted',
            'title' => 'Application submitted',
            'message' => "Your loan application {$application->reference} was submitted for review.",
            'reference' => $application->reference,
            'application_id' => $application->id,
            'action_url' => '/loans/applications/'.$application->id,
        ]));

        $this->notifyBankStaff($application->bank_id, [
            'type' => 'bank_new_application',
            'title' => 'New loan application',
            'message' => "New application {$application->reference} requires review.",
            'reference' => $application->reference,
            'application_id' => $application->id,
            'action_url' => '/bank/applications/'.$application->id,
        ]);
    }

    public function applicationDecision(LoanApplication $application, string $decision): void
    {
        $application->loadMissing('user');

        $approved = $decision === 'approved';

        $application->user?->notify(new LoanLifecycleNotification([
            'type' => $approved ? 'loan_application_approved' : 'loan_application_rejected',
            'title' => $approved ? 'Application approved' : 'Application rejected',
            'message' => $approved
                ? "Your application {$application->reference} was approved. Review and accept the agreement."
                : "Your application {$application->reference} was rejected.".($application->rejection_reason ? ' '.$application->rejection_reason : ''),
            'reference' => $application->reference,
            'application_id' => $application->id,
            'action_url' => '/loans/applications/'.$application->id,
        ]));
    }

    public function moreInformationRequired(LoanApplication $application, string $note): void
    {
        $application->loadMissing('user');

        $application->user?->notify(new LoanLifecycleNotification([
            'type' => 'loan_more_information_required',
            'title' => 'More information required',
            'message' => "Your bank requested more information for {$application->reference}: {$note}",
            'reference' => $application->reference,
            'application_id' => $application->id,
            'action_url' => '/loans/applications/'.$application->id.'/edit',
        ]));
    }

    public function loanDisbursed(Loan $loan): void
    {
        $loan->loadMissing('user', 'product', 'disbursement');

        $amount = $loan->disbursement?->amount ?? $loan->principal_amount;
        $product = $loan->product?->name ?? 'student loan';

        $loan->user?->notify(new LoanLifecycleNotification([
            'type' => 'loan_disbursed',
            'title' => 'Loan disbursed',
            'message' => "Your {$product} of {$amount} has been disbursed and added to your financial activity as a loan inflow. Your repayment schedule is ready.",
            'reference' => $loan->reference,
            'loan_id' => $loan->id,
            'transaction_id' => $loan->disbursement?->transaction_id,
            'action_url' => '/loans/active/'.$loan->id,
        ]));
    }

    public function paymentSucceeded(LoanPayment $payment): void
    {
        $payment->loadMissing('user', 'loan');

        $payment->user?->notify(new LoanLifecycleNotification([
            'type' => 'loan_payment_succeeded',
            'title' => 'Payment successful',
            'message' => "Payment {$payment->reference} of {$payment->amount} {$payment->currency} succeeded.",
            'reference' => $payment->reference,
            'loan_id' => $payment->loan_id,
            'action_url' => '/loans/active/'.$payment->loan_id,
        ]));

        $this->notifyBankStaff($payment->bank_id, [
            'type' => 'bank_payment_received',
            'title' => 'Payment received',
            'message' => "Payment {$payment->reference} received for loan {$payment->loan?->reference}.",
            'reference' => $payment->reference,
            'loan_id' => $payment->loan_id,
            'action_url' => '/bank/loans/'.$payment->loan_id,
        ]);
    }

    public function paymentFailed(LoanPayment $payment): void
    {
        $payment->loadMissing('user');

        $payment->user?->notify(new LoanLifecycleNotification([
            'type' => 'loan_payment_failed',
            'title' => 'Payment failed',
            'message' => "Payment {$payment->reference} failed. You can try again from your loan center.",
            'reference' => $payment->reference,
            'loan_id' => $payment->loan_id,
            'action_url' => '/loans/active/'.$payment->loan_id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notifyBankStaff(int $bankId, array $payload): void
    {
        $staff = User::query()
            ->where('role', 'bank')
            ->whereHas('bankMemberships', fn ($q) => $q->where('bank_id', $bankId)->where('is_active', true))
            ->get();

        if ($staff->isEmpty()) {
            return;
        }

        Notification::send($staff, new LoanLifecycleNotification($payload));
    }
}

<?php

namespace App\Services\Loans;

use App\Enums\Loans\DisbursementStatus;
use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanApplicationStatus;
use App\Enums\Loans\LoanStatus;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocument;
use App\Models\LoanApplicationEvent;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanWorkflowService
{
    public function __construct(
        private readonly RepaymentScheduleService $schedule,
        private readonly LoanAuditService $audit,
        private readonly LoanNotificationService $notifications,
        private readonly LoanFinanceLedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $student, LoanProduct $product, array $data): LoanApplication
    {
        $this->assertProductAvailable($product);

        $application = LoanApplication::query()->create([
            'reference' => $this->reference('APP'),
            'user_id' => $student->id,
            'bank_id' => $product->bank_id,
            'loan_product_id' => $product->id,
            'requested_amount' => MoneyMath::normalize($data['requested_amount']),
            'requested_tenure_months' => (int) $data['requested_tenure_months'],
            'purpose' => $data['purpose'] ?? null,
            'applicant_notes' => $data['applicant_notes'] ?? null,
            'status' => LoanApplicationStatus::Draft,
        ]);

        $this->recordEvent($application, $student, null, LoanApplicationStatus::Draft, 'draft.created');
        $this->audit->log($student, 'application.draft_created', $application, null, $product->bank_id);

        return $application;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(LoanApplication $application, User $student, array $data): LoanApplication
    {
        $this->assertOwner($application, $student);
        $this->assertStatus($application, [LoanApplicationStatus::Draft, LoanApplicationStatus::MoreInformationRequired]);

        $application->fill([
            'requested_amount' => MoneyMath::normalize($data['requested_amount'] ?? $application->requested_amount),
            'requested_tenure_months' => (int) ($data['requested_tenure_months'] ?? $application->requested_tenure_months),
            'purpose' => $data['purpose'] ?? $application->purpose,
            'applicant_notes' => $data['applicant_notes'] ?? $application->applicant_notes,
        ])->save();

        return $application->fresh();
    }

    public function submit(LoanApplication $application, User $student): LoanApplication
    {
        $this->assertOwner($application, $student);
        $this->assertStatus($application, [LoanApplicationStatus::Draft, LoanApplicationStatus::MoreInformationRequired]);
        $this->assertProductAvailable($application->product);

        $from = $application->status;
        $application->forceFill([
            'status' => LoanApplicationStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        $this->recordEvent($application, $student, $from, LoanApplicationStatus::Submitted, 'application.submitted');
        $this->audit->log($student, 'application.submitted', $application, null, $application->bank_id);
        $this->notifications->applicationSubmitted($application);

        return $application->fresh();
    }

    public function markUnderReview(LoanApplication $application, User $officer): LoanApplication
    {
        $this->assertBankAccess($application, $officer);
        $this->assertStatus($application, [LoanApplicationStatus::Submitted, LoanApplicationStatus::MoreInformationRequired]);

        $from = $application->status;
        $application->forceFill([
            'status' => LoanApplicationStatus::UnderReview,
            'reviewed_by' => $officer->id,
        ])->save();

        $this->recordEvent($application, $officer, $from, LoanApplicationStatus::UnderReview, 'application.under_review');
        $this->audit->log($officer, 'application.under_review', $application, null, $application->bank_id);

        return $application->fresh();
    }

    public function requestMoreInformation(LoanApplication $application, User $officer, string $note): LoanApplication
    {
        $this->assertBankAccess($application, $officer);
        $this->assertStatus($application, [LoanApplicationStatus::Submitted, LoanApplicationStatus::UnderReview]);

        $from = $application->status;
        $application->forceFill([
            'status' => LoanApplicationStatus::MoreInformationRequired,
            'reviewed_by' => $officer->id,
        ])->save();

        $this->recordEvent($application, $officer, $from, LoanApplicationStatus::MoreInformationRequired, 'application.more_info', $note);
        $this->audit->log($officer, 'application.more_info', $application, $note, $application->bank_id);
        $this->notifications->moreInformationRequired($application, $note);

        return $application->fresh();
    }

    public function reject(LoanApplication $application, User $officer, string $reason): LoanApplication
    {
        $this->assertBankAccess($application, $officer);
        $this->assertStatus($application, [
            LoanApplicationStatus::Submitted,
            LoanApplicationStatus::UnderReview,
            LoanApplicationStatus::MoreInformationRequired,
        ]);

        $from = $application->status;
        $application->forceFill([
            'status' => LoanApplicationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $officer->id,
            'decided_at' => now(),
        ])->save();

        $this->recordEvent($application, $officer, $from, LoanApplicationStatus::Rejected, 'application.rejected', $reason);
        $this->audit->log($officer, 'application.rejected', $application, $reason, $application->bank_id);
        $this->notifications->applicationDecision($application, 'rejected');

        return $application->fresh();
    }

    public function approve(LoanApplication $application, User $officer): Loan
    {
        $this->assertBankAccess($application, $officer);
        $this->assertStatus($application, [
            LoanApplicationStatus::Submitted,
            LoanApplicationStatus::UnderReview,
            LoanApplicationStatus::MoreInformationRequired,
        ]);

        return DB::transaction(function () use ($application, $officer) {
            $product = $application->product;
            $principal = MoneyMath::normalize((string) $application->requested_amount);
            $tenure = (int) $application->requested_tenure_months;
            $rate = MoneyMath::normalize((string) $product->annual_rate_percent);
            $monthlyRate = MoneyMath::compare($rate, '0.00') === 0
                ? '0.00'
                : (function_exists('bcdiv') ? MoneyMath::normalize(bcdiv($rate, '1200', 8)) : MoneyMath::normalize(((float) $rate) / 1200));
            $installment = $this->schedule->installmentAmount($principal, $tenure, $monthlyRate);
            $schedule = $this->schedule->buildSchedule($principal, $tenure, $rate);
            $total = '0.00';
            foreach ($schedule as $row) {
                $total = MoneyMath::add($total, $row['amount_due']);
            }

            $from = $application->status;
            $application->forceFill([
                'status' => LoanApplicationStatus::Approved,
                'reviewed_by' => $officer->id,
                'decided_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->recordEvent($application, $officer, $from, LoanApplicationStatus::Approved, 'application.approved');

            $loan = Loan::query()->create([
                'reference' => $this->reference('LN'),
                'loan_application_id' => $application->id,
                'user_id' => $application->user_id,
                'bank_id' => $application->bank_id,
                'loan_product_id' => $application->loan_product_id,
                'principal_amount' => $principal,
                'tenure_months' => $tenure,
                'annual_rate_percent' => $rate,
                'installment_amount' => $installment,
                'total_repayable' => $total,
                'outstanding_balance' => $total,
                'status' => LoanStatus::AgreementPending,
            ]);

            foreach ($schedule as $row) {
                LoanInstallment::query()->create([
                    'loan_id' => $loan->id,
                    'installment_number' => $row['installment_number'],
                    'due_date' => $row['due_date'],
                    'amount_due' => $row['amount_due'],
                    'principal_component' => $row['principal_component'],
                    'interest_component' => $row['interest_component'],
                    'amount_paid' => '0.00',
                    'status' => InstallmentStatus::Upcoming,
                ]);
            }

            LoanDisbursement::query()->create([
                'loan_id' => $loan->id,
                'amount' => $principal,
                'status' => 'pending',
            ]);

            $this->audit->log($officer, 'application.approved', $application, null, $application->bank_id);
            $this->audit->log($officer, 'loan.created', $loan, null, $loan->bank_id);
            $this->notifications->applicationDecision($application, 'approved');

            return $loan->fresh(['installments', 'disbursement']);
        });
    }

    public function acceptAgreement(Loan $loan, User $student): Loan
    {
        if ($loan->user_id !== $student->id) {
            abort(403);
        }

        if ($loan->status !== LoanStatus::AgreementPending) {
            throw ValidationException::withMessages(['loan' => 'Agreement is not pending for this loan.']);
        }

        $loan->forceFill([
            'status' => LoanStatus::ReadyForDisbursement,
            'agreement_accepted_at' => now(),
        ])->save();

        $this->audit->log($student, 'loan.agreement_accepted', $loan, null, $loan->bank_id);

        return $loan->fresh();
    }

    public function confirmDisbursement(Loan $loan, User $officer, ?string $reference = null, ?string $destination = null): Loan
    {
        $this->assertBankAccessLoan($loan, $officer);

        return DB::transaction(function () use ($loan, $officer, $reference, $destination) {
            $loan = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $loan->loadMissing('disbursement', 'product');

            // Idempotent: disbursement already confirmed and finance income posted.
            if (
                $loan->status === LoanStatus::Active
                && $loan->disbursed_at
                && $loan->disbursement?->transaction_id
            ) {
                return $loan->fresh(['installments', 'disbursement']);
            }

            if (! in_array($loan->status, [LoanStatus::ReadyForDisbursement, LoanStatus::Approved], true)) {
                throw ValidationException::withMessages(['loan' => 'Loan is not ready for disbursement.']);
            }

            $disbursement = $loan->disbursement;
            if (! $disbursement) {
                $disbursement = LoanDisbursement::query()->create([
                    'loan_id' => $loan->id,
                    'amount' => $loan->principal_amount,
                    'status' => DisbursementStatus::Pending,
                ]);
            } else {
                $disbursement = LoanDisbursement::query()->whereKey($disbursement->id)->lockForUpdate()->firstOrFail();
            }

            if ($disbursement->transaction_id) {
                $loan->forceFill([
                    'status' => LoanStatus::Active,
                    'disbursed_at' => $loan->disbursed_at ?? now(),
                ])->save();

                return $loan->fresh(['installments', 'disbursement']);
            }

            $disbursement->forceFill([
                'status' => DisbursementStatus::Disbursed,
                'amount' => $disbursement->amount ?: $loan->principal_amount,
                'reference' => $reference ?: $disbursement->reference ?: $this->reference('DIS'),
                'destination_note' => $destination ?? $disbursement->destination_note,
                'confirmed_by' => $officer->id,
                'confirmed_at' => $disbursement->confirmed_at ?? now(),
            ])->save();

            $loan->forceFill([
                'status' => LoanStatus::Active,
                'disbursed_at' => $loan->disbursed_at ?? now(),
            ])->save();

            $first = $loan->installments()->orderBy('installment_number')->first();
            if ($first && $first->status === InstallmentStatus::Upcoming) {
                $first->forceFill(['status' => InstallmentStatus::Due])->save();
            }

            $transaction = $this->ledger->recordDisbursementIncome($loan->fresh(['product']), $disbursement->fresh());

            $this->audit->log(
                $officer,
                'loan.disbursed',
                $loan,
                'Disbursement confirmed and finance income posted.',
                $loan->bank_id,
                [
                    'disbursement_id' => $disbursement->id,
                    'transaction_id' => $transaction->id,
                    'amount' => (string) $disbursement->amount,
                ],
            );
            $this->notifications->loanDisbursed($loan->fresh(['user', 'product', 'disbursement']));

            return $loan->fresh(['installments', 'disbursement']);
        });
    }

    public function storeApplicationDocument(
        LoanApplication $application,
        User $student,
        string $documentType,
        UploadedFile $file,
    ): LoanApplicationDocument {
        $this->assertOwner($application, $student);

        $path = $file->store("loan-applications/{$application->id}", 'local');

        $doc = LoanApplicationDocument::query()->create([
            'loan_application_id' => $application->id,
            'document_type' => $documentType,
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'status' => 'uploaded',
        ]);

        $this->audit->log($student, 'application.document_uploaded', $application, $documentType, $application->bank_id);

        return $doc;
    }

    private function assertProductAvailable(LoanProduct $product): void
    {
        $product->loadMissing('bank');
        if (! $product->is_published || $product->status !== 'active' || ! $product->bank?->isActive()) {
            throw ValidationException::withMessages(['product' => 'This loan product is not available.']);
        }
    }

    private function assertOwner(LoanApplication $application, User $student): void
    {
        if ($application->user_id !== $student->id) {
            abort(403);
        }
    }

    private function assertBankAccess(LoanApplication $application, User $officer): void
    {
        // Loan decisions belong to the bank tenant — super admins do not underwrite.
        $member = $officer->bankMemberships()
            ->where('bank_id', $application->bank_id)
            ->where('is_active', true)
            ->exists();

        if (! $member) {
            abort(403);
        }
    }

    private function assertBankAccessLoan(Loan $loan, User $officer): void
    {
        $member = $officer->bankMemberships()
            ->where('bank_id', $loan->bank_id)
            ->where('is_active', true)
            ->exists();

        if (! $member) {
            abort(403);
        }
    }

    /**
     * @param  list<LoanApplicationStatus>  $allowed
     */
    private function assertStatus(LoanApplication $application, array $allowed): void
    {
        if (! in_array($application->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid application status for this action.']);
        }
    }

    private function recordEvent(
        LoanApplication $application,
        ?User $actor,
        ?LoanApplicationStatus $from,
        LoanApplicationStatus $to,
        string $action,
        ?string $note = null,
    ): void {
        LoanApplicationEvent::query()->create([
            'loan_application_id' => $application->id,
            'actor_id' => $actor?->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'action' => $action,
            'note' => $note,
        ]);
    }

    private function reference(string $prefix): string
    {
        return strtoupper($prefix).'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}

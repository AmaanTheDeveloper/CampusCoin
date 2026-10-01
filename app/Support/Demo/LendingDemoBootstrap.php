<?php

namespace App\Support\Demo;

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use App\Enums\Loans\DisbursementStatus;
use App\Enums\Loans\LoanApplicationStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Role;
use App\Models\Bank;
use App\Models\BankMembership;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocument;
use App\Models\LoanApplicationEvent;
use App\Models\LoanAuditLog;
use App\Models\LoanDisbursement;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanPaymentEvent;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loans\LoanFinanceLedgerService;
use App\Services\Loans\LoanWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Idempotent local/demo lending bootstrap.
 * Uses real models and workflow services — no auth or state-machine bypasses.
 */
final class LendingDemoBootstrap
{
    /**
     * @return array{
     *     bank: Bank,
     *     owner: User,
     *     officer: User,
     *     student: User,
     *     admin: User|null,
     *     product: LoanProduct,
     *     walkthrough_application: LoanApplication,
     *     active_loan: Loan|null
     * }
     */
    public function seed(bool $withActiveLoan = true): array
    {
        try {
            return DB::transaction(function () use ($withActiveLoan) {
                $admin = $this->ensureDemoAdmin();
                $owner = $this->ensureBankUser(
                    LendingDemoIds::OWNER_EMAIL,
                    'Campus Demo Bank Owner',
                    LendingDemoIds::OWNER_PASSWORD,
                );
                $officer = $this->ensureBankUser(
                    LendingDemoIds::OFFICER_EMAIL,
                    'Campus Demo Loan Officer',
                    LendingDemoIds::OFFICER_PASSWORD,
                );
                $student = $this->ensureStudent();

                $bank = $this->ensureBank($owner, $admin);
                $this->ensureMembership($bank, $owner, BankStaffRole::Owner);
                $this->ensureMembership($bank, $officer, BankStaffRole::Officer);

                $product = $this->ensureProduct($bank);
                $walkthrough = $this->ensureWalkthroughApplication($student, $bank, $product);
                $activeLoan = $withActiveLoan
                    ? $this->ensureActiveLoanScenario($student, $bank, $product, $owner)
                    : null;

                return [
                    'bank' => $bank->fresh(),
                    'owner' => $owner->fresh(),
                    'officer' => $officer->fresh(),
                    'student' => $student->fresh(),
                    'admin' => $admin?->fresh(),
                    'product' => $product->fresh(),
                    'walkthrough_application' => $walkthrough->fresh(['documents', 'events']),
                    'active_loan' => $activeLoan?->fresh(['installments']),
                ];
            });
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Lending demo seed failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Reset the walkthrough application to SUBMITTED and remove any loan/payment
     * records tied to that reference so the approval journey can be repeated.
     */
    public function resetWalkthrough(): LoanApplication
    {
        return DB::transaction(function () {
            $application = LoanApplication::query()
                ->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)
                ->first();

            if (! $application) {
                $result = $this->seed(withActiveLoan: false);

                return $result['walkthrough_application'];
            }

            $this->purgeApplicationTree($application);

            $application->forceFill([
                'status' => LoanApplicationStatus::Submitted,
                'rejection_reason' => null,
                'submitted_at' => now(),
                'decided_at' => null,
                'reviewed_by' => null,
                'requested_amount' => LendingDemoIds::REQUESTED_AMOUNT,
                'requested_tenure_months' => LendingDemoIds::REQUESTED_TENURE_MONTHS,
                'purpose' => 'Demo tuition and campus living support',
                'applicant_notes' => 'DEVELOPMENT / DEMO ONLY — sample application for walkthrough.',
            ])->save();

            LoanApplicationEvent::query()->create([
                'loan_application_id' => $application->id,
                'actor_id' => $application->user_id,
                'action' => 'application.submitted',
                'from_status' => null,
                'to_status' => LoanApplicationStatus::Submitted->value,
                'note' => 'Demo walkthrough reset to submitted.',
            ]);

            $this->ensureDemoDocument($application);

            return $application->fresh(['documents', 'events']);
        });
    }

    private function ensureDemoAdmin(): ?User
    {
        $existingAdmin = User::query()->where('role', Role::Admin)->first();
        if ($existingAdmin) {
            return $existingAdmin;
        }

        $admin = User::query()->firstOrNew(['email' => LendingDemoIds::ADMIN_EMAIL]);
        $admin->fill([
            'name' => 'Demo Super Admin',
            'password' => LendingDemoIds::ADMIN_PASSWORD,
        ]);
        $admin->email_verified_at = now();
        $admin->forceFill(['role' => Role::Admin])->save();

        return $admin->fresh();
    }

    private function ensureBankUser(string $email, string $name, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => $password,
        ]);
        $user->email_verified_at = now();
        $user->forceFill(['role' => Role::Bank])->save();

        return $user->fresh();
    }

    private function ensureStudent(): User
    {
        $user = User::query()->firstOrNew(['email' => LendingDemoIds::STUDENT_EMAIL]);
        $user->fill([
            'name' => LendingDemoIds::STUDENT_NAME,
            'password' => LendingDemoIds::STUDENT_PASSWORD,
        ]);
        $user->email_verified_at = now();
        $user->forceFill(['role' => Role::Student])->save();

        return $user->fresh();
    }

    private function ensureBank(User $owner, ?User $approver): Bank
    {
        $bank = Bank::query()->firstOrNew(['slug' => LendingDemoIds::BANK_SLUG]);
        $bank->fill([
            'name' => LendingDemoIds::BANK_NAME,
            'reference_code' => $bank->reference_code ?: 'BANK-REQ-DEMO0001',
            'official_email' => LendingDemoIds::BANK_EMAIL,
            'phone' => '+92 300 0000000',
            'address' => 'Demo Banking Center',
            'city' => 'Demo City',
            'country' => 'Pakistan',
            'registration_number' => LendingDemoIds::BANK_REGISTRATION,
            'authorized_representative' => $owner->name,
            'representative_email' => $owner->email,
            'representative_phone' => '+92 300 0000000',
            'status' => BankStatus::Approved,
            'rejection_reason' => null,
            'suspension_reason' => null,
            'suspended_at' => null,
            'approved_by' => $approver?->id,
            'approved_at' => $bank->approved_at ?? now(),
        ]);
        $bank->save();

        return $bank->fresh();
    }

    private function ensureMembership(Bank $bank, User $user, BankStaffRole $role): void
    {
        BankMembership::query()->updateOrCreate(
            [
                'bank_id' => $bank->id,
                'user_id' => $user->id,
            ],
            [
                'staff_role' => $role,
                'is_active' => true,
            ],
        );
    }

    private function ensureProduct(Bank $bank): LoanProduct
    {
        $product = LoanProduct::query()->firstOrNew([
            'bank_id' => $bank->id,
            'slug' => LendingDemoIds::PRODUCT_SLUG,
        ]);

        $product->fill([
            'name' => LendingDemoIds::PRODUCT_NAME,
            'description' => 'DEVELOPMENT / DEMO ONLY — sandbox student support loan for CampusCoin local walkthroughs. Not a real credit offer.',
            'min_amount' => '10000.00',
            'max_amount' => '100000.00',
            'min_tenure_months' => 6,
            'max_tenure_months' => 12,
            'repayment_frequency' => 'monthly',
            'annual_rate_percent' => '10.0000',
            'processing_fee_percent' => '0',
            'processing_fee_fixed' => '0',
            'allows_partial_payments' => true,
            'required_documents' => ['student_id'],
            'eligibility_notes' => [
                'Enrolled student (demo)',
                'Valid campus email / demo account',
            ],
            'is_published' => true,
            'status' => 'active',
        ]);
        $product->save();

        return $product->fresh();
    }

    private function ensureWalkthroughApplication(User $student, Bank $bank, LoanProduct $product): LoanApplication
    {
        $application = LoanApplication::query()->firstOrNew([
            'reference' => LendingDemoIds::APP_WALKTHROUGH_REFERENCE,
        ]);

        // Preserve in-progress walkthrough if already past submitted (unless no loan yet and still pending review).
        if ($application->exists && $application->loan()->exists()) {
            return $application->fresh();
        }

        if ($application->exists && in_array($application->status, [
            LoanApplicationStatus::UnderReview,
            LoanApplicationStatus::MoreInformationRequired,
            LoanApplicationStatus::Approved,
        ], true)) {
            $this->ensureDemoDocument($application);

            return $application->fresh();
        }

        $application->fill([
            'user_id' => $student->id,
            'bank_id' => $bank->id,
            'loan_product_id' => $product->id,
            'requested_amount' => LendingDemoIds::REQUESTED_AMOUNT,
            'requested_tenure_months' => LendingDemoIds::REQUESTED_TENURE_MONTHS,
            'purpose' => 'Demo tuition and campus living support',
            'applicant_notes' => 'DEVELOPMENT / DEMO ONLY — sample application for bank review walkthrough.',
            'status' => LoanApplicationStatus::Submitted,
            'rejection_reason' => null,
            'submitted_at' => $application->submitted_at ?? now(),
            'decided_at' => null,
            'reviewed_by' => null,
        ]);
        $application->save();

        $hasSubmittedEvent = LoanApplicationEvent::query()
            ->where('loan_application_id', $application->id)
            ->where('action', 'application.submitted')
            ->exists();

        if (! $hasSubmittedEvent) {
            LoanApplicationEvent::query()->create([
                'loan_application_id' => $application->id,
                'actor_id' => $student->id,
                'action' => 'application.submitted',
                'from_status' => LoanApplicationStatus::Draft->value,
                'to_status' => LoanApplicationStatus::Submitted->value,
                'note' => 'Demo seed: application submitted for review.',
            ]);

            LoanAuditLog::query()->create([
                'actor_id' => $student->id,
                'actor_role' => Role::Student->value,
                'action' => 'application.submitted',
                'subject_type' => LoanApplication::class,
                'subject_id' => $application->id,
                'bank_id' => $bank->id,
                'description' => 'Demo seed: application submitted for review.',
                'meta' => ['demo' => true, 'reference' => $application->reference],
            ]);
        }

        $this->ensureDemoDocument($application);

        return $application->fresh();
    }

    private function ensureActiveLoanScenario(User $student, Bank $bank, LoanProduct $product, User $owner): Loan
    {
        $existingLoan = Loan::query()
            ->whereHas('application', fn ($q) => $q->where('reference', LendingDemoIds::APP_ACTIVE_REFERENCE))
            ->first();

        if ($existingLoan) {
            $this->ensureDisbursementFinancePosted($existingLoan);

            return $existingLoan->fresh(['installments', 'disbursement']);
        }

        $application = LoanApplication::query()->firstOrNew([
            'reference' => LendingDemoIds::APP_ACTIVE_REFERENCE,
        ]);

        if (! $application->exists) {
            $application->fill([
                'user_id' => $student->id,
                'bank_id' => $bank->id,
                'loan_product_id' => $product->id,
                'requested_amount' => '45000.00',
                'requested_tenure_months' => 6,
                'purpose' => 'Demo active repayment scenario',
                'applicant_notes' => 'DEVELOPMENT / DEMO ONLY — pre-advanced to active for repayment/payment sandbox demos.',
                'status' => LoanApplicationStatus::Draft,
            ]);
            $application->save();
        }

        $this->ensureDemoDocument($application);

        $workflow = app(LoanWorkflowService::class);

        if ($application->status === LoanApplicationStatus::Draft) {
            $application = $workflow->submit($application->fresh(), $student);
        }

        if (in_array($application->status, [LoanApplicationStatus::Submitted, LoanApplicationStatus::MoreInformationRequired], true)) {
            $application = $workflow->markUnderReview($application->fresh(), $owner);
        }

        if ($application->status === LoanApplicationStatus::UnderReview) {
            $loan = $workflow->approve($application->fresh(), $owner);
        } else {
            $loan = $application->loan;
        }

        if (! $loan) {
            throw new RuntimeException('Demo active loan could not be created from application.');
        }

        if ($loan->status === LoanStatus::AgreementPending) {
            $loan = $workflow->acceptAgreement($loan->fresh(), $student);
        }

        if ($loan->status === LoanStatus::ReadyForDisbursement) {
            $loan = $workflow->confirmDisbursement($loan->fresh(), $owner, 'DIS-DEMO-ACTIVE', 'Demo student wallet');
        }

        $this->ensureDisbursementFinancePosted($loan);

        return $loan->fresh(['installments', 'disbursement']);
    }

    /**
     * Backfill finance income for demo loans disbursed before ledger integration.
     */
    private function ensureDisbursementFinancePosted(Loan $loan): void
    {
        $loan->loadMissing('disbursement', 'product');
        $disbursement = $loan->disbursement;

        if (! $disbursement || $disbursement->transaction_id || ! $loan->disbursed_at) {
            return;
        }

        if ($disbursement->status !== DisbursementStatus::Disbursed) {
            $disbursement->forceFill(['status' => DisbursementStatus::Disbursed])->save();
        }

        app(LoanFinanceLedgerService::class)->recordDisbursementIncome($loan, $disbursement->fresh());
    }

    private function ensureDemoDocument(LoanApplication $application): void
    {
        $path = 'demo/lending/'.$application->reference.'-student-id.txt';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put(
                $path,
                "DEVELOPMENT / DEMO ONLY\nSample student ID placeholder for {$application->reference}.\nNot a real identity document.\n",
            );
        }

        LoanApplicationDocument::query()->updateOrCreate(
            [
                'loan_application_id' => $application->id,
                'document_type' => 'student_id',
            ],
            [
                'original_name' => 'demo-student-id.txt',
                'disk_path' => $path,
                'mime_type' => 'text/plain',
                'size_bytes' => Storage::disk('local')->size($path),
                'status' => 'uploaded',
                'reviewer_note' => null,
            ],
        );
    }

    private function purgeApplicationTree(LoanApplication $application): void
    {
        $loan = $application->loan;
        if ($loan) {
            $paymentIds = LoanPayment::query()->where('loan_id', $loan->id)->pluck('id');
            if ($paymentIds->isNotEmpty()) {
                LoanPaymentEvent::query()->whereIn('loan_payment_id', $paymentIds)->delete();
                LoanPayment::query()->whereIn('id', $paymentIds)->delete();
            }
            LoanInstallment::query()->where('loan_id', $loan->id)->delete();
            LoanDisbursement::query()->where('loan_id', $loan->id)->delete();
            $loan->delete();
        }

        LoanApplicationEvent::query()->where('loan_application_id', $application->id)->delete();
        // Keep document file; recreate metadata after reset.
    }
}

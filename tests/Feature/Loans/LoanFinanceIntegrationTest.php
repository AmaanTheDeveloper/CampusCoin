<?php

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use App\Enums\Loans\DisbursementStatus;
use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Loans\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\Bank;
use App\Models\BankMembership;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanPayment;
use App\Models\LoanPaymentEvent;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Loans\LoanFinanceLedgerService;
use App\Services\Loans\LoanWorkflowService;
use App\Services\Loans\Payments\LoanPaymentService;

function createFinanceBankFixture(): array
{
    seedDefaultCategories();

    $owner = User::factory()->bank()->create();
    $student = User::factory()->create();

    $bank = Bank::query()->create([
        'name' => 'Finance Link Bank',
        'slug' => 'finance-link-bank-'.uniqid(),
        'official_email' => 'ops@financelink.test',
        'authorized_representative' => $owner->name,
        'representative_email' => $owner->email,
        'status' => BankStatus::Approved,
        'approved_at' => now(),
    ]);

    BankMembership::query()->create([
        'bank_id' => $bank->id,
        'user_id' => $owner->id,
        'staff_role' => BankStaffRole::Owner,
        'is_active' => true,
    ]);

    $product = LoanProduct::query()->create([
        'bank_id' => $bank->id,
        'name' => 'Campus Student Support Loan',
        'slug' => 'campus-support-'.uniqid(),
        'description' => 'Demo product',
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
        'eligibility_notes' => [],
        'is_published' => true,
        'status' => 'active',
    ]);

    return [$bank, $owner, $student, $product];
}

function advanceLoanToReadyForDisbursement(User $student, User $owner, LoanProduct $product): Loan
{
    $workflow = app(LoanWorkflowService::class);

    $application = $workflow->createDraft($student, $product, [
        'requested_amount' => '60000.00',
        'requested_tenure_months' => 12,
        'purpose' => 'Tuition',
    ]);
    $application = $workflow->submit($application, $student);
    $application = $workflow->markUnderReview($application, $owner);
    $loan = $workflow->approve($application, $owner);
    $loan = $workflow->acceptAgreement($loan, $student);

    expect($loan->status)->toBe(LoanStatus::ReadyForDisbursement);

    return $loan->fresh();
}

test('confirmed disbursement creates a single Loan income transaction', function () {
    [, $owner, $student, $product] = createFinanceBankFixture();
    $loan = advanceLoanToReadyForDisbursement($student, $owner, $product);

    expect(Transaction::query()->where('user_id', $student->id)->where('type', TransactionType::Income)->count())->toBe(0);

    $loan = app(LoanWorkflowService::class)->confirmDisbursement($loan, $owner, 'DIS-FIN-1', 'Wallet');

    $disbursement = $loan->disbursement()->firstOrFail();
    expect($disbursement->status)->toBe(DisbursementStatus::Disbursed)
        ->and($disbursement->transaction_id)->not->toBeNull();

    $income = Transaction::query()->findOrFail($disbursement->transaction_id);
    $loanCategory = Category::query()->whereNull('user_id')->where('type', TransactionType::Income)->where('name', 'Loan')->firstOrFail();

    expect($income->user_id)->toBe($student->id)
        ->and($income->type)->toBe(TransactionType::Income)
        ->and($income->category_id)->toBe($loanCategory->id)
        ->and((string) $income->amount)->toBe((string) $disbursement->amount)
        ->and($income->description)->toContain('Disbursement');

    // Duplicate confirm is idempotent — still one income transaction.
    app(LoanWorkflowService::class)->confirmDisbursement($loan->fresh(), $owner, 'DIS-FIN-1-RETRY', 'Wallet');

    expect(Transaction::query()->where('user_id', $student->id)->where('category_id', $loanCategory->id)->count())->toBe(1)
        ->and(LoanDisbursement::query()->where('loan_id', $loan->id)->whereNotNull('transaction_id')->count())->toBe(1);
});

test('approval alone does not create income and failed disbursement creates none', function () {
    [, $owner, $student, $product] = createFinanceBankFixture();
    $workflow = app(LoanWorkflowService::class);

    $application = $workflow->createDraft($student, $product, [
        'requested_amount' => '60000.00',
        'requested_tenure_months' => 12,
        'purpose' => 'Tuition',
    ]);
    $application = $workflow->submit($application, $student);
    $application = $workflow->markUnderReview($application, $owner);
    $loan = $workflow->approve($application, $owner);

    expect(Transaction::query()->where('user_id', $student->id)->count())->toBe(0);

    $disbursement = LoanDisbursement::query()->create([
        'loan_id' => $loan->id,
        'amount' => $loan->principal_amount,
        'status' => DisbursementStatus::Failed,
        'failure_reason' => 'Demo failure',
    ]);

    expect(fn () => app(LoanFinanceLedgerService::class)->recordDisbursementIncome($loan, $disbursement))
        ->toThrow(RuntimeException::class);

    expect(Transaction::query()->where('user_id', $student->id)->count())->toBe(0)
        ->and($disbursement->fresh()->transaction_id)->toBeNull();
});

test('successful installment payment creates a single Loan Repayment expense', function () {
    [, $owner, $student, $product] = createFinanceBankFixture();
    $loan = advanceLoanToReadyForDisbursement($student, $owner, $product);
    $loan = app(LoanWorkflowService::class)->confirmDisbursement($loan, $owner, 'DIS-FIN-2', 'Wallet');

    $installment = $loan->installments()->orderBy('installment_number')->firstOrFail();
    expect($installment->status)->toBe(InstallmentStatus::Due);

    $payments = app(LoanPaymentService::class);
    $checkout = $payments->initiate($student, $installment, (string) $installment->amount_due);
    /** @var LoanPayment $payment */
    $payment = $checkout['payment'];

    $payload = [
        'provider_transaction_id' => $payment->provider_transaction_id,
        'event_id' => 'fin_evt_pay_1',
        'event_type' => 'payment.succeeded',
        'status' => 'succeeded',
        'card_last_four' => '4242',
    ];

    $payments->handleWebhook($payload, null);
    $payment->refresh();

    $expenseCategory = Category::query()
        ->whereNull('user_id')
        ->where('type', TransactionType::Expense)
        ->where('name', 'Loan Repayment')
        ->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->transaction_id)->not->toBeNull();

    $expense = Transaction::query()->findOrFail($payment->transaction_id);
    expect($expense->type)->toBe(TransactionType::Expense)
        ->and($expense->category_id)->toBe($expenseCategory->id)
        ->and($expense->user_id)->toBe($student->id)
        ->and((string) $expense->amount)->toBe((string) $payment->amount);

    $payments->handleWebhook($payload, null);
    expect(LoanPaymentEvent::query()->where('provider_event_id', 'fin_evt_pay_1')->count())->toBe(1)
        ->and(Transaction::query()->where('user_id', $student->id)->where('category_id', $expenseCategory->id)->count())->toBe(1)
        ->and($installment->fresh()->status)->toBe(InstallmentStatus::Paid);
});

test('student can view own loan finance transaction and not another students', function () {
    [, $owner, $student, $product] = createFinanceBankFixture();
    $other = User::factory()->create();
    $loan = advanceLoanToReadyForDisbursement($student, $owner, $product);
    $loan = app(LoanWorkflowService::class)->confirmDisbursement($loan, $owner, 'DIS-FIN-3', 'Wallet');
    $transactionId = $loan->disbursement->transaction_id;

    $this->actingAs($student)
        ->get(route('transactions.show', $transactionId))
        ->assertOk();

    $this->actingAs($other)
        ->get(route('transactions.show', $transactionId))
        ->assertForbidden();

    $this->actingAs($student)
        ->get(route('loans.active.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loan.disbursement.transaction_id', $transactionId));
});

test('demo active loan posts Loan income through real disbursement path', function () {
    seedDefaultCategories();
    app(\App\Support\Demo\LendingDemoBootstrap::class)->seed(withActiveLoan: true);

    $loan = Loan::query()
        ->whereHas('application', fn ($q) => $q->where('reference', \App\Support\Demo\LendingDemoIds::APP_ACTIVE_REFERENCE))
        ->firstOrFail();

    $disbursement = $loan->disbursement()->firstOrFail();
    expect($disbursement->transaction_id)->not->toBeNull();

    $income = Transaction::query()->findOrFail($disbursement->transaction_id);
    expect($income->type)->toBe(TransactionType::Income)
        ->and($income->category->name)->toBe('Loan')
        ->and($income->user_id)->toBe($loan->user_id);

    // Re-seed remains idempotent for finance posting.
    app(\App\Support\Demo\LendingDemoBootstrap::class)->seed(withActiveLoan: true);
    expect(Transaction::query()->where('user_id', $loan->user_id)->whereHas('category', fn ($q) => $q->where('name', 'Loan'))->count())->toBe(1);
});

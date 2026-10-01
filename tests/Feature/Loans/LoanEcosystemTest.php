<?php

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanApplicationStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Loans\PaymentStatus;
use App\Enums\Role;
use App\Models\Bank;
use App\Models\BankMembership;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanAuditLog;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanPaymentEvent;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loans\BankRegistrationService;
use App\Services\Loans\LoanWorkflowService;
use App\Services\Loans\Payments\LoanPaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createApprovedBankWithOwner(string $name = 'Campus Bank'): array
{
    $owner = User::factory()->bank()->create([
        'name' => 'Bank Owner',
        'email' => strtolower(str_replace(' ', '', $name)).'-owner@example.com',
    ]);

    $bank = Bank::query()->create([
        'name' => $name,
        'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
        'reference_code' => 'BANK-REQ-'.strtoupper(substr(uniqid(), -8)),
        'official_email' => 'ops@'.strtolower(str_replace(' ', '', $name)).'.test',
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

    return [$bank->fresh(), $owner->fresh()];
}

function createPublishedProduct(Bank $bank, array $overrides = []): LoanProduct
{
    return LoanProduct::query()->create(array_merge([
        'bank_id' => $bank->id,
        'name' => 'Student Tuition Loan',
        'slug' => 'student-tuition-'.uniqid(),
        'description' => 'Tuition support for enrolled students.',
        'min_amount' => '10000.00',
        'max_amount' => '500000.00',
        'min_tenure_months' => 3,
        'max_tenure_months' => 24,
        'repayment_frequency' => 'monthly',
        'annual_rate_percent' => '12.0000',
        'processing_fee_percent' => '0',
        'processing_fee_fixed' => '0',
        'allows_partial_payments' => true,
        'required_documents' => ['student_id'],
        'eligibility_notes' => ['Enrolled student'],
        'is_published' => true,
        'status' => 'active',
    ], $overrides));
}

test('public student loans page hides pending bank products', function () {
    [$pendingBank] = createApprovedBankWithOwner('Pending Bank Co');
    $pendingBank->forceFill(['status' => BankStatus::Pending, 'approved_at' => null])->save();
    createPublishedProduct($pendingBank, ['name' => 'Hidden Product']);

    [$activeBank] = createApprovedBankWithOwner('Active Bank Co');
    createPublishedProduct($activeBank, ['name' => 'Visible Product']);

    $this->get(route('student-loans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/loans/index')
            ->has('products', 1)
            ->where('products.0.name', 'Visible Product'));
});

test('bank registration stays pending until admin approval', function () {
    Storage::fake('local');

    $this->post(route('bank.register.store'), [
        'name' => 'New Partner Bank',
        'official_email' => 'contact@newpartner.test',
        'authorized_representative' => 'Ayesha Khan',
        'representative_email' => 'ayesha@newpartner.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'authorization_confirmed' => '1',
        'documents' => [UploadedFile::fake()->create('license.pdf', 100, 'application/pdf')],
    ])->assertRedirect();

    $bank = Bank::query()->where('name', 'New Partner Bank')->firstOrFail();
    expect($bank->status)->toBe(BankStatus::Pending);
    expect($bank->reference_code)->toStartWith('BANK-REQ-');

    $this->get(route('bank.registration.status', ['ref' => $bank->reference_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bank/registration-status')
            ->where('bank.reference_code', $bank->reference_code)
            ->where('bank.status', 'pending'));

    $owner = User::query()->where('email', 'ayesha@newpartner.test')->firstOrFail();
    expect($owner->role)->toBe(Role::Bank);

    $this->post('/login', [
        'email' => 'ayesha@newpartner.test',
        'password' => 'password',
    ])->assertRedirect(route('login'));

    $this->assertGuest();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)
        ->get(route('admin.banks.index', ['status' => 'pending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/banks/index'));

    $this->actingAs($admin)
        ->post(route('admin.banks.approve', $bank))
        ->assertRedirect();

    expect($bank->fresh()->status)->toBe(BankStatus::Approved);
    expect(LoanAuditLog::query()->where('action', 'bank.approved')->exists())->toBeTrue();

    $this->post('/logout');

    $this->post('/login', [
        'email' => 'ayesha@newpartner.test',
        'password' => 'password',
    ])->assertRedirect(route('bank.dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner->fresh());
});

test('duplicate bank official email registration is rejected', function () {
    Storage::fake('local');

    $payload = [
        'name' => 'Duplicate Email Bank',
        'official_email' => 'ops@duplicatebank.test',
        'authorized_representative' => 'Owner One',
        'representative_email' => 'owner1@duplicatebank.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'authorization_confirmed' => '1',
    ];

    $this->post(route('bank.register.store'), $payload)->assertRedirect();

    $this->post(route('bank.register.store'), array_merge($payload, [
        'name' => 'Duplicate Email Bank Two',
        'representative_email' => 'owner2@duplicatebank.test',
    ]))->assertSessionHasErrors('official_email');

    expect(Bank::query()->where('official_email', 'ops@duplicatebank.test')->count())->toBe(1);
});

test('bank cannot access another bank application by id', function () {
    [$bankA, $ownerA] = createApprovedBankWithOwner('Bank A');
    [$bankB, $ownerB] = createApprovedBankWithOwner('Bank B');
    $product = createPublishedProduct($bankA);
    $student = User::factory()->create();

    $application = LoanApplication::query()->create([
        'reference' => 'APP-TEST-1',
        'user_id' => $student->id,
        'bank_id' => $bankA->id,
        'loan_product_id' => $product->id,
        'requested_amount' => '50000.00',
        'requested_tenure_months' => 6,
        'status' => LoanApplicationStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $this->actingAs($ownerB)
        ->get(route('bank.applications.show', $application))
        ->assertNotFound();

    $this->actingAs($ownerA)
        ->get(route('bank.applications.show', $application))
        ->assertOk();
});

test('student cannot access another students application', function () {
    [$bank] = createApprovedBankWithOwner('Isolation Bank');
    $product = createPublishedProduct($bank);
    $studentA = User::factory()->create();
    $studentB = User::factory()->create();

    $application = LoanApplication::query()->create([
        'reference' => 'APP-TEST-2',
        'user_id' => $studentA->id,
        'bank_id' => $bank->id,
        'loan_product_id' => $product->id,
        'requested_amount' => '25000.00',
        'requested_tenure_months' => 6,
        'status' => LoanApplicationStatus::Draft,
    ]);

    $this->actingAs($studentB)
        ->get(route('loans.applications.show', $application))
        ->assertForbidden();
});

test('student cannot approve a loan application', function () {
    [$bank] = createApprovedBankWithOwner('Decision Bank');
    $product = createPublishedProduct($bank);
    $student = User::factory()->create();

    $application = LoanApplication::query()->create([
        'reference' => 'APP-TEST-3',
        'user_id' => $student->id,
        'bank_id' => $bank->id,
        'loan_product_id' => $product->id,
        'requested_amount' => '30000.00',
        'requested_tenure_months' => 6,
        'status' => LoanApplicationStatus::Submitted,
        'submitted_at' => now(),
    ]);

    $this->actingAs($student)
        ->post(route('bank.applications.approve', $application))
        ->assertForbidden();
});

test('full loan workflow from apply to sandbox payment', function () {
    [$bank, $owner] = createApprovedBankWithOwner('Workflow Bank');
    $product = createPublishedProduct($bank);
    $student = User::factory()->create();
    $workflow = app(LoanWorkflowService::class);
    $payments = app(LoanPaymentService::class);

    $application = $workflow->createDraft($student, $product, [
        'requested_amount' => '120000.00',
        'requested_tenure_months' => 6,
        'purpose' => 'Tuition',
    ]);
    expect($application->status)->toBe(LoanApplicationStatus::Draft);

    $application = $workflow->submit($application, $student);
    expect($application->status)->toBe(LoanApplicationStatus::Submitted);

    $application = $workflow->markUnderReview($application, $owner);
    expect($application->status)->toBe(LoanApplicationStatus::UnderReview);

    $loan = $workflow->approve($application, $owner);
    expect($loan->status)->toBe(LoanStatus::AgreementPending);
    expect($loan->installments)->toHaveCount(6);

    $loan = $workflow->acceptAgreement($loan, $student);
    expect($loan->status)->toBe(LoanStatus::ReadyForDisbursement);

    $loan = $workflow->confirmDisbursement($loan, $owner, 'DIS-REF-1', 'Student wallet');
    expect($loan->status)->toBe(LoanStatus::Active);

    $installment = $loan->installments()->orderBy('installment_number')->firstOrFail();
    expect($installment->status)->toBe(InstallmentStatus::Due);

    $checkout = $payments->initiate($student, $installment, (string) $installment->amount_due);
    /** @var LoanPayment $payment */
    $payment = $checkout['payment'];
    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->card_last_four)->toBeNull();

    $payload = [
        'provider_transaction_id' => $payment->provider_transaction_id,
        'event_id' => 'evt_success_1',
        'event_type' => 'payment.succeeded',
        'status' => 'succeeded',
        'card_last_four' => '4242',
    ];

    $payments->handleWebhook($payload, null);

    $payment->refresh();
    $installment->refresh();
    $loan->refresh();

    expect($payment->status)->toBe(PaymentStatus::Succeeded);
    expect($payment->transaction_id)->not->toBeNull();
    expect($installment->status)->toBe(InstallmentStatus::Paid);
    expect((float) $loan->outstanding_balance)->toBeLessThan((float) $loan->total_repayable);

    // Duplicate webhook must be ignored safely.
    $payments->handleWebhook($payload, null);
    expect(LoanPaymentEvent::query()->where('provider_event_id', 'evt_success_1')->count())->toBe(1);
    expect($installment->fresh()->status)->toBe(InstallmentStatus::Paid);
});

test('admin cannot underwrite loans for a bank', function () {
    [$bank] = createApprovedBankWithOwner('Admin Boundary Bank');
    $product = createPublishedProduct($bank);
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $application = LoanApplication::query()->create([
        'reference' => 'APP-TEST-4',
        'user_id' => $student->id,
        'bank_id' => $bank->id,
        'loan_product_id' => $product->id,
        'requested_amount' => '40000.00',
        'requested_tenure_months' => 6,
        'status' => LoanApplicationStatus::Submitted,
        'submitted_at' => now(),
    ]);

    expect(fn () => app(LoanWorkflowService::class)->approve($application, $admin))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('bank registration service approval activates bank once', function () {
    $admin = User::factory()->admin()->create();
    $service = app(BankRegistrationService::class);

    $bank = $service->register([
        'name' => 'Service Bank',
        'official_email' => 'hello@servicebank.test',
        'authorized_representative' => 'Rep Name',
        'representative_email' => 'rep@servicebank.test',
        'password' => 'password',
    ], []);

    expect($bank->status)->toBe(BankStatus::Pending);

    $approved = $service->approve($bank, $admin);
    expect($approved->status)->toBe(BankStatus::Approved);
    expect($approved->approved_by)->toBe($admin->id);
});

<?php

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanApplicationStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Loans\PaymentStatus;
use App\Enums\Role;
use App\Models\Bank;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanPayment;
use App\Models\LoanPaymentEvent;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\Loans\Payments\LoanPaymentService;
use App\Support\Demo\LendingDemoBootstrap;
use App\Support\Demo\LendingDemoIds;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    app(LendingDemoBootstrap::class)->seed(withActiveLoan: true);
});

test('lending demo seed creates bank users product and applications idempotently', function () {
    $firstBankCount = Bank::query()->where('slug', LendingDemoIds::BANK_SLUG)->count();
    $firstProductCount = LoanProduct::query()->where('slug', LendingDemoIds::PRODUCT_SLUG)->count();
    $firstWalkthrough = LoanApplication::query()->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)->count();
    $firstUsers = User::query()->whereIn('email', [
        LendingDemoIds::STUDENT_EMAIL,
        LendingDemoIds::OWNER_EMAIL,
        LendingDemoIds::OFFICER_EMAIL,
    ])->count();

    expect($firstBankCount)->toBe(1)
        ->and($firstProductCount)->toBe(1)
        ->and($firstWalkthrough)->toBe(1)
        ->and($firstUsers)->toBe(3);

    app(LendingDemoBootstrap::class)->seed(withActiveLoan: true);

    expect(Bank::query()->where('slug', LendingDemoIds::BANK_SLUG)->count())->toBe(1)
        ->and(LoanProduct::query()->where('slug', LendingDemoIds::PRODUCT_SLUG)->count())->toBe(1)
        ->and(LoanApplication::query()->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)->count())->toBe(1)
        ->and(LoanApplication::query()->where('reference', LendingDemoIds::APP_ACTIVE_REFERENCE)->count())->toBe(1)
        ->and(User::query()->whereIn('email', [
            LendingDemoIds::STUDENT_EMAIL,
            LendingDemoIds::OWNER_EMAIL,
            LendingDemoIds::OFFICER_EMAIL,
        ])->count())->toBe(3);

    $bank = Bank::query()->where('slug', LendingDemoIds::BANK_SLUG)->firstOrFail();
    expect($bank->status)->toBe(BankStatus::Approved)
        ->and($bank->name)->toBe(LendingDemoIds::BANK_NAME);

    $product = LoanProduct::query()->where('slug', LendingDemoIds::PRODUCT_SLUG)->firstOrFail();
    expect($product->is_published)->toBeTrue()
        ->and($product->status)->toBe('active')
        ->and($product->bank_id)->toBe($bank->id);

    $walkthrough = LoanApplication::query()->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)->firstOrFail();
    expect($walkthrough->status)->toBe(LoanApplicationStatus::Submitted);

    $activeLoan = Loan::query()
        ->whereHas('application', fn ($q) => $q->where('reference', LendingDemoIds::APP_ACTIVE_REFERENCE))
        ->firstOrFail();
    expect($activeLoan->status)->toBe(LoanStatus::Active)
        ->and($activeLoan->installments()->count())->toBeGreaterThan(0);
});

test('demo student and bank owner can authenticate with documented passwords', function () {
    $student = User::query()->where('email', LendingDemoIds::STUDENT_EMAIL)->firstOrFail();
    $owner = User::query()->where('email', LendingDemoIds::OWNER_EMAIL)->firstOrFail();
    $officer = User::query()->where('email', LendingDemoIds::OFFICER_EMAIL)->firstOrFail();

    expect($student->role)->toBe(Role::Student)
        ->and(Hash::check(LendingDemoIds::STUDENT_PASSWORD, $student->password))->toBeTrue()
        ->and($owner->role)->toBe(Role::Bank)
        ->and(Hash::check(LendingDemoIds::OWNER_PASSWORD, $owner->password))->toBeTrue()
        ->and($officer->role)->toBe(Role::Bank)
        ->and(Hash::check(LendingDemoIds::OFFICER_PASSWORD, $officer->password))->toBeTrue();

    $this->post('/login', [
        'email' => LendingDemoIds::STUDENT_EMAIL,
        'password' => LendingDemoIds::STUDENT_PASSWORD,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($student);

    $this->post('/logout');

    $this->post('/login', [
        'email' => LendingDemoIds::OWNER_EMAIL,
        'password' => LendingDemoIds::OWNER_PASSWORD,
    ])->assertRedirect(route('bank.dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner);
});

test('demo bank staff see own product and walkthrough application only for their bank', function () {
    $owner = User::query()->where('email', LendingDemoIds::OWNER_EMAIL)->firstOrFail();
    $officer = User::query()->where('email', LendingDemoIds::OFFICER_EMAIL)->firstOrFail();
    $bank = Bank::query()->where('slug', LendingDemoIds::BANK_SLUG)->firstOrFail();
    $walkthrough = LoanApplication::query()->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)->firstOrFail();

    expect($owner->bankMemberships()->where('bank_id', $bank->id)->where('staff_role', BankStaffRole::Owner)->exists())->toBeTrue()
        ->and($officer->bankMemberships()->where('bank_id', $bank->id)->where('staff_role', BankStaffRole::Officer)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('bank.dashboard'))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('bank.applications.show', $walkthrough))
        ->assertOk();

    $this->actingAs($officer)
        ->get(route('bank.applications.show', $walkthrough))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('bank.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bank/products/index')
            ->has('products', 1));
});

test('demo student can open loan center and public loans lists demo product', function () {
    $student = User::query()->where('email', LendingDemoIds::STUDENT_EMAIL)->firstOrFail();

    $this->get(route('student-loans'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/loans/index')
            ->has('products', 1)
            ->where('products.0.name', LendingDemoIds::PRODUCT_NAME));

    $this->actingAs($student)
        ->get(route('loans.index'))
        ->assertOk();

    $this->actingAs($student)
        ->get(route('loans.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products'));
});

test('demo authorization boundaries hold for student bank and admin surfaces', function () {
    $student = User::query()->where('email', LendingDemoIds::STUDENT_EMAIL)->firstOrFail();
    $owner = User::query()->where('email', LendingDemoIds::OWNER_EMAIL)->firstOrFail();
    $walkthrough = LoanApplication::query()->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)->firstOrFail();

    $this->actingAs($student)
        ->get(route('bank.dashboard'))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('bank.applications.approve', $walkthrough))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.banks.index'))
        ->assertForbidden();
});

test('demo walkthrough approval disbursement and sandbox payment use real services', function () {
    $student = User::query()->where('email', LendingDemoIds::STUDENT_EMAIL)->firstOrFail();
    $owner = User::query()->where('email', LendingDemoIds::OWNER_EMAIL)->firstOrFail();

    app(LendingDemoBootstrap::class)->resetWalkthrough();
    $application = LoanApplication::query()
        ->where('reference', LendingDemoIds::APP_WALKTHROUGH_REFERENCE)
        ->firstOrFail();
    expect($application->status)->toBe(LoanApplicationStatus::Submitted);

    $this->actingAs($owner)
        ->post(route('bank.applications.under-review', $application))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(LoanApplicationStatus::UnderReview);

    $this->actingAs($owner)
        ->post(route('bank.applications.approve', $application))
        ->assertRedirect();

    $loan = Loan::query()->where('loan_application_id', $application->id)->firstOrFail();
    expect($loan->status)->toBe(LoanStatus::AgreementPending);

    $this->actingAs($student)
        ->post(route('loans.active.accept-agreement', $loan))
        ->assertRedirect();

    expect($loan->fresh()->status)->toBe(LoanStatus::ReadyForDisbursement);

    $this->actingAs($owner)
        ->post(route('bank.loans.confirm-disbursement', $loan), [
            'reference' => 'DIS-DEMO-TEST',
            'destination' => 'Demo wallet',
        ])
        ->assertRedirect();

    $loan->refresh();
    expect($loan->status)->toBe(LoanStatus::Active);

    $installment = $loan->installments()->orderBy('installment_number')->firstOrFail();
    expect($installment->status)->toBe(InstallmentStatus::Due);

    $this->actingAs($student)
        ->post(route('loans.active.pay', ['loan' => $loan->id, 'installment' => $installment->id]), [
            'amount' => (string) $installment->amount_due,
        ])
        ->assertRedirect();

    $payment = LoanPayment::query()->where('loan_id', $loan->id)->latest('id')->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Pending);

    $payload = [
        'provider_transaction_id' => $payment->provider_transaction_id,
        'event_id' => 'demo_evt_success_1',
        'event_type' => 'payment.succeeded',
        'status' => 'succeeded',
        'card_last_four' => '4242',
    ];

    app(LoanPaymentService::class)->handleWebhook($payload, null);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->transaction_id)->not->toBeNull()
        ->and($installment->fresh()->status)->toBe(InstallmentStatus::Paid);

    app(LoanPaymentService::class)->handleWebhook($payload, null);
    expect(LoanPaymentEvent::query()->where('provider_event_id', 'demo_evt_success_1')->count())->toBe(1);
});

test('demo sandbox failure leaves installment unpaid', function () {
    $student = User::query()->where('email', LendingDemoIds::STUDENT_EMAIL)->firstOrFail();
    $loan = Loan::query()
        ->whereHas('application', fn ($q) => $q->where('reference', LendingDemoIds::APP_ACTIVE_REFERENCE))
        ->firstOrFail();

    $installment = $loan->installments()->orderBy('installment_number')->firstOrFail();
    $beforeBalance = (string) $loan->outstanding_balance;

    $checkout = app(LoanPaymentService::class)->initiate($student, $installment, (string) $installment->amount_due);
    /** @var LoanPayment $payment */
    $payment = $checkout['payment'];

    app(LoanPaymentService::class)->handleWebhook([
        'provider_transaction_id' => $payment->provider_transaction_id,
        'event_id' => 'demo_evt_fail_1',
        'event_type' => 'payment.failed',
        'status' => 'failed',
        'failure_reason' => 'Sandbox decline',
    ], null);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($installment->fresh()->status)->not->toBe(InstallmentStatus::Paid)
        ->and((string) $loan->fresh()->outstanding_balance)->toBe($beforeBalance)
        ->and($payment->fresh()->transaction_id)->toBeNull();
});

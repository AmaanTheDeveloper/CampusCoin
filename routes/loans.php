<?php

use App\Http\Controllers\Admin\Banks\AdminBankController;
use App\Http\Controllers\Bank\BankApplicationController;
use App\Http\Controllers\Bank\BankDashboardController;
use App\Http\Controllers\Bank\BankLoanController;
use App\Http\Controllers\Bank\BankProductController;
use App\Http\Controllers\Bank\BankRegistrationController;
use App\Http\Controllers\Bank\BankStaffController;
use App\Http\Controllers\Loans\LoanPaymentController;
use App\Http\Controllers\Loans\StudentActiveLoanController;
use App\Http\Controllers\Loans\StudentApplicationController;
use App\Http\Controllers\Loans\StudentLoanController;
use App\Http\Controllers\PublicSite\StudentLoansController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public student loans listing
|--------------------------------------------------------------------------
*/
Route::get('/student-loans', StudentLoansController::class)->name('student-loans');

/*
|--------------------------------------------------------------------------
| Bank registration (guest)
|--------------------------------------------------------------------------
*/
Route::middleware(['guest', 'throttle:5,1'])->group(function () {
    Route::get('/bank/register', [BankRegistrationController::class, 'create'])->name('bank.register');
    Route::post('/bank/register', [BankRegistrationController::class, 'store'])->name('bank.register.store');
    Route::get('/bank/registration/status', [BankRegistrationController::class, 'status'])->name('bank.registration.status');
});

/*
|--------------------------------------------------------------------------
| Bank portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'bank'])->prefix('bank')->name('bank.')->group(function () {
    Route::get('/', BankDashboardController::class)->name('dashboard');

    Route::get('products', [BankProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [BankProductController::class, 'create'])->name('products.create');
    Route::post('products', [BankProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [BankProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/publish', [BankProductController::class, 'publish'])->name('products.publish');

    Route::get('applications', [BankApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [BankApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications/{application}/under-review', [BankApplicationController::class, 'underReview'])->name('applications.under-review');
    Route::post('applications/{application}/request-info', [BankApplicationController::class, 'requestInfo'])->name('applications.request-info');
    Route::post('applications/{application}/approve', [BankApplicationController::class, 'approve'])->name('applications.approve');
    Route::post('applications/{application}/reject', [BankApplicationController::class, 'reject'])->name('applications.reject');

    Route::get('loans', [BankLoanController::class, 'index'])->name('loans.index');
    Route::get('loans/{loan}', [BankLoanController::class, 'show'])->name('loans.show');
    Route::post('loans/{loan}/confirm-disbursement', [BankLoanController::class, 'confirmDisbursement'])->name('loans.confirm-disbursement');

    Route::get('staff', [BankStaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [BankStaffController::class, 'store'])->name('staff.store');
});

/*
|--------------------------------------------------------------------------
| Student loan center
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('loans')->name('loans.')->group(function () {
    Route::get('/', [StudentLoanController::class, 'index'])->name('index');

    Route::get('products', [StudentLoanController::class, 'productsIndex'])->name('products.index');
    Route::get('products/{product}', [StudentLoanController::class, 'productsShow'])->name('products.show');

    Route::get('applications/create', [StudentApplicationController::class, 'create'])->name('applications.create');
    Route::post('applications', [StudentApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [StudentApplicationController::class, 'show'])->name('applications.show');
    Route::get('applications/{application}/edit', [StudentApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('applications/{application}', [StudentApplicationController::class, 'update'])->name('applications.update');
    Route::post('applications/{application}/submit', [StudentApplicationController::class, 'submit'])->name('applications.submit');
    Route::post('applications/{application}/documents', [StudentApplicationController::class, 'uploadDocument'])->name('applications.documents.store');

    Route::get('active/{loan}', [StudentActiveLoanController::class, 'show'])->name('active.show');
    Route::post('active/{loan}/accept-agreement', [StudentActiveLoanController::class, 'acceptAgreement'])->name('active.accept-agreement');
    Route::post('active/{loan}/installments/{installment}/pay', [StudentActiveLoanController::class, 'payInstallment'])->name('active.pay');

    Route::get('payments/{payment}/sandbox', [LoanPaymentController::class, 'sandbox'])->name('payments.sandbox');
    Route::post('payments/{payment}/sandbox/complete', [LoanPaymentController::class, 'completeSandbox'])->name('payments.sandbox.complete');
});

/*
|--------------------------------------------------------------------------
| Payment webhooks
|--------------------------------------------------------------------------
*/
Route::post('webhooks/loans/payments', [LoanPaymentController::class, 'webhook'])
    ->name('webhooks.loans.payments');

/*
|--------------------------------------------------------------------------
| Admin bank verification
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('banks', [AdminBankController::class, 'index'])->name('banks.index');
    Route::get('banks/{bank}', [AdminBankController::class, 'show'])->name('banks.show');
    Route::post('banks/{bank}/approve', [AdminBankController::class, 'approve'])->name('banks.approve');
    Route::post('banks/{bank}/reject', [AdminBankController::class, 'reject'])->name('banks.reject');
    Route::post('banks/{bank}/suspend', [AdminBankController::class, 'suspend'])->name('banks.suspend');
    Route::post('banks/{bank}/reactivate', [AdminBankController::class, 'reactivate'])->name('banks.reactivate');
});

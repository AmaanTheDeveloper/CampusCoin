<?php

namespace App\Http\Controllers\Bank;

use App\Enums\Loans\InstallmentStatus;
use App\Enums\Loans\LoanApplicationStatus;
use App\Enums\Loans\LoanStatus;
use App\Enums\Loans\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');

        $newApplications = $bank->applications()
            ->where('status', LoanApplicationStatus::Submitted)
            ->count();

        $underReview = $bank->applications()
            ->where('status', LoanApplicationStatus::UnderReview)
            ->count();

        $moreInfo = $bank->applications()
            ->where('status', LoanApplicationStatus::MoreInformationRequired)
            ->count();

        $approvedApps = $bank->applications()
            ->where('status', LoanApplicationStatus::Approved)
            ->count();

        $pendingDisbursement = $bank->loans()
            ->whereIn('status', [LoanStatus::AgreementPending, LoanStatus::ReadyForDisbursement])
            ->count();

        $activeLoans = $bank->loans()
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Disbursed])
            ->count();

        $overdueInstallments = LoanInstallment::query()
            ->whereHas('loan', fn ($q) => $q->where('bank_id', $bank->id))
            ->where('status', InstallmentStatus::Overdue)
            ->count();

        $paymentIssues = LoanPayment::query()
            ->whereHas('loan', fn ($q) => $q->where('bank_id', $bank->id))
            ->where('status', PaymentStatus::Failed)
            ->count();

        $publishedProducts = $bank->products()->where('is_published', true)->count();

        $recentApplications = $bank->applications()
            ->with(['user:id,name,email', 'product:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($application) => [
                'id' => $application->id,
                'reference' => $application->reference,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'requested_amount' => (string) $application->requested_amount,
                'student' => $application->user?->name,
                'product' => $application->product?->name,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('bank/dashboard', [
            'eyebrow' => 'Bank portal',
            'title' => $bank->name,
            'description' => 'Review applications, manage products, and confirm disbursements.',
            'bank' => [
                'id' => $bank->id,
                'name' => $bank->name,
                'status' => $bank->status->value,
                'status_label' => $bank->status->label(),
            ],
            'stats' => [
                'new_applications' => $newApplications,
                'under_review' => $underReview,
                'more_information_required' => $moreInfo,
                'approved_applications' => $approvedApps,
                'pending_disbursement' => $pendingDisbursement,
                'active_loans' => $activeLoans,
                'overdue_installments' => $overdueInstallments,
                'payment_issues' => $paymentIssues,
                'published_products' => $publishedProducts,
                // Back-compat for any leftover consumers
                'pending_applications' => $newApplications + $underReview + $moreInfo,
                'products_total' => $bank->products()->count(),
            ],
            'recent_applications' => $recentApplications,
        ]);
    }
}

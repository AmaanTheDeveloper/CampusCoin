<?php

namespace App\Http\Controllers\Loans;

use App\Enums\Loans\BankStatus;
use App\Enums\Loans\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentLoanController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $applications = $user->loanApplications()
            ->with(['product:id,name', 'bank:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($application) => [
                'id' => $application->id,
                'reference' => $application->reference,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'requested_amount' => (string) $application->requested_amount,
                'product' => $application->product?->name,
                'bank' => $application->bank?->name,
            ]);

        $activeLoans = $user->loans()
            ->with(['product:id,name', 'bank:id,name', 'installments'])
            ->whereIn('status', [
                LoanStatus::AgreementPending,
                LoanStatus::ReadyForDisbursement,
                LoanStatus::Disbursed,
                LoanStatus::Active,
            ])
            ->latest()
            ->get()
            ->map(function ($loan) {
                $next = $loan->installments
                    ->filter(fn ($row) => in_array($row->status->value, ['due', 'partially_paid', 'overdue', 'upcoming'], true))
                    ->sortBy(fn ($row) => [$row->due_date?->timestamp ?? PHP_INT_MAX, $row->installment_number])
                    ->first();

                $payable = $loan->installments
                    ->first(fn ($row) => in_array($row->status->value, ['due', 'partially_paid', 'overdue'], true)
                        && (float) $row->remainingAmount() > 0);

                return [
                    'id' => $loan->id,
                    'reference' => $loan->reference,
                    'status' => $loan->status->value,
                    'status_label' => $loan->status->label(),
                    'outstanding_balance' => (string) $loan->outstanding_balance,
                    'principal_amount' => (string) $loan->principal_amount,
                    'product' => $loan->product?->name,
                    'bank' => $loan->bank?->name,
                    'next_installment' => $next ? [
                        'id' => $next->id,
                        'installment_number' => $next->installment_number,
                        'due_date' => $next->due_date?->toDateString(),
                        'remaining' => (string) $next->remainingAmount(),
                        'status' => $next->status->value,
                        'status_label' => $next->status->label(),
                        'can_pay' => $payable !== null
                            && in_array($next->status->value, ['due', 'partially_paid', 'overdue'], true)
                            && (float) $next->remainingAmount() > 0,
                    ] : null,
                ];
            });

        $completedLoans = $user->loans()
            ->with(['product:id,name', 'bank:id,name'])
            ->where('status', LoanStatus::Completed)
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn ($loan) => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'outstanding_balance' => (string) $loan->outstanding_balance,
                'principal_amount' => (string) $loan->principal_amount,
                'product' => $loan->product?->name,
                'bank' => $loan->bank?->name,
                'completed_at' => $loan->completed_at?->toDateString(),
            ]);

        return Inertia::render('loans/index', [
            'eyebrow' => 'Student loans',
            'title' => 'Loan center',
            'description' => 'Browse products, track applications, and manage active loans.',
            'applications' => $applications,
            'active_loans' => $activeLoans,
            'completed_loans' => $completedLoans,
        ]);
    }

    public function productsIndex(): Response
    {
        $products = LoanProduct::query()
            ->with('bank:id,name,city,country')
            ->where('is_published', true)
            ->where('status', 'active')
            ->whereHas('bank', fn ($q) => $q->where('status', BankStatus::Approved)->whereNull('suspended_at'))
            ->latest()
            ->paginate(12)
            ->through(fn (LoanProduct $product) => $this->productCard($product));

        return Inertia::render('loans/products/index', [
            'eyebrow' => 'Catalog',
            'title' => 'Loan products',
            'description' => 'Compare published student loan products from approved partner banks.',
            'products' => $products,
        ]);
    }

    public function productsShow(LoanProduct $product): Response
    {
        $product->load('bank:id,name,city,country,status,suspended_at');
        abort_unless($product->isAvailable(), 404);

        return Inertia::render('loans/products/show', [
            'eyebrow' => $product->bank?->name ?? 'Loan product',
            'title' => $product->name,
            'description' => $product->description ?: 'Review terms and start an application.',
            'product' => [
                ...$this->productCard($product),
                'description' => $product->description,
                'repayment_frequency' => $product->repayment_frequency,
                'processing_fee_percent' => (string) $product->processing_fee_percent,
                'processing_fee_fixed' => (string) $product->processing_fee_fixed,
                'allows_partial_payments' => $product->allows_partial_payments,
                'required_documents' => $product->required_documents ?? [],
                'eligibility_notes' => $product->eligibility_notes ?? [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(LoanProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'min_amount' => (string) $product->min_amount,
            'max_amount' => (string) $product->max_amount,
            'min_tenure_months' => $product->min_tenure_months,
            'max_tenure_months' => $product->max_tenure_months,
            'annual_rate_percent' => (string) $product->annual_rate_percent,
            'bank' => [
                'id' => $product->bank?->id,
                'name' => $product->bank?->name,
                'city' => $product->bank?->city,
                'country' => $product->bank?->country,
            ],
        ];
    }
}

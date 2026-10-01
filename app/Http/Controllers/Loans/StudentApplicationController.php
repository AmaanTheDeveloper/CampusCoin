<?php

namespace App\Http\Controllers\Loans;

use App\Enums\Loans\LoanApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\Loans\LoanWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentApplicationController extends Controller
{
    public function create(Request $request): Response
    {
        $productId = (int) $request->query('product');
        $product = LoanProduct::query()->with('bank')->findOrFail($productId);

        abort_unless($product->isAvailable(), 404);

        return Inertia::render('loans/applications/create', [
            'eyebrow' => 'Apply',
            'title' => 'New loan application',
            'description' => 'Draft your request for '.$product->name.'.',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'min_amount' => (string) $product->min_amount,
                'max_amount' => (string) $product->max_amount,
                'min_tenure_months' => $product->min_tenure_months,
                'max_tenure_months' => $product->max_tenure_months,
                'annual_rate_percent' => (string) $product->annual_rate_percent,
                'required_documents' => $product->required_documents ?? [],
                'bank' => $product->bank?->only(['id', 'name']),
            ],
            'application' => null,
        ]);
    }

    public function store(Request $request, LoanWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);

        $application = $workflow->createDraft($request->user(), $product, $data);

        return redirect()
            ->route('loans.applications.show', $application)
            ->with('success', 'Draft application saved.');
    }

    public function edit(Request $request, LoanApplication $application): Response
    {
        $this->assertOwner($request, $application);
        abort_unless(
            in_array($application->status, [
                LoanApplicationStatus::Draft,
                LoanApplicationStatus::MoreInformationRequired,
            ], true),
            403,
        );

        $application->load(['product.bank:id,name', 'documents']);

        return Inertia::render('loans/applications/create', [
            'eyebrow' => 'Edit application',
            'title' => $application->reference,
            'description' => 'Update your draft before submitting.',
            'product' => [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'min_amount' => (string) $application->product->min_amount,
                'max_amount' => (string) $application->product->max_amount,
                'min_tenure_months' => $application->product->min_tenure_months,
                'max_tenure_months' => $application->product->max_tenure_months,
                'annual_rate_percent' => (string) $application->product->annual_rate_percent,
                'required_documents' => $application->product->required_documents ?? [],
                'bank' => $application->product->bank?->only(['id', 'name']),
            ],
            'application' => $this->serialize($application),
        ]);
    }

    public function update(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        $this->assertOwner($request, $application);

        $data = $request->validate([
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $workflow->updateDraft($application, $request->user(), $data);

        return redirect()
            ->route('loans.applications.show', $application)
            ->with('success', 'Application updated.');
    }

    public function submit(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        $this->assertOwner($request, $application);

        $workflow->submit($application, $request->user());

        return redirect()
            ->route('loans.applications.show', $application)
            ->with('success', 'Application submitted to the bank.');
    }

    public function uploadDocument(
        Request $request,
        LoanApplication $application,
        LoanWorkflowService $workflow,
    ): RedirectResponse {
        $this->assertOwner($request, $application);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:64'],
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $workflow->storeApplicationDocument(
            $application,
            $request->user(),
            $data['document_type'],
            $request->file('document'),
        );

        return back()->with('success', 'Document uploaded.');
    }

    public function show(Request $request, LoanApplication $application): Response
    {
        $this->assertOwner($request, $application);

        $application->load([
            'product.bank:id,name',
            'documents',
            'events' => fn ($q) => $q->latest('created_at'),
            'loan',
        ]);

        return Inertia::render('loans/applications/show', [
            'eyebrow' => 'Application',
            'title' => $application->reference,
            'description' => 'Track status, upload documents, and submit when ready.',
            'application' => $this->serialize($application),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(LoanApplication $application): array
    {
        return [
            'id' => $application->id,
            'reference' => $application->reference,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'requested_amount' => (string) $application->requested_amount,
            'requested_tenure_months' => $application->requested_tenure_months,
            'purpose' => $application->purpose,
            'applicant_notes' => $application->applicant_notes,
            'rejection_reason' => $application->rejection_reason,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'decided_at' => $application->decided_at?->toIso8601String(),
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'required_documents' => $application->product->required_documents ?? [],
                'bank' => $application->product->bank?->only(['id', 'name']),
            ] : null,
            'documents' => $application->relationLoaded('documents')
                ? $application->documents->map(fn ($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'original_name' => $doc->original_name,
                    'status' => $doc->status,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ])
                : [],
            'events' => $application->relationLoaded('events')
                ? $application->events->map(fn ($event) => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'note' => $event->note,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                : [],
            'loan_id' => $application->loan?->id,
            'can_edit' => in_array($application->status, [
                LoanApplicationStatus::Draft,
                LoanApplicationStatus::MoreInformationRequired,
            ], true),
            'can_submit' => in_array($application->status, [
                LoanApplicationStatus::Draft,
                LoanApplicationStatus::MoreInformationRequired,
            ], true),
        ];
    }

    private function assertOwner(Request $request, LoanApplication $application): void
    {
        abort_unless($application->user_id === $request->user()->id, 403);
    }
}

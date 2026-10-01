<?php

namespace App\Http\Controllers\Bank;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankProductController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');

        $products = $bank->products()
            ->latest()
            ->get()
            ->map(fn (LoanProduct $product) => $this->productPayload($product));

        return Inertia::render('bank/products/index', [
            'eyebrow' => 'Products',
            'title' => 'Loan products',
            'description' => 'Create and publish student loan products for your bank.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'products' => $products,
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');

        return Inertia::render('bank/products/create', [
            'eyebrow' => 'Products',
            'title' => 'New loan product',
            'description' => 'Define amounts, tenure, rates, and eligibility for a student product.',
            'bank' => ['id' => $bank->id, 'name' => $bank->name],
            'product' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $validated = $this->validatedProduct($request);

        $product = $bank->products()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($bank, $validated['name']),
            'is_published' => false,
            'status' => 'draft',
        ]);

        return redirect()
            ->route('bank.products.index')
            ->with('success', "Product {$product->name} created.");
    }

    public function update(Request $request, LoanProduct $product): RedirectResponse
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankProduct($bank, $product);

        $validated = $this->validatedProduct($request);

        $product->fill([
            ...$validated,
            'slug' => $product->name === $validated['name']
                ? $product->slug
                : $this->uniqueSlug($bank, $validated['name'], $product->id),
        ])->save();

        return redirect()
            ->route('bank.products.index')
            ->with('success', "Product {$product->name} updated.");
    }

    public function publish(Request $request, LoanProduct $product): RedirectResponse
    {
        /** @var Bank $bank */
        $bank = $request->attributes->get('bank');
        $this->assertBankProduct($bank, $product);

        $request->validate([
            'publish' => ['sometimes', 'boolean'],
        ]);

        $publish = $request->boolean('publish', ! $product->is_published);

        $product->forceFill([
            'is_published' => $publish,
            'status' => $publish ? 'active' : 'draft',
        ])->save();

        return back()->with(
            'success',
            $publish
                ? "Product {$product->name} published."
                : "Product {$product->name} unpublished.",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'min_amount' => ['required', 'numeric', 'min:1'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'min_tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'max_tenure_months' => ['required', 'integer', 'gte:min_tenure_months', 'max:120'],
            'repayment_frequency' => ['required', Rule::in(['monthly'])],
            'annual_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'processing_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'processing_fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'allows_partial_payments' => ['sometimes', 'boolean'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string', 'max:120'],
            'eligibility_notes' => ['nullable', 'array'],
            'eligibility_notes.*' => ['string', 'max:255'],
        ]);

        $validated['processing_fee_percent'] = $validated['processing_fee_percent'] ?? 0;
        $validated['processing_fee_fixed'] = $validated['processing_fee_fixed'] ?? 0;
        $validated['allows_partial_payments'] = $request->boolean('allows_partial_payments');

        return $validated;
    }

    private function assertBankProduct(Bank $bank, LoanProduct $product): void
    {
        abort_unless($product->bank_id === $bank->id, 404);
    }

    private function uniqueSlug(Bank $bank, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (
            LoanProduct::query()
                ->where('bank_id', $bank->id)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(LoanProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'min_amount' => (string) $product->min_amount,
            'max_amount' => (string) $product->max_amount,
            'min_tenure_months' => $product->min_tenure_months,
            'max_tenure_months' => $product->max_tenure_months,
            'repayment_frequency' => $product->repayment_frequency,
            'annual_rate_percent' => (string) $product->annual_rate_percent,
            'processing_fee_percent' => (string) $product->processing_fee_percent,
            'processing_fee_fixed' => (string) $product->processing_fee_fixed,
            'allows_partial_payments' => $product->allows_partial_payments,
            'required_documents' => $product->required_documents ?? [],
            'eligibility_notes' => $product->eligibility_notes ?? [],
            'is_published' => $product->is_published,
            'status' => $product->status,
        ];
    }
}

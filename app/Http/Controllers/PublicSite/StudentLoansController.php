<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Inertia\Inertia;
use Inertia\Response;

class StudentLoansController extends Controller
{
    public function __invoke(): Response
    {
        $products = LoanProduct::query()
            ->with(['bank:id,name,slug,city,country'])
            ->where('is_published', true)
            ->where('status', 'active')
            ->whereHas('bank', fn ($q) => $q->where('status', 'approved')->whereNull('suspended_at'))
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (LoanProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'min_amount' => (string) $product->min_amount,
                'max_amount' => (string) $product->max_amount,
                'min_tenure_months' => $product->min_tenure_months,
                'max_tenure_months' => $product->max_tenure_months,
                'annual_rate_percent' => (string) $product->annual_rate_percent,
                'bank' => $product->bank ? [
                    'id' => $product->bank->id,
                    'name' => $product->bank->name,
                    'slug' => $product->bank->slug,
                    'city' => $product->bank->city,
                    'country' => $product->bank->country,
                ] : null,
            ]);

        return Inertia::render('public/loans/index', [
            'eyebrow' => 'CampusCoin',
            'title' => 'Student loans',
            'description' => 'Explore loan products from verified partner banks on CampusCoin.',
            'products' => $products,
        ]);
    }
}

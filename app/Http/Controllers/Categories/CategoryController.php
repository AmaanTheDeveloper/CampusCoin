<?php

namespace App\Http\Controllers\Categories;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->accessibleTo($request->user())
            ->orderByDesc('is_default')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'is_default' => $category->is_default,
                'is_personal' => $category->user_id !== null,
                'can_edit' => $request->user()->can('update', $category),
                'can_delete' => $request->user()->can('delete', $category),
            ]);

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'typeOptions' => TransactionType::options(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        return Inertia::render('categories/create', [
            'typeOptions' => TransactionType::options(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $request->user()->categories()->create([
            ...$request->validated(),
            'is_default' => false,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', $category);

        return Inertia::render('categories/edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
            ],
            'typeOptions' => TransactionType::options(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->transactions()->exists() || $category->recurringTransactions()->exists() || $category->budgets()->exists()) {
            return back()->withErrors([
                'category' => 'This category is being used by existing transactions or budgets.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted.');
    }
}

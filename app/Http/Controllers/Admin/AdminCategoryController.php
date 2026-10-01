<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Admin\AdminActivityLogger;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->whereNull('user_id')
            ->withCount('transactions')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'is_default' => $category->is_default,
                'is_active' => $category->is_active,
                'usage_count' => $category->transactions_count,
                'is_system' => true,
            ]);

        return Inertia::render('admin/categories/index', [
            'income' => $categories->where('type', TransactionType::Income->value)->values(),
            'expense' => $categories->where('type', TransactionType::Expense->value)->values(),
            'type_options' => [
                ['value' => TransactionType::Income->value, 'label' => 'Income'],
                ['value' => TransactionType::Expense->value, 'label' => 'Expense'],
            ],
        ]);
    }

    public function store(Request $request, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(TransactionType::values())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $exists = Category::query()
            ->whereNull('user_id')
            ->where('type', $data['type'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A system category with this name already exists for that type.']);
        }

        $category = Category::query()->create([
            'name' => $data['name'],
            'type' => $data['type'],
        ]);
        $category->forceFill([
            'user_id' => null,
            'is_default' => true,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        $logger->log($request->user(), 'category.create', "Created system category {$category->name}", $category);

        $notifications->notifyAdmins(
            eventType: 'category.created',
            title: 'System category created',
            message: "{$category->name} was added to system {$category->type->value} categories.",
            severity: 'success',
            actionUrl: '/admin/categories',
            actionLabel: 'View categories',
        );

        return back()->with('success', 'System category created.');
    }

    public function update(Request $request, Category $category, AdminActivityLogger $logger): RedirectResponse
    {
        abort_unless($category->user_id === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(TransactionType::values())],
            'is_active' => ['required', 'boolean'],
        ]);

        $exists = Category::query()
            ->whereNull('user_id')
            ->where('type', $data['type'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->whereKeyNot($category->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A system category with this name already exists for that type.']);
        }

        $category->fill([
            'name' => $data['name'],
            'type' => $data['type'],
        ]);
        $category->forceFill(['is_active' => $data['is_active']])->save();

        $logger->log($request->user(), 'category.update', "Updated system category {$category->name}", $category);

        return back()->with('success', 'System category updated.');
    }

    public function destroy(Request $request, Category $category, AdminActivityLogger $logger, AdminNotificationService $notifications): RedirectResponse
    {
        abort_unless($category->user_id === null, 403);

        if ($category->transactions()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category that has transactions. Deactivate it instead.']);
        }

        $name = $category->name;
        $category->delete();

        $logger->log($request->user(), 'category.delete', "Deleted system category {$name}");

        $notifications->notifyAdmins(
            eventType: 'category.deleted',
            title: 'System category deleted',
            message: "{$name} was removed from system categories.",
            severity: 'warning',
            actionUrl: '/admin/categories',
            actionLabel: 'View categories',
        );

        return back()->with('success', 'System category deleted.');
    }
}

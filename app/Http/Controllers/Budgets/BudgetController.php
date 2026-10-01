<?php

namespace App\Http\Controllers\Budgets;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\StoreBudgetRequest;
use App\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Models\Budget;
use App\Services\BudgetService;
use App\Support\CategoryAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request, BudgetService $budgets): Response
    {
        $this->authorize('viewAny', Budget::class);

        $filters = $request->validate([
            'month' => ['nullable', 'date'],
        ]);

        $query = $request->user()
            ->budgets()
            ->with('category:id,name,type,is_default')
            ->orderByDesc('month')
            ->orderBy('id');

        if (! empty($filters['month'])) {
            $month = $budgets->normalizeMonth($filters['month']);
            $query->whereDate('month', $month);
        }

        $items = $query->get();
        $enriched = $budgets->enrichBudgets($request->user(), $items);
        $budgets->evaluateAlerts($request->user(), $enriched);

        return Inertia::render('budgets/index', [
            'budgets' => $enriched,
            'filters' => [
                'month' => isset($filters['month'])
                    ? $budgets->normalizeMonth($filters['month'])
                    : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Budget::class);

        return Inertia::render('budgets/create', [
            'categories' => CategoryAccess::optionsForUser($request->user(), TransactionType::Expense),
            'defaultMonth' => now()->startOfMonth()->format('Y-m'),
        ]);
    }

    public function store(StoreBudgetRequest $request, BudgetService $budgets): RedirectResponse
    {
        $data = $request->validated();

        try {
            $budget = $request->user()->budgets()->create($data);
        } catch (QueryException $exception) {
            $this->throwDuplicateBudgetValidation($exception);
        }

        $budget->load('category:id,name,type,is_default');
        $budgets->syncAlertsForBudget($budget);

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget created.');
    }

    public function edit(Request $request, Budget $budget): Response
    {
        $this->authorize('update', $budget);

        return Inertia::render('budgets/edit', [
            'budget' => [
                'id' => $budget->id,
                'category_id' => $budget->category_id,
                'month' => $budget->month->format('Y-m'),
                'limit_amount' => (string) $budget->limit_amount,
            ],
            'categories' => CategoryAccess::optionsForUser($request->user(), TransactionType::Expense),
        ]);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget, BudgetService $budgets): RedirectResponse
    {
        try {
            $budget->update($request->validated());
        } catch (QueryException $exception) {
            $this->throwDuplicateBudgetValidation($exception);
        }

        $budget->load('category:id,name,type,is_default', 'user');
        $budgets->syncAlertsForBudget($budget);

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget updated.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget deleted. Transactions and past notifications were kept.');
    }

    /**
     * Convert a unique-constraint race into the same validation message as FormRequest checks.
     */
    private function throwDuplicateBudgetValidation(QueryException $exception): never
    {
        if (! $this->isBudgetUniqueViolation($exception)) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            'category_id' => 'You already have a budget for this category in that month.',
        ]);
    }

    private function isBudgetUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        // MySQL 1062 / Postgres 23505 / SQLite UNIQUE — match the budgets unique index name when present.
        $isUnique = $sqlState === '23000' || $sqlState === '23505' || $driverCode === 1062 || str_contains($message, 'UNIQUE constraint failed');

        return $isUnique && (
            str_contains($message, 'budgets_user_category_month_unique')
            || str_contains($message, 'budgets.user_id')
            || str_contains($message, 'user_id, category_id, month')
        );
    }
}

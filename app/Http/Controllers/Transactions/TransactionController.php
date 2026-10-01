<?php

namespace App\Http\Controllers\Transactions;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Ai\AiCategorizationService;
use App\Services\Ai\IncomePlanAlertService;
use App\Services\Intelligence\DuplicateDetectionService;
use App\Services\Intelligence\ExpenseAlertBridge;
use App\Services\Intelligence\RecentActivityService;
use App\Services\Intelligence\TransactionSignalService;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Paginated transaction history for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'archived'])],
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
            'category_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $status = $filters['status'] ?? 'active';

        if (! empty($filters['category_id'])) {
            $allowed = Category::query()
                ->accessibleTo($user)
                ->whereKey($filters['category_id'])
                ->exists();

            if (! $allowed) {
                abort(403);
            }
        }

        $query = $user
            ->transactions()
            ->with('category:id,name,type,is_default')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($status === 'archived') {
            $query->onlyTrashed();
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('transaction_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('transaction_date', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $query->where('description', 'like', '%'.$filters['search'].'%');
        }

        $transactions = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'is_archived' => $transaction->trashed(),
                'category' => $transaction->category
                    ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'is_default' => $transaction->category->is_default,
                    ]
                    : null,
                'created_at' => $transaction->created_at?->toIso8601String(),
                'updated_at' => $transaction->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'filters' => [
                'status' => $status,
                'type' => $filters['type'] ?? null,
                'category_id' => isset($filters['category_id']) ? (int) $filters['category_id'] : null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
            'typeOptions' => TransactionType::options(),
            'categoryOptions' => CategoryAccess::optionsForUser($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
        ]);

        return Inertia::render('transactions/create', [
            'typeOptions' => TransactionType::options(),
            'categories' => CategoryAccess::optionsForUser($request->user()),
            'initialType' => $validated['type'] ?? null,
            'aiAvailable' => app(AiCategorizationService::class)->isAvailable(),
            'today' => AvailableBalance::today(),
            'available_balance' => AvailableBalance::forUser($request->user()),
        ]);
    }

    public function store(
        StoreTransactionRequest $request,
        AiCategorizationService $ai,
        TransactionSignalService $signals,
        IncomePlanAlertService $incomePlans,
        ExpenseAlertBridge $expenseAlerts,
    ): RedirectResponse {
        $transaction = $request->user()->transactions()->create($request->validated());

        $this->recordAiFeedback($request, $ai);
        $spendingAlert = $signals->evaluateTransaction($request->user(), $transaction);

        // Proactive alerts run after the financial save. Failures never undo the transaction.
        if ($transaction->type === TransactionType::Income) {
            $incomePlans->handle($request->user(), $transaction);
        } else {
            $expenseAlerts->handle($request->user(), $transaction, $spendingAlert);
        }

        $redirect = redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction added.');

        if ($spendingAlert !== null && ($spendingAlert['signal_type'] ?? null) === 'large_transaction') {
            $redirect->with('spending_alert', $spendingAlert);
        }

        return $redirect;
    }

    public function show(Request $request, Transaction $transaction, RecentActivityService $activity): Response
    {
        $this->authorize('view', $transaction);

        if (! $transaction->trashed()) {
            $activity->recordView($request->user(), $transaction);
        }

        $transaction->load('category:id,name,type,is_default');

        return Inertia::render('transactions/show', [
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'category_id' => $transaction->category_id,
                'is_archived' => $transaction->trashed(),
                'category' => $transaction->category
                    ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'is_default' => $transaction->category->is_default,
                    ]
                    : null,
                'created_at' => $transaction->created_at?->toIso8601String(),
                'updated_at' => $transaction->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function edit(Request $request, Transaction $transaction, RecentActivityService $activity): Response
    {
        $this->authorize('update', $transaction);

        // Edit screen still records recent activity (backend edit entry point retained).
        $activity->recordView($request->user(), $transaction);

        $transaction->load('category:id,name,type,is_default');

        return Inertia::render('transactions/edit', [
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'category_id' => $transaction->category_id,
                'category' => $transaction->category
                    ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'is_default' => $transaction->category->is_default,
                    ]
                    : null,
            ],
            'typeOptions' => TransactionType::options(),
            'categories' => CategoryAccess::optionsForUser($request->user()),
            'aiAvailable' => app(AiCategorizationService::class)->isAvailable(),
            'today' => AvailableBalance::today(),
            'available_balance' => AvailableBalance::forUser($request->user(), $transaction),
        ]);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        AiCategorizationService $ai,
        RecentActivityService $activity,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $transaction->update($request->validated());

        $this->recordAiFeedback($request, $ai);
        $activity->recordEdit($request->user(), $transaction);
        $signals->evaluateTransaction($request->user(), $transaction->fresh(['category:id,name']));

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction updated.');
    }

    public function destroy(
        Request $request,
        Transaction $transaction,
        DuplicateDetectionService $duplicates,
    ): RedirectResponse {
        $this->authorize('delete', $transaction);

        if (! $transaction->trashed()) {
            // Archiving income must not leave posted expenses uncovered (AvailableBalance ≥ 0).
            if ($transaction->type === TransactionType::Income) {
                $remaining = AvailableBalance::afterRemoving($request->user(), $transaction);
                if (MoneyMath::compare($remaining, '0.00') < 0) {
                    return redirect()
                        ->back()
                        ->withErrors([
                            'transaction' => sprintf(
                                'Cannot archive this income: available funds would become Rs %s. Reduce or archive expenses first.',
                                number_format((float) $remaining, 2),
                            ),
                        ]);
                }
            }

            $transaction->delete();
            $duplicates->resolveOpenForArchivedTransaction($request->user(), $transaction);
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction archived successfully.')
            ->with('archived_transaction_id', $transaction->id);
    }

    public function restore(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('restore', $transaction);

        if ($transaction->trashed()) {
            if ($transaction->type === TransactionType::Expense
                && ! AvailableBalance::canAffordExpense($request->user(), (string) $transaction->amount)) {
                $available = AvailableBalance::forUser($request->user());

                return redirect()
                    ->back()
                    ->withErrors([
                        'transaction' => sprintf(
                            'Cannot restore this expense: available balance is Rs %s, but the expense is Rs %s.',
                            number_format((float) $available, 2),
                            number_format((float) $transaction->amount, 2),
                        ),
                    ]);
            }

            $transaction->restore();
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction restored successfully.');
    }

    private function recordAiFeedback(StoreTransactionRequest|UpdateTransactionRequest $request, AiCategorizationService $ai): void
    {
        $suggested = $request->input('ai_suggested_category_id');
        if ($suggested === null || $suggested === '') {
            return;
        }

        $selected = (int) $request->input('category_id');
        $suggestedId = (int) $suggested;
        $accepted = $request->boolean('ai_suggestion_accepted') || $suggestedId === $selected;

        $ai->recordFeedback(
            $request->user(),
            TransactionType::from($request->string('type')->toString()),
            $request->input('description'),
            $suggestedId,
            $selected,
            $accepted && $suggestedId === $selected,
        );
    }
}

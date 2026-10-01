<?php

namespace App\Http\Controllers\RecurringTransactions;

use App\Enums\RecurringFrequency;
use App\Enums\RecurringOccurrenceStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecurringTransactions\StoreRecurringTransactionRequest;
use App\Http\Requests\RecurringTransactions\UpdateRecurringTransactionRequest;
use App\Models\RecurringTransaction;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RecurringTransaction::class);

        $items = $request->user()
            ->recurringTransactions()
            ->with([
                'category:id,name,type,is_default',
                'occurrences' => fn ($query) => $query
                    ->whereIn('status', [
                        RecurringOccurrenceStatus::PendingApproval->value,
                        RecurringOccurrenceStatus::Rejected->value,
                    ])
                    ->orderByDesc('occurrence_date')
                    ->limit(5),
            ])
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RecurringTransaction $item) => $this->serialize($item));

        return Inertia::render('recurring-transactions/index', [
            'recurringTransactions' => $items,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RecurringTransaction::class);

        return Inertia::render('recurring-transactions/create', [
            'typeOptions' => TransactionType::options(),
            'frequencyOptions' => RecurringFrequency::options(),
            'categories' => CategoryAccess::optionsForUser($request->user()),
            'today' => AvailableBalance::today(),
        ]);
    }

    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['next_run_date'] = $data['start_date'];
        $data['is_active'] = $data['is_active'] ?? true;

        $request->user()->recurringTransactions()->create($data);

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Recurring transaction created.');
    }

    public function edit(Request $request, RecurringTransaction $recurringTransaction): Response
    {
        $this->authorize('update', $recurringTransaction);

        $recurringTransaction->load('category:id,name,type,is_default');

        return Inertia::render('recurring-transactions/edit', [
            'recurringTransaction' => $this->serialize($recurringTransaction),
            'typeOptions' => TransactionType::options(),
            'frequencyOptions' => RecurringFrequency::options(),
            'categories' => CategoryAccess::optionsForUser($request->user()),
            'today' => AvailableBalance::today(),
        ]);
    }

    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $data = $request->validated();

        // If start_date changes and no generation has occurred yet, reset next_run_date.
        if (
            $recurringTransaction->last_generated_at === null
            && isset($data['start_date'])
            && $data['start_date'] !== $recurringTransaction->start_date->toDateString()
        ) {
            $data['next_run_date'] = $data['start_date'];
        }

        $recurringTransaction->update($data);

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Recurring transaction updated.');
    }

    public function toggle(Request $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('update', $recurringTransaction);

        $recurringTransaction->forceFill([
            'is_active' => ! $recurringTransaction->is_active,
        ])->save();

        $message = $recurringTransaction->is_active
            ? 'Recurring transaction resumed.'
            : 'Recurring transaction paused.';

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', $message);
    }

    public function destroy(Request $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('delete', $recurringTransaction);

        $recurringTransaction->delete();

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Recurring transaction deleted. Past transactions were kept.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RecurringTransaction $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'category_id' => $item->category_id,
            'category' => $item->category
                ? [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    'is_default' => $item->category->is_default,
                ]
                : null,
            'amount' => $item->amount,
            'description' => $item->description,
            'frequency' => $item->frequency->value,
            'frequency_label' => $item->frequency->label(),
            'start_date' => $item->start_date->toDateString(),
            'end_date' => $item->end_date?->toDateString(),
            'next_run_date' => $item->next_run_date->toDateString(),
            'last_generated_at' => $item->last_generated_at?->toIso8601String(),
            'is_active' => $item->is_active,
            'pending_occurrences' => $item->relationLoaded('occurrences')
                ? $item->occurrences->map(fn ($occurrence) => [
                    'id' => $occurrence->id,
                    'occurrence_date' => $occurrence->occurrence_date->toDateString(),
                    'status' => $occurrence->status->value,
                    'status_label' => $occurrence->status->label(),
                ])->values()->all()
                : [],
        ];
    }
}

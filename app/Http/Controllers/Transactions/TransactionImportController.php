<?php

namespace App\Http\Controllers\Transactions;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Ai\AiCategorizationService;
use App\Services\CsvTransactionImporter;
use App\Services\Intelligence\TransactionSignalService;
use App\Support\CategoryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TransactionImportController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('transactions/import', [
            'limits' => [
                'max_size_kb' => (int) config('campuscoin.csv_import.max_size_kb', 512),
                'max_rows' => (int) config('campuscoin.csv_import.max_rows', 500),
            ],
            'categoryOptions' => CategoryAccess::optionsForUser($request->user()),
        ]);
    }

    public function preview(Request $request, CsvTransactionImporter $importer, AiCategorizationService $ai): Response|RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $request->validate([
            'csv' => [
                'required',
                'file',
                'extensions:csv',
                'max:'.(int) config('campuscoin.csv_import.max_size_kb', 512),
            ],
        ], [
            'csv.required' => 'Choose a CSV file to import.',
            'csv.extensions' => 'Only .csv files are allowed.',
            'csv.max' => 'CSV file exceeds the maximum allowed size.',
        ]);

        try {
            $result = $importer->preview($request->file('csv'), $request->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'csv' => $e->getMessage(),
            ]);
        }

        $rows = $ai->enrichCsvRows($request->user(), $result['rows']);

        $token = Str::uuid()->toString();

        $request->session()->put('csv_import', [
            'token' => $token,
            'path' => $result['path'],
            'rows' => $rows,
            'summary' => $result['summary'],
            'user_id' => $request->user()->id,
        ]);

        return Inertia::render('transactions/import-preview', [
            'token' => $token,
            'rows' => $rows,
            'summary' => $result['summary'],
            'categoryOptions' => CategoryAccess::optionsForUser($request->user()),
            'aiAvailable' => $ai->isAvailable(),
            'limits' => [
                'max_size_kb' => (int) config('campuscoin.csv_import.max_size_kb', 512),
                'max_rows' => (int) config('campuscoin.csv_import.max_rows', 500),
            ],
        ]);
    }

    public function confirm(
        Request $request,
        CsvTransactionImporter $importer,
        TransactionSignalService $signals,
    ): RedirectResponse {
        $this->authorize('create', Transaction::class);

        $validated = $request->validate([
            'token' => ['required', 'string', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'category_overrides' => ['nullable', 'array'],
            'category_overrides.*' => ['nullable', 'integer'],
        ]);

        $idempotencyKey = 'csv_import_done:'.$request->user()->id.':'.$validated['idempotency_key'];

        if ($request->session()->has($idempotencyKey)) {
            $prior = $request->session()->get($idempotencyKey);

            return redirect()
                ->route('transactions.import.create')
                ->with('success', $this->summaryMessage($prior))
                ->with('import_result', $prior);
        }

        $sessionImport = $request->session()->get('csv_import');

        if (
            ! is_array($sessionImport)
            || ($sessionImport['token'] ?? null) !== $validated['token']
            || ($sessionImport['user_id'] ?? null) !== $request->user()->id
        ) {
            throw ValidationException::withMessages([
                'token' => 'Import preview expired. Upload the CSV again.',
            ]);
        }

        $rows = $sessionImport['rows'] ?? [];
        $overrides = $validated['category_overrides'] ?? [];

        $rows = $this->applyCategoryOverrides($request->user(), $rows, is_array($overrides) ? $overrides : []);

        try {
            $summary = DB::transaction(function () use ($importer, $request, $rows) {
                return $importer->importValidRows($request->user(), $rows);
            });
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'csv' => 'Import failed due to a database error. No rows were partially saved.',
            ]);
        }

        // Intelligence runs after a successful financial import; never blocks/alters rows.
        $duplicateCount = $signals->evaluateMany($request->user(), $summary['transaction_ids'] ?? []);
        unset($summary['transaction_ids']);
        $summary['possible_duplicates'] = $duplicateCount;

        if (! empty($sessionImport['path'])) {
            $importer->deleteTemporaryFile($sessionImport['path']);
        }

        $request->session()->forget('csv_import');
        $request->session()->put($idempotencyKey, $summary);

        return redirect()
            ->route('transactions.import.create')
            ->with('success', $this->summaryMessage($summary))
            ->with('import_result', $summary);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string|int, mixed>  $overrides
     * @return list<array<string, mixed>>
     */
    private function applyCategoryOverrides($user, array $rows, array $overrides): array
    {
        foreach ($rows as $index => $row) {
            $rowNumber = (string) ($row['row_number'] ?? $index);

            if (! array_key_exists($rowNumber, $overrides) && ! array_key_exists((int) $rowNumber, $overrides)) {
                continue;
            }

            $override = $overrides[$rowNumber] ?? $overrides[(int) $rowNumber] ?? null;

            if ($override === null || $override === '') {
                $rows[$index]['category_id'] = null;

                continue;
            }

            $categoryId = (int) $override;
            if ($this->categoryAllowedForRow($user, $categoryId, $row)) {
                $rows[$index]['category_id'] = $categoryId;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function categoryAllowedForRow($user, int $categoryId, array $row): bool
    {
        $type = TransactionType::tryFrom((string) ($row['type'] ?? ''));
        if ($type === null) {
            return false;
        }

        return Category::query()
            ->accessibleTo($user)
            ->whereKey($categoryId)
            ->where('type', $type->value)
            ->exists();
    }

    /**
     * @param  array{imported?: int, skipped?: int, errors?: int, warnings?: int, possible_duplicates?: int}  $summary
     */
    private function summaryMessage(array $summary): string
    {
        $imported = $summary['imported'] ?? 0;
        $skipped = $summary['skipped'] ?? 0;
        $errors = $summary['errors'] ?? 0;
        $warnings = $summary['warnings'] ?? 0;
        $duplicates = $summary['possible_duplicates'] ?? 0;

        $message = "Imported: {$imported}. Skipped: {$skipped}. Errors: {$errors}. Warnings: {$warnings}.";

        if ($duplicates > 0) {
            $message .= " Possible duplicates found: {$duplicates}.";
        }

        return $message;
    }
}

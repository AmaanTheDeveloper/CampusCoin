<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use App\Support\AvailableBalance;
use App\Support\CategoryAccess;
use App\Support\MoneyMath;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CsvTransactionImporter
{
    public const REQUIRED_HEADERS = ['date', 'type', 'amount', 'description', 'category'];

    /**
     * @return array{
     *     path: string,
     *     rows: list<array<string, mixed>>,
     *     summary: array{total: int, valid: int, invalid: int, warnings: int}
     * }
     */
    public function preview(UploadedFile $file, User $user): array
    {
        $this->assertSafeUpload($file);

        $disk = config('campuscoin.csv_import.disk', 'local');
        $directory = config('campuscoin.csv_import.directory', 'csv-imports');
        $filename = Str::uuid()->toString().'.csv';
        $path = $file->storeAs($directory, $filename, $disk);

        if ($path === false) {
            throw new RuntimeException('Unable to store uploaded CSV.');
        }

        try {
            $parsed = $this->parseStoredFile($path, $user, $disk);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return [
            'path' => $path,
            'rows' => $parsed['rows'],
            'summary' => $parsed['summary'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, errors: int, warnings: int, transaction_ids: list<int>}
     */
    public function importValidRows(User $user, array $rows): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $warnings = 0;
        $transactionIds = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'error') {
                $errors++;
                $skipped++;

                continue;
            }

            if (($row['status'] ?? '') === 'warning') {
                $warnings++;
            }

            // Ownership is always the authenticated user — never from CSV.
            if (($row['type'] ?? null) === TransactionType::Expense->value
                && isset($row['amount'])
                && ! AvailableBalance::canAffordExpense($user, (string) $row['amount'])
            ) {
                $errors++;
                $skipped++;

                continue;
            }

            $transaction = $user->transactions()->create([
                'category_id' => $row['category_id'] ?? null,
                'type' => $row['type'],
                'amount' => $row['amount'],
                'description' => $row['description'],
                'transaction_date' => $row['date'],
            ]);

            $transactionIds[] = $transaction->id;
            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'warnings' => $warnings,
            'transaction_ids' => $transactionIds,
        ];
    }

    public function deleteTemporaryFile(string $path): void
    {
        $disk = config('campuscoin.csv_import.disk', 'local');
        Storage::disk($disk)->delete($path);
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array{total: int, valid: int, invalid: int, warnings: int}
     * }
     */
    public function parseStoredFile(string $path, User $user, ?string $disk = null): array
    {
        $disk ??= config('campuscoin.csv_import.disk', 'local');
        $absolute = Storage::disk($disk)->path($path);

        if (! is_readable($absolute)) {
            throw new RuntimeException('CSV file is not readable.');
        }

        $handle = fopen($absolute, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        try {
            $headerRow = fgetcsv($handle);
            if ($headerRow === false || $headerRow === [null] || $headerRow === []) {
                throw new RuntimeException('CSV file is empty or missing a header row.');
            }

            $headers = $this->normalizeHeaders($headerRow);
            $this->assertRequiredHeaders($headers);

            if (in_array('user_id', $headers, true)) {
                // Presence is ignored for ownership; flagged as informational only.
            }

            $categories = CategoryAccess::forUser($user);
            $maxRows = (int) config('campuscoin.csv_import.max_rows', 500);
            $rows = [];
            $rowNumber = 1;

            while (($data = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $rowNumber++;

                if (count($rows) >= $maxRows) {
                    throw new RuntimeException("CSV exceeds the maximum of {$maxRows} data rows.");
                }

                $assoc = [];
                foreach ($headers as $index => $header) {
                    $assoc[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
                }

                $parsed = $this->validateRow($assoc, $rowNumber, $categories);
                $rows[] = $parsed;
            }
        } finally {
            fclose($handle);
        }

        $rows = $this->applyRunningBalanceRules($user, $rows);

        $valid = 0;
        $invalid = 0;
        $warnings = 0;
        foreach ($rows as $parsed) {
            if ($parsed['status'] === 'error') {
                $invalid++;
            } elseif ($parsed['status'] === 'warning') {
                $warnings++;
                $valid++;
            } else {
                $valid++;
            }
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => $invalid,
                'warnings' => $warnings,
            ],
        ];
    }

    private function assertSafeUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('campuscoin.csv_import.max_size_kb', 512);

        if ($file->getSize() !== false && $file->getSize() > $maxKb * 1024) {
            throw new RuntimeException("CSV file exceeds the maximum size of {$maxKb} KB.");
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            throw new RuntimeException('Only .csv files are allowed.');
        }

        $mime = $file->getMimeType() ?? '';
        $allowedMimes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'text/x-csv',
            'application/octet-stream',
        ];

        if (! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Invalid CSV MIME type.');
        }
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return list<string>
     */
    private function normalizeHeaders(array $headerRow): array
    {
        $aliases = [
            'transaction_date' => 'date',
            'txn_date' => 'date',
            'transaction_type' => 'type',
            'category_name' => 'category',
            'note' => 'description',
            'notes' => 'description',
            'memo' => 'description',
        ];

        return array_map(function ($header) use ($aliases) {
            $normalized = Str::of((string) $header)
                ->lower()
                ->replace("\u{FEFF}", '')
                ->trim()
                ->replace(' ', '_')
                ->toString();

            return $aliases[$normalized] ?? $normalized;
        }, $headerRow);
    }

    /**
     * @param  list<string>  $headers
     */
    private function assertRequiredHeaders(array $headers): void
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                throw new RuntimeException("Missing required CSV header: {$required}");
            }
        }
    }

    /**
     * @param  list<string|null>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $assoc
     * @param  Collection<int, Category>  $categories
     * @return array<string, mixed>
     */
    private function validateRow(array $assoc, int $rowNumber, $categories): array
    {
        $messages = [];
        $status = 'valid';
        $categoryId = null;
        $typeValue = null;
        $amount = null;
        $date = null;
        $description = $this->sanitizeText($assoc['description'] ?? '');

        // Never accept ownership from CSV.
        unset($assoc['user_id']);

        $rawType = strtolower(trim($assoc['type'] ?? ''));
        if (! in_array($rawType, TransactionType::values(), true)) {
            $messages[] = "Row {$rowNumber}: type is invalid.";
            $status = 'error';
        } else {
            $typeValue = $rawType;
        }

        $rawDate = trim($assoc['date'] ?? '');
        try {
            if ($rawDate === '') {
                throw new RuntimeException('empty');
            }
            $date = Carbon::parse($rawDate)->toDateString();
        } catch (\Throwable) {
            $messages[] = "Row {$rowNumber}: date is invalid.";
            $status = 'error';
            $date = null;
        }

        if ($date !== null && $date > AvailableBalance::today()) {
            $messages[] = "Row {$rowNumber}: transaction date cannot be in the future.";
            $status = 'error';
        }

        $rawAmount = trim($assoc['amount'] ?? '');
        if ($rawAmount === '' || ! is_numeric($rawAmount) || (float) $rawAmount <= 0) {
            $messages[] = "Row {$rowNumber}: amount is invalid.";
            $status = 'error';
        } else {
            $amount = number_format((float) $rawAmount, 2, '.', '');
            if (strlen($amount) > 15) {
                $messages[] = "Row {$rowNumber}: amount is invalid.";
                $status = 'error';
                $amount = null;
            }
        }

        if (strlen($description) > 255) {
            $messages[] = "Row {$rowNumber}: description is too long.";
            $status = 'error';
        }

        $categoryName = $this->sanitizeText(trim($assoc['category'] ?? ''));
        if ($categoryName !== '' && $typeValue !== null && $status !== 'error') {
            $match = $categories->first(
                fn (Category $category) => strcasecmp($category->name, $categoryName) === 0
                    && $category->type->value === $typeValue
            );

            if ($match === null) {
                $foreign = $categories->first(
                    fn (Category $category) => strcasecmp($category->name, $categoryName) === 0
                );

                if ($foreign !== null) {
                    $messages[] = "Row {$rowNumber}: category '{$categoryName}' does not match the transaction type.";
                } else {
                    $messages[] = "Row {$rowNumber}: category '{$categoryName}' was not found.";
                }
                $status = 'warning';
                $categoryId = null;
            } else {
                $categoryId = $match->id;
            }
        }

        if ($status === 'error') {
            // keep error
        } elseif ($status === 'warning') {
            // keep warning
        }

        return [
            'row_number' => $rowNumber,
            'date' => $date,
            'type' => $typeValue,
            'amount' => $amount,
            'description' => $description !== '' ? $description : null,
            'category_name' => $categoryName !== '' ? $categoryName : null,
            'category_id' => $categoryId,
            'status' => $status,
            'messages' => $messages,
        ];
    }

    /**
     * Simulate wallet impact in CSV row order. Insufficient expenses become errors
     * and are not imported. Does not mutate existing DB rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyRunningBalanceRules(User $user, array $rows): array
    {
        $running = AvailableBalance::forUser($user);

        foreach ($rows as $index => $row) {
            if (($row['status'] ?? '') === 'error') {
                continue;
            }

            $type = $row['type'] ?? null;
            $amount = isset($row['amount']) ? MoneyMath::normalize($row['amount']) : null;

            if ($amount === null || $type === null) {
                continue;
            }

            if ($type === TransactionType::Income->value) {
                $running = MoneyMath::add($running, $amount);

                continue;
            }

            if ($type === TransactionType::Expense->value) {
                if (MoneyMath::compare($amount, $running) > 0) {
                    $rows[$index]['status'] = 'error';
                    $rows[$index]['messages'][] = sprintf(
                        'Row %s: insufficient balance. Available Rs %s, expense Rs %s.',
                        (string) ($row['row_number'] ?? $index),
                        number_format((float) $running, 2),
                        number_format((float) $amount, 2),
                    );

                    continue;
                }

                $running = MoneyMath::sub($running, $amount);
            }
        }

        return $rows;
    }

    /**
     * Neutralize spreadsheet formula-injection prefixes without altering normal text.
     * Values are stored as plain text and never executed.
     */
    private function sanitizeText(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}

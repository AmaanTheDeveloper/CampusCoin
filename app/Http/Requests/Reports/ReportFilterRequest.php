<?php

namespace App\Http\Requests\Reports;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Support\ReportFilters;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', 'string'],
            'income_source' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $fromInput = $this->input('from');
            $toInput = $this->input('to');

            $now = now();
            $defaultFrom = $now->copy()->startOfMonth()->toDateString();
            $defaultTo = $now->copy()->endOfMonth()->toDateString();

            $from = $fromInput ? Carbon::parse((string) $fromInput)->toDateString() : $defaultFrom;
            $to = $toInput ? Carbon::parse((string) $toInput)->toDateString() : $defaultTo;

            if ($from > $to) {
                $validator->errors()->add('from', 'The start date must be on or before the end date.');

                return;
            }

            $fromCarbon = Carbon::parse($from)->startOfDay();
            $toCarbon = Carbon::parse($to)->startOfDay();
            $maxEnd = $fromCarbon->copy()->addMonthsNoOverflow(ReportFilters::MAX_RANGE_MONTHS);

            if ($toCarbon->gt($maxEnd)) {
                $validator->errors()->add('to', 'Date range cannot exceed '.ReportFilters::MAX_RANGE_MONTHS.' months.');

                return;
            }

            $categoryRaw = $this->input('category');
            if ($categoryRaw !== null && $categoryRaw !== '' && $categoryRaw !== 'uncategorized') {
                if (! ctype_digit((string) $categoryRaw)) {
                    $validator->errors()->add('category', 'Select a valid expense category.');

                    return;
                }

                $category = Category::query()
                    ->accessibleTo($this->user())
                    ->whereKey((int) $categoryRaw)
                    ->first();

                if ($category === null || $category->type !== TransactionType::Expense) {
                    $validator->errors()->add('category', 'Select a valid expense category.');

                    return;
                }
            }

            $incomeSourceId = $this->input('income_source');
            if ($incomeSourceId !== null && $incomeSourceId !== '') {
                $source = Category::query()
                    ->accessibleTo($this->user())
                    ->whereKey((int) $incomeSourceId)
                    ->first();

                if ($source === null || $source->type !== TransactionType::Income) {
                    $validator->errors()->add('income_source', 'Select a valid income source.');
                }
            }
        });
    }

    public function filters(): ReportFilters
    {
        $now = now();
        $from = $this->filled('from')
            ? Carbon::parse((string) $this->input('from'))->toDateString()
            : $now->copy()->startOfMonth()->toDateString();
        $to = $this->filled('to')
            ? Carbon::parse((string) $this->input('to'))->toDateString()
            : $now->copy()->endOfMonth()->toDateString();

        $categoryRaw = $this->input('category');
        $uncategorizedOnly = $categoryRaw === 'uncategorized';
        $categoryId = (! $uncategorizedOnly && $categoryRaw !== null && $categoryRaw !== '' && ctype_digit((string) $categoryRaw))
            ? (int) $categoryRaw
            : null;

        $incomeSourceRaw = $this->input('income_source');
        $incomeSourceId = ($incomeSourceRaw !== null && $incomeSourceRaw !== '')
            ? (int) $incomeSourceRaw
            : null;

        return new ReportFilters(
            from: $from,
            to: $to,
            categoryId: $categoryId,
            uncategorizedOnly: $uncategorizedOnly,
            incomeSourceId: $incomeSourceId,
        );
    }
}

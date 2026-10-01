<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Validated, normalized report filter values.
 *
 * Semantics:
 * - Date range applies to summary, category, daily, weekly, and detail table.
 * - Six-month trend is independent (last 6 calendar months).
 * - Category filter applies to expense calculations and expense rows in the table.
 * - Income-source filter applies to income calculations and income rows in the table.
 * - Week start is Monday (ISO-8601).
 * - Maximum date range: 24 months inclusive.
 */
final class ReportFilters
{
    public const MAX_RANGE_MONTHS = 24;

    public const WEEK_STARTS_ON = Carbon::MONDAY;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $categoryId,
        public readonly bool $uncategorizedOnly,
        public readonly ?int $incomeSourceId,
    ) {}

    public function periodLabel(): string
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        if ($from->isSameMonth($to) && $from->isSameYear($to)) {
            if ($from->isSameDay($from->copy()->startOfMonth()) && $to->isSameDay($to->copy()->endOfMonth())) {
                return $from->format('F Y');
            }
        }

        return $from->format('d M Y').' – '.$to->format('d M Y');
    }

    /**
     * @return array{from: string, to: string, category: string|null, income_source: string|null}
     */
    public function toQueryArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'category' => $this->uncategorizedOnly ? 'uncategorized' : ($this->categoryId !== null ? (string) $this->categoryId : null),
            'income_source' => $this->incomeSourceId !== null ? (string) $this->incomeSourceId : null,
        ];
    }

    /**
     * Human-readable active filter labels for export branding.
     *
     * @return list<string>
     */
    public function activeLabels(?string $categoryName = null, ?string $incomeSourceName = null): array
    {
        $labels = [
            'Period: '.$this->periodLabel(),
        ];

        if ($this->uncategorizedOnly) {
            $labels[] = 'Category: Uncategorized';
        } elseif ($categoryName !== null) {
            $labels[] = 'Category: '.$categoryName;
        } else {
            $labels[] = 'Category: All expense categories';
        }

        if ($incomeSourceName !== null) {
            $labels[] = 'Income source: '.$incomeSourceName;
        } else {
            $labels[] = 'Income source: All income sources';
        }

        return $labels;
    }
}

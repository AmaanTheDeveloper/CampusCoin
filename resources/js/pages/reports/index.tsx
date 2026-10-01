import { ReportVisual } from '@/components/dashboard/visuals/ReportVisual';
import { FintechEmpty, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { PaginationNav } from '@/components/pagination-nav';
import { ReportCategoryChart, ReportDailyChart, ReportSixMonthChart, ReportWeeklyChart } from '@/components/reports/report-charts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { exportReportAsImage, exportReportAsPdf } from '@/lib/report-export';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ReportData } from '@/types/report';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileImage, Filter, RotateCcw } from 'lucide-react';
import { type CSSProperties, type FormEvent, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

type Props = {
    report: ReportData | null;
    error: string | null;
};

function FilterFields({
    idPrefix,
    from,
    to,
    category,
    incomeSource,
    expenseCategories,
    incomeSources,
    onFrom,
    onTo,
    onCategory,
    onIncomeSource,
}: {
    idPrefix: string;
    from: string;
    to: string;
    category: string;
    incomeSource: string;
    expenseCategories: ReportData['expense_categories'];
    incomeSources: ReportData['income_sources'];
    onFrom: (value: string) => void;
    onTo: (value: string) => void;
    onCategory: (value: string) => void;
    onIncomeSource: (value: string) => void;
}) {
    const fromId = `${idPrefix}-from`;
    const toId = `${idPrefix}-to`;
    const categoryId = `${idPrefix}-category`;
    const incomeSourceId = `${idPrefix}-income-source`;

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1.5">
                <Label htmlFor={fromId} className="cc-text-secondary text-xs">
                    Date From
                </Label>
                <Input id={fromId} type="date" className="cc-field" value={from} onChange={(e) => onFrom(e.target.value)} />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor={toId} className="cc-text-secondary text-xs">
                    Date To
                </Label>
                <Input id={toId} type="date" className="cc-field" value={to} onChange={(e) => onTo(e.target.value)} />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor={categoryId} className="cc-text-secondary text-xs">
                    Category
                </Label>
                <select id={categoryId} className="cc-field w-full" value={category} onChange={(e) => onCategory(e.target.value)}>
                    <option value="">All expense categories</option>
                    {expenseCategories.map((item) => (
                        <option key={String(item.id)} value={String(item.id)}>
                            {item.name}
                            {item.is_default ? '' : ' (Personal)'}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-1.5">
                <Label htmlFor={incomeSourceId} className="cc-text-secondary text-xs">
                    Income Source
                </Label>
                <select id={incomeSourceId} className="cc-field w-full" value={incomeSource} onChange={(e) => onIncomeSource(e.target.value)}>
                    <option value="">All income sources</option>
                    {incomeSources.map((item) => (
                        <option key={String(item.id)} value={String(item.id)}>
                            {item.name}
                            {item.is_default ? '' : ' (Personal)'}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}

function ReportExportDocument({ report }: { report: ReportData }) {
    // Inline hex styles only — html2canvas strips stylesheets (Tailwind oklch is unsupported).
    const sectionTitle: CSSProperties = { margin: '0 0 12px', fontSize: 18, fontWeight: 600, color: '#0f172a' };
    const bodyText: CSSProperties = { margin: 0, color: '#475569', fontSize: 14 };

    return (
        <div
            data-report-export-root="true"
            style={{
                width: 900,
                minWidth: 900,
                boxSizing: 'border-box',
                backgroundColor: '#ffffff',
                color: '#0f172a',
                padding: 32,
                fontFamily: 'Segoe UI, Helvetica, Arial, sans-serif',
                display: 'grid',
                gap: 32,
            }}
        >
            <header style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: 24 }}>
                <p style={{ margin: 0, fontSize: 30, fontWeight: 600, letterSpacing: '-0.02em', color: '#115e59' }}>Campus Coin</p>
                <p style={{ margin: '4px 0 0', fontSize: 14, color: '#64748b' }}>Smart Spending Student Style</p>
                <h1 style={{ margin: '16px 0 0', fontSize: 24, fontWeight: 600 }}>Monthly Financial Report</h1>
                <p style={{ ...bodyText, marginTop: 4 }}>Period: {report.period.label}</p>
                <p style={{ margin: '4px 0 0', fontSize: 13, color: '#64748b' }}>Generated: {report.period.generated_label}</p>
                <ul style={{ margin: '12px 0 0', paddingLeft: 18, color: '#475569', fontSize: 14 }}>
                    {report.filter_labels.map((label) => (
                        <li key={label} style={{ marginBottom: 4 }}>
                            {label}
                        </li>
                    ))}
                </ul>
            </header>

            {!report.has_data ? (
                <p style={bodyText}>No transactions found for this period.</p>
            ) : (
                <>
                    <section>
                        <h2 style={sectionTitle}>Summary</h2>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
                            {[
                                ['Total Income', report.summary.income],
                                ['Total Expenses', report.summary.expense],
                                ['Balance', report.summary.balance],
                                ['Savings Rate', report.summary.savings_rate === null ? 'N/A' : `${report.summary.savings_rate.toFixed(1)}%`],
                            ].map(([label, value]) => (
                                <div key={label} style={{ borderRadius: 8, border: '1px solid #e2e8f0', padding: 12 }}>
                                    <p style={{ margin: 0, fontSize: 11, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#64748b' }}>
                                        {label}
                                    </p>
                                    <p style={{ margin: '4px 0 0', fontSize: 20, fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>
                                        {label === 'Savings Rate' ? value : formatMoney(String(value))}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section>
                        <h2 style={sectionTitle}>Spending by Category</h2>
                        <ReportCategoryChart items={report.category_breakdown} exportMode />
                    </section>

                    <section>
                        <h2 style={sectionTitle}>Daily Spending</h2>
                        <ReportDailyChart points={report.daily_summary} exportMode />
                    </section>

                    <section>
                        <h2 style={sectionTitle}>Weekly Spending</h2>
                        <ReportWeeklyChart items={report.weekly_summary} exportMode />
                    </section>

                    <section>
                        <h2 style={sectionTitle}>Income vs Expenses — Last 6 Months</h2>
                        <p style={{ ...bodyText, marginBottom: 8 }}>{report.six_month_note}</p>
                        <ReportSixMonthChart points={report.six_month_trend} exportMode />
                    </section>

                    <section>
                        <h2 style={sectionTitle}>Transaction Details</h2>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                            <thead>
                                <tr>
                                    {['Date', 'Description', 'Category', 'Type', 'Amount'].map((heading, index) => (
                                        <th
                                            key={heading}
                                            style={{
                                                padding: '8px 8px 8px 0',
                                                textAlign: index === 4 ? 'right' : 'left',
                                                borderBottom: '1px solid #e2e8f0',
                                                fontWeight: 600,
                                            }}
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {report.transactions.data.slice(0, 40).map((txn) => (
                                    <tr key={txn.id}>
                                        <td style={{ padding: '8px 8px 8px 0', whiteSpace: 'nowrap', borderBottom: '1px solid #f1f5f9' }}>
                                            {txn.transaction_date}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px 8px 8px 0',
                                                maxWidth: 220,
                                                wordBreak: 'break-word',
                                                borderBottom: '1px solid #f1f5f9',
                                            }}
                                        >
                                            {txn.description || '—'}
                                        </td>
                                        <td style={{ padding: '8px 8px 8px 0', borderBottom: '1px solid #f1f5f9' }}>
                                            {txn.category?.name ?? 'Uncategorized'}
                                        </td>
                                        <td style={{ padding: '8px 8px 8px 0', textTransform: 'capitalize', borderBottom: '1px solid #f1f5f9' }}>
                                            {txn.type}
                                        </td>
                                        <td
                                            style={{
                                                padding: '8px 0',
                                                textAlign: 'right',
                                                fontVariantNumeric: 'tabular-nums',
                                                borderBottom: '1px solid #f1f5f9',
                                            }}
                                        >
                                            {formatMoney(txn.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {report.transactions.total > 40 ? (
                            <p style={{ margin: '8px 0 0', fontSize: 12, color: '#64748b' }}>
                                Showing first 40 of {report.transactions.total} matching transactions.
                            </p>
                        ) : null}
                    </section>
                </>
            )}
        </div>
    );
}

export default function ReportsIndex({ report, error }: Props) {
    const exportRef = useRef<HTMLDivElement>(null);
    const [exporting, setExporting] = useState<'pdf' | 'image' | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);
    const [exportSurfaceActive, setExportSurfaceActive] = useState(false);

    const [from, setFrom] = useState(report?.filters.from ?? '');
    const [to, setTo] = useState(report?.filters.to ?? '');
    const [category, setCategory] = useState(
        report?.filters.category === null || report?.filters.category === undefined ? '' : String(report.filters.category),
    );
    const [incomeSource, setIncomeSource] = useState(
        report?.filters.income_source === null || report?.filters.income_source === undefined ? '' : String(report.filters.income_source),
    );

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();
        router.get(
            route('reports.index'),
            {
                from: from || undefined,
                to: to || undefined,
                category: category || undefined,
                income_source: incomeSource || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const resetFilters = () => {
        router.get(route('reports.index'), {}, { preserveState: false });
    };

    const runExport = async (kind: 'pdf' | 'image') => {
        if (!report || exporting) {
            return;
        }

        setExportError(null);
        setExporting(kind);
        setExportSurfaceActive(true);

        try {
            // Mount off-screen export DOM, then wait until the ref + charts are ready.
            const started = performance.now();
            while (!exportRef.current && performance.now() - started < 2000) {
                await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
            }

            if (!exportRef.current) {
                throw new Error('Export surface unavailable');
            }

            await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));

            if (kind === 'pdf') {
                await exportReportAsPdf(exportRef.current, report);
            } else {
                await exportReportAsImage(exportRef.current, report);
            }
        } catch {
            setExportError('Export failed. Please try again.');
        } finally {
            setExporting(null);
            setExportSurfaceActive(false);
        }
    };

    if (error || !report) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Reports" />
                <FintechPage>
                    <p className="text-destructive">{error ?? 'Report unavailable.'}</p>
                </FintechPage>
            </AppLayout>
        );
    }

    const savingsLabel = report.summary.savings_rate === null ? 'N/A' : `${report.summary.savings_rate.toFixed(1)}%`;
    const balanceTone = Number(report.summary.balance) < 0 ? 'text-destructive' : 'text-foreground';
    const canExport = report.has_data;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <FintechPage stagger={false} className="min-w-0 print:p-0">
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Reports"
                    description="Understand where your money is going."
                    visual={<ReportVisual className="size-14 print:hidden" />}
                    actions={
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap print:hidden">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                aria-label={exporting === 'pdf' ? 'Preparing PDF' : 'Export PDF'}
                                aria-busy={exporting === 'pdf'}
                                disabled={!canExport || exporting !== null}
                                onClick={() => void runExport('pdf')}
                            >
                                <Download className="mr-2 size-4" />
                                {exporting === 'pdf' ? 'Preparing PDF…' : 'Export PDF'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                aria-label={exporting === 'image' ? 'Preparing image' : 'Export Image'}
                                aria-busy={exporting === 'image'}
                                disabled={!canExport || exporting !== null}
                                onClick={() => void runExport('image')}
                            >
                                <FileImage className="mr-2 size-4" />
                                {exporting === 'image' ? 'Preparing image…' : 'Export Image'}
                            </Button>
                        </div>
                    }
                />

                {exportError ? (
                    <p className="text-destructive text-sm print:hidden" role="alert">
                        {exportError}
                    </p>
                ) : null}

                <form onSubmit={applyFilters} className="cc-toolbar min-w-0 flex-col items-stretch gap-3 !p-3 lg:items-end print:hidden">
                    <div className="flex min-w-0 items-center justify-between gap-3">
                        <h2 className="cc-text-secondary text-xs font-semibold tracking-wide uppercase">Filters</h2>
                        <div className="flex gap-2 lg:hidden">
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button type="button" variant="outline" size="sm">
                                        <Filter className="mr-2 size-4" />
                                        Filters
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
                                    <SheetHeader>
                                        <SheetTitle>Report filters</SheetTitle>
                                    </SheetHeader>
                                    <div className="mt-4 space-y-4">
                                        <FilterFields
                                            idPrefix="report-mobile"
                                            from={from}
                                            to={to}
                                            category={category}
                                            incomeSource={incomeSource}
                                            expenseCategories={report.expense_categories}
                                            incomeSources={report.income_sources}
                                            onFrom={setFrom}
                                            onTo={setTo}
                                            onCategory={setCategory}
                                            onIncomeSource={setIncomeSource}
                                        />
                                        <div className="flex gap-2">
                                            <Button type="button" onClick={() => applyFilters()}>
                                                Apply Filters
                                            </Button>
                                            <Button type="button" variant="outline" onClick={resetFilters}>
                                                Reset
                                            </Button>
                                        </div>
                                    </div>
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>

                    <div className="hidden w-full lg:block">
                        <FilterFields
                            idPrefix="report"
                            from={from}
                            to={to}
                            category={category}
                            incomeSource={incomeSource}
                            expenseCategories={report.expense_categories}
                            incomeSources={report.income_sources}
                            onFrom={setFrom}
                            onTo={setTo}
                            onCategory={setCategory}
                            onIncomeSource={setIncomeSource}
                        />
                    </div>

                    <div className="hidden gap-2 lg:flex">
                        <Button type="submit" size="sm">
                            Apply Filters
                        </Button>
                        <Button type="button" variant="outline" size="sm" onClick={resetFilters}>
                            <RotateCcw className="mr-2 size-4" />
                            Reset
                        </Button>
                    </div>

                    <p className="cc-text-tertiary text-xs">{report.filter_semantics.income_source}</p>
                </form>

                {!report.has_data ? (
                    <FintechEmpty
                        className="print:hidden"
                        visual={<ReportVisual className="size-16" />}
                        title="No transactions found for this period."
                        description="Try clearing filters or add a transaction."
                        action={
                            <>
                                <Button type="button" variant="outline" onClick={resetFilters}>
                                    Clear Filters
                                </Button>
                                <Button asChild>
                                    <Link href={route('transactions.create')}>Add Transaction</Link>
                                </Button>
                            </>
                        }
                    />
                ) : (
                    <>
                        <section className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Report summary">
                            <article className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Total Income</p>
                                <p className="mt-2 text-2xl font-semibold break-words tabular-nums">{formatMoney(report.summary.income)}</p>
                            </article>
                            <article className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Total Expenses</p>
                                <p className="mt-2 text-2xl font-semibold break-words tabular-nums">{formatMoney(report.summary.expense)}</p>
                            </article>
                            <article className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Balance</p>
                                <p className={cn('mt-2 text-2xl font-semibold break-words tabular-nums', balanceTone)}>
                                    {formatMoney(report.summary.balance)}
                                </p>
                            </article>
                            <article className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Savings Rate</p>
                                <p className="mt-2 text-2xl font-semibold break-words tabular-nums">{savingsLabel}</p>
                                {report.top_category ? (
                                    <p className="text-muted-foreground mt-1 text-xs break-words">
                                        Top category: {report.top_category.name} ({formatMoney(report.top_category.amount)})
                                    </p>
                                ) : null}
                            </article>
                        </section>

                        <section className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                            <h2 className="text-lg font-semibold">Spending by Category</h2>
                            <p className="text-muted-foreground mt-1 mb-4 text-sm">Category-wise expenses for {report.period.label}.</p>
                            <div className="min-w-0 print:hidden">
                                <ReportCategoryChart items={report.category_breakdown} />
                            </div>
                        </section>

                        <div className="grid min-w-0 gap-4 xl:grid-cols-2">
                            <section className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <h2 className="text-lg font-semibold">Daily Spending</h2>
                                <p className="text-muted-foreground mt-1 mb-4 text-sm">Daily expense totals for the selected range.</p>
                                <div className="min-w-0 overflow-x-auto">
                                    <ReportDailyChart points={report.daily_summary} />
                                </div>
                            </section>
                            <section className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                                <h2 className="text-lg font-semibold">Weekly Spending</h2>
                                <p className="text-muted-foreground mt-1 mb-4 text-sm">Calendar weeks (Monday start) within the selected range.</p>
                                <div className="min-w-0 overflow-x-auto">
                                    <ReportWeeklyChart items={report.weekly_summary} />
                                </div>
                            </section>
                        </div>

                        <section className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                            <h2 className="text-lg font-semibold">Income vs Expenses — Last 6 Months</h2>
                            <p className="text-muted-foreground mt-1 mb-4 text-sm">{report.six_month_note}</p>
                            <div className="min-w-0 overflow-x-auto">
                                <ReportSixMonthChart points={report.six_month_trend} />
                            </div>
                        </section>

                        <section className="border-border bg-card min-w-0 rounded-xl border p-4 shadow-sm">
                            <h2 className="text-lg font-semibold">Report Details</h2>
                            <p className="text-muted-foreground mt-1 mb-4 text-sm">
                                Matching transactions ({report.transactions.total}). Pagination keeps your filters.
                            </p>

                            {/* Mobile / narrow: stacked rows so values stay readable without clipping */}
                            <ul className="divide-border divide-y lg:hidden" aria-label="Matching transactions">
                                {report.transactions.data.map((txn) => (
                                    <li key={txn.id} className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-muted-foreground text-xs whitespace-nowrap tabular-nums">{txn.transaction_date}</p>
                                            <p className="text-sm font-medium whitespace-nowrap tabular-nums">{formatMoney(txn.amount)}</p>
                                        </div>
                                        <p className="min-w-0 text-sm break-words">{txn.description || '—'}</p>
                                        <p className="text-muted-foreground text-xs">
                                            <span className="capitalize">{txn.type}</span>
                                            {' · '}
                                            {txn.category?.name ?? 'Uncategorized'}
                                        </p>
                                    </li>
                                ))}
                            </ul>

                            {/* Desktop / wide: table with fixed layout so long descriptions cannot blow out columns */}
                            <div className="hidden max-w-full min-w-0 overflow-x-auto overscroll-x-contain lg:block">
                                <table className="w-full table-fixed border-collapse text-sm">
                                    <thead>
                                        <tr className="border-border border-b text-left">
                                            <th className="w-[7.25rem] py-2 pr-3 font-medium">Date</th>
                                            <th className="py-2 pr-3 font-medium">Description</th>
                                            <th className="w-[8rem] py-2 pr-3 font-medium">Category</th>
                                            <th className="w-[5.5rem] py-2 pr-3 font-medium">Type</th>
                                            <th className="w-[7rem] py-2 text-right font-medium">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.transactions.data.map((txn) => (
                                            <tr key={txn.id} className="border-border/60 border-b">
                                                <td className="py-2 pr-3 whitespace-nowrap">{txn.transaction_date}</td>
                                                <td className="min-w-0 py-2 pr-3 break-words">{txn.description || '—'}</td>
                                                <td className="min-w-0 truncate py-2 pr-3" title={txn.category?.name ?? 'Uncategorized'}>
                                                    {txn.category?.name ?? 'Uncategorized'}
                                                </td>
                                                <td className="py-2 pr-3 capitalize">{txn.type}</td>
                                                <td className="py-2 text-right whitespace-nowrap tabular-nums">{formatMoney(txn.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="mt-4 print:hidden">
                                <PaginationNav links={report.transactions.links} />
                            </div>
                        </section>
                    </>
                )}
            </FintechPage>

            {/*
              Mount export surface only while exporting so the 900px layout cannot
              expand document scroll width on normal page views.
            */}
            {exportSurfaceActive ? (
                <div aria-hidden className="pointer-events-none fixed top-0 left-0 z-[-1] h-0 w-0 overflow-hidden opacity-0">
                    <div ref={exportRef} style={{ width: 900 }}>
                        <ReportExportDocument report={report} />
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}

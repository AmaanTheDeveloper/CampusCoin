import { EmptyWalletVisual } from '@/components/dashboard/visuals/EmptyWalletVisual';
import { SmartScanTrigger } from '@/components/document-scan/smart-scan-trigger';
import { FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { PaginationNav } from '@/components/pagination-nav';
import { ArchiveTransactionDialog } from '@/components/transactions/archive-transaction-dialog';
import { RestoreTransactionDialog } from '@/components/transactions/restore-transaction-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type CategoryOption, type Paginated, type SharedData, type Transaction, type TypeOption } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, Eye, Filter, Plus, RotateCcw, Search } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Transactions', href: '/transactions' }];

const UNDO_WINDOW_MS = 12_000;

type Filters = {
    status: 'active' | 'archived';
    type: string | null;
    category_id: number | null;
    from: string | null;
    to: string | null;
    search: string | null;
};

type IndexProps = {
    transactions: Paginated<Transaction>;
    filters: Filters;
    typeOptions: TypeOption[];
    categoryOptions: CategoryOption[];
};

function transactionTitle(transaction: Transaction): { text: string; isFallback: boolean } {
    if (transaction.description?.trim()) {
        return { text: transaction.description.trim(), isFallback: false };
    }
    if (transaction.category?.name) {
        return { text: `${transaction.category.name} transaction`, isFallback: true };
    }
    return { text: 'No description', isFallback: true };
}

function formatShortDate(value: string): string {
    try {
        const date = new Date(`${value}T00:00:00`);
        return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    } catch {
        return value;
    }
}

export default function TransactionsIndex({ transactions, filters, typeOptions, categoryOptions }: IndexProps) {
    const { flash } = usePage<SharedData>().props;
    const isArchivedView = filters.status === 'archived';
    const [localFilters, setLocalFilters] = useState({
        type: filters.type ?? '',
        category_id: filters.category_id ? String(filters.category_id) : '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        search: filters.search ?? '',
    });
    const [archiveTarget, setArchiveTarget] = useState<Transaction | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<Transaction | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [undoTransactionId, setUndoTransactionId] = useState<number | null>(flash?.archived_transaction_id ?? null);
    const [spendingAlert, setSpendingAlert] = useState(flash?.spending_alert ?? null);
    const [undoProcessing, setUndoProcessing] = useState(false);
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setUndoTransactionId(flash?.archived_transaction_id ?? null);
        setSpendingAlert(flash?.spending_alert ?? null);
    }, [flash?.success, flash?.archived_transaction_id, flash?.spending_alert]);

    useEffect(() => {
        if (undoTransactionId === null) {
            return;
        }

        const timer = window.setTimeout(() => {
            setUndoTransactionId(null);
        }, UNDO_WINDOW_MS);

        return () => window.clearTimeout(timer);
    }, [undoTransactionId]);

    useEffect(() => {
        setLocalFilters({
            type: filters.type ?? '',
            category_id: filters.category_id ? String(filters.category_id) : '',
            from: filters.from ?? '',
            to: filters.to ?? '',
            search: filters.search ?? '',
        });
    }, [filters.type, filters.category_id, filters.from, filters.to, filters.search]);

    const activeFilterCount = useMemo(() => {
        return [localFilters.type, localFilters.category_id, localFilters.from, localFilters.to].filter(Boolean).length;
    }, [localFilters.type, localFilters.category_id, localFilters.from, localFilters.to]);

    const filterQuery = (overrides: Record<string, string | undefined> = {}) => ({
        status: isArchivedView ? 'archived' : undefined,
        type: localFilters.type || undefined,
        category_id: localFilters.category_id || undefined,
        from: localFilters.from || undefined,
        to: localFilters.to || undefined,
        search: localFilters.search || undefined,
        ...overrides,
    });

    const applyFilters = (e?: FormEvent) => {
        e?.preventDefault();
        router.get(route('transactions.index'), filterQuery(), { preserveState: true, replace: true });
        setMobileFiltersOpen(false);
    };

    const clearFilters = () => {
        setLocalFilters({ type: '', category_id: '', from: '', to: '', search: '' });
        router.get(route('transactions.index'), { status: isArchivedView ? 'archived' : undefined }, { preserveState: true, replace: true });
        setMobileFiltersOpen(false);
    };

    const setStatus = (status: 'active' | 'archived') => {
        router.get(
            route('transactions.index'),
            {
                status: status === 'archived' ? 'archived' : undefined,
                type: localFilters.type || undefined,
                category_id: localFilters.category_id || undefined,
                from: localFilters.from || undefined,
                to: localFilters.to || undefined,
                search: localFilters.search || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const undoArchive = () => {
        if (undoTransactionId === null || undoProcessing) {
            return;
        }

        setUndoProcessing(true);
        router.post(
            route('transactions.restore', undoTransactionId),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setUndoProcessing(false);
                    setUndoTransactionId(null);
                },
                onError: () => {
                    setUndoProcessing(false);
                },
            },
        );
    };

    const filterFields = (
        <>
            <div className="grid gap-1.5">
                <Label htmlFor="filter-type" className="cc-text-secondary text-xs">
                    Type
                </Label>
                <select
                    id="filter-type"
                    className="cc-field w-full"
                    value={localFilters.type}
                    onChange={(e) => setLocalFilters((prev) => ({ ...prev, type: e.target.value }))}
                >
                    <option value="">All</option>
                    {typeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="filter-category" className="cc-text-secondary text-xs">
                    Category
                </Label>
                <select
                    id="filter-category"
                    className="cc-field w-full"
                    value={localFilters.category_id}
                    onChange={(e) => setLocalFilters((prev) => ({ ...prev, category_id: e.target.value }))}
                >
                    <option value="">All categories</option>
                    {categoryOptions.map((category) => (
                        <option key={category.id} value={category.id}>
                            {category.name}
                            {category.is_default ? ' (System)' : ''}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="filter-from" className="cc-text-secondary text-xs">
                    From
                </Label>
                <Input
                    id="filter-from"
                    type="date"
                    className="cc-field"
                    value={localFilters.from}
                    onChange={(e) => setLocalFilters((prev) => ({ ...prev, from: e.target.value }))}
                />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="filter-to" className="cc-text-secondary text-xs">
                    To
                </Label>
                <Input
                    id="filter-to"
                    type="date"
                    className="cc-field"
                    value={localFilters.to}
                    onChange={(e) => setLocalFilters((prev) => ({ ...prev, to: e.target.value }))}
                />
            </div>
        </>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />

            <FintechPage stagger={false}>
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Transactions"
                    description="Track and manage your financial activity."
                    actions={
                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                            <SmartScanTrigger className="w-full shrink-0 sm:w-auto" />
                            <Button variant="outline" asChild className="w-full shrink-0 sm:w-auto">
                                <Link href={route('transactions.import.create')}>Import CSV</Link>
                            </Button>
                            <Button asChild className="w-full shrink-0 sm:w-auto">
                                <Link href={route('transactions.create')}>
                                    <Plus className="mr-2 size-4" aria-hidden />
                                    Add transaction
                                </Link>
                            </Button>
                        </div>
                    }
                />

                {successMessage ? (
                    <FintechFlash>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <span>{successMessage}</span>
                            {undoTransactionId !== null ? (
                                <Button type="button" variant="outline" size="sm" onClick={undoArchive} disabled={undoProcessing}>
                                    {undoProcessing ? 'Undoing…' : 'Undo'}
                                </Button>
                            ) : null}
                        </div>
                    </FintechFlash>
                ) : null}

                {spendingAlert ? (
                    <FintechFlash tone="warning">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1 text-sm">
                                <p className="font-semibold">Unusual Expense Detected</p>
                                <p>
                                    {formatMoney(spendingAlert.transaction?.amount ?? spendingAlert.explanation?.amount ?? '0.00')}
                                    {spendingAlert.transaction?.category || spendingAlert.explanation?.category_name
                                        ? ` on ${spendingAlert.transaction?.category ?? spendingAlert.explanation?.category_name}`
                                        : ''}
                                </p>
                                <p className="cc-text-secondary text-xs">{spendingAlert.reason}</p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {spendingAlert.transaction ? (
                                    <Button type="button" size="sm" variant="outline" asChild>
                                        <Link href={route('transactions.show', spendingAlert.transaction.id)}>View transaction</Link>
                                    </Button>
                                ) : null}
                                <Button type="button" size="sm" variant="ghost" asChild>
                                    <Link href={route('spending-alerts.index')}>Spending Alerts</Link>
                                </Button>
                            </div>
                        </div>
                    </FintechFlash>
                ) : null}

                <div className="flex flex-wrap gap-2" role="tablist" aria-label="Transaction status">
                    <Button
                        type="button"
                        size="sm"
                        variant={!isArchivedView ? 'default' : 'outline'}
                        role="tab"
                        aria-selected={!isArchivedView}
                        onClick={() => setStatus('active')}
                    >
                        Active
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant={isArchivedView ? 'default' : 'outline'}
                        role="tab"
                        aria-selected={isArchivedView}
                        onClick={() => setStatus('archived')}
                    >
                        Archived
                    </Button>
                </div>

                {/* Desktop compact toolbar — wrapper keeps .cc-toolbar display:flex from overriding Tailwind hidden */}
                <div className="hidden lg:block">
                    <form onSubmit={applyFilters} className="cc-toolbar">
                        <div className="cc-search-field">
                            <span className="cc-search-field__icon" aria-hidden>
                                <Search />
                            </span>
                            <Input
                                id="filter-search"
                                type="search"
                                value={localFilters.search}
                                onChange={(e) => setLocalFilters((prev) => ({ ...prev, search: e.target.value }))}
                                placeholder="Search…"
                                className="cc-field cc-search-field__input h-10 border-0 bg-transparent shadow-none focus-visible:ring-0"
                                aria-label="Search description"
                            />
                        </div>
                        <select
                            className="cc-field max-w-[9rem]"
                            value={localFilters.type}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, type: e.target.value }))}
                            aria-label="Type"
                        >
                            <option value="">Type</option>
                            {typeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className="cc-field max-w-[11rem]"
                            value={localFilters.category_id}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, category_id: e.target.value }))}
                            aria-label="Category"
                        >
                            <option value="">Category</option>
                            {categoryOptions.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <Input
                            type="date"
                            className="cc-field max-w-[9.5rem]"
                            value={localFilters.from}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, from: e.target.value }))}
                            aria-label="From date"
                        />
                        <Input
                            type="date"
                            className="cc-field max-w-[9.5rem]"
                            value={localFilters.to}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, to: e.target.value }))}
                            aria-label="To date"
                        />
                        <Button type="submit" size="sm" variant="secondary">
                            Apply
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={clearFilters}>
                            Clear
                        </Button>
                    </form>
                </div>

                {/* Mobile / tablet: search + filter sheet */}
                <div className="flex gap-2 lg:hidden">
                    <form onSubmit={applyFilters} className="cc-toolbar cc-search-inline min-w-0 flex-1 !flex-nowrap">
                        <span className="cc-search-inline__icon" aria-hidden>
                            <Search />
                        </span>
                        <Input
                            type="search"
                            value={localFilters.search}
                            onChange={(e) => setLocalFilters((prev) => ({ ...prev, search: e.target.value }))}
                            placeholder="Search…"
                            className="h-9 min-w-0 flex-1 border-0 bg-transparent px-0 shadow-none focus-visible:ring-0"
                            aria-label="Search description"
                        />
                    </form>
                    <Sheet open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}>
                        <SheetTrigger asChild>
                            <Button type="button" variant="outline" className="shrink-0 gap-2">
                                <Filter className="size-4" aria-hidden />
                                Filters
                                {activeFilterCount > 0 ? (
                                    <span className="rounded-full bg-[#E94F09] px-1.5 text-[10px] text-white">{activeFilterCount}</span>
                                ) : null}
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="bottom" className="bg-card border-border rounded-t-2xl">
                            <SheetHeader>
                                <SheetTitle className="text-white">Filters</SheetTitle>
                            </SheetHeader>
                            <form onSubmit={applyFilters} className="mt-4 grid gap-4 pb-6">
                                {filterFields}
                                <div className="flex gap-2">
                                    <Button type="submit" className="flex-1">
                                        Apply filters
                                    </Button>
                                    <Button type="button" variant="outline" className="flex-1" onClick={clearFilters}>
                                        Clear
                                    </Button>
                                </div>
                            </form>
                        </SheetContent>
                    </Sheet>
                </div>

                {transactions.data.length === 0 ? (
                    <div className="cc-elev-2 flex flex-1 flex-col items-center justify-center border-dashed px-6 py-14 text-center">
                        <EmptyWalletVisual />
                        <h3 className="mt-4 text-lg font-semibold text-white">
                            {isArchivedView ? 'No archived transactions' : 'No transactions yet'}
                        </h3>
                        <p className="cc-text-secondary mt-2 max-w-md text-sm">
                            {isArchivedView
                                ? 'Archived transactions are kept for history and can be restored anytime.'
                                : 'Start tracking your money by adding your first income or expense.'}
                        </p>
                        {!isArchivedView ? (
                            <Button asChild className="mt-6">
                                <Link href={route('transactions.create')}>
                                    <Plus className="mr-2 size-4" aria-hidden />
                                    Add transaction
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                ) : (
                    <>
                        <div className="cc-elev-2 hidden overflow-hidden lg:block">
                            <div className="divide-y divide-white/5">
                                {transactions.data.map((transaction) => {
                                    const title = transactionTitle(transaction);
                                    return (
                                        <div
                                            key={transaction.id}
                                            className={cn(
                                                'grid grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)_5.5rem_minmax(6.5rem,8rem)_auto] items-center gap-3 px-4 py-3.5 transition-colors hover:bg-white/[0.03]',
                                                isArchivedView && 'opacity-70',
                                            )}
                                        >
                                            <div className="min-w-0">
                                                <p
                                                    className={cn(
                                                        'truncate font-medium text-white',
                                                        title.isFallback && 'cc-text-secondary font-normal',
                                                    )}
                                                >
                                                    {title.text}
                                                </p>
                                                <p className="cc-text-tertiary mt-0.5 text-xs">
                                                    {formatShortDate(transaction.transaction_date)}
                                                    {isArchivedView ? ' · Archived' : ''}
                                                </p>
                                            </div>
                                            <p className="cc-text-secondary truncate text-sm">{transaction.category?.name ?? 'Uncategorized'}</p>
                                            <TypeBadge type={transaction.type} />
                                            <p
                                                className={cn(
                                                    'text-right text-sm font-semibold tabular-nums',
                                                    transaction.type === 'income' ? 'text-emerald-400/90' : 'text-white',
                                                )}
                                            >
                                                <span className="sr-only">{transaction.type === 'income' ? 'Income' : 'Expense'} </span>
                                                {formatMoney(transaction.amount, { signed: true, type: transaction.type })}
                                            </p>
                                            <div className="flex justify-end gap-1.5">
                                                <Button variant="ghost" size="icon" asChild className="cc-icon-btn !size-11">
                                                    <Link
                                                        href={route('transactions.show', transaction.id)}
                                                        aria-label={`View transaction ${transaction.id}`}
                                                    >
                                                        <Eye className="size-4" />
                                                    </Link>
                                                </Button>
                                                {isArchivedView ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        type="button"
                                                        className="cc-icon-btn !size-11"
                                                        aria-label={`Restore transaction ${transaction.id}`}
                                                        onClick={() => setRestoreTarget(transaction)}
                                                    >
                                                        <RotateCcw className="size-4" />
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        type="button"
                                                        className="cc-icon-btn !size-11"
                                                        aria-label={`Archive transaction ${transaction.id}`}
                                                        onClick={() => setArchiveTarget(transaction)}
                                                    >
                                                        <Archive className="size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="grid gap-2 lg:hidden">
                            {transactions.data.map((transaction) => {
                                const title = transactionTitle(transaction);
                                return (
                                    <article key={transaction.id} className={cn('cc-tx-row', isArchivedView && 'opacity-70')}>
                                        <div className="min-w-0">
                                            <p
                                                className={cn(
                                                    'min-w-0 font-medium break-words text-white',
                                                    title.isFallback && 'cc-text-secondary font-normal',
                                                )}
                                            >
                                                {title.text}
                                            </p>
                                            <p className="cc-text-tertiary mt-1 text-xs">
                                                {transaction.category?.name ?? 'Uncategorized'} · {formatShortDate(transaction.transaction_date)}
                                            </p>
                                        </div>
                                        <p
                                            className={cn(
                                                'text-right text-base font-semibold tabular-nums',
                                                transaction.type === 'income' ? 'text-emerald-400/90' : 'text-white',
                                            )}
                                        >
                                            {formatMoney(transaction.amount, { signed: true, type: transaction.type })}
                                        </p>
                                        <div className="col-span-2 flex gap-2">
                                            <Button variant="outline" size="sm" asChild className="min-h-11 flex-1">
                                                <Link href={route('transactions.show', transaction.id)}>View</Link>
                                            </Button>
                                            {isArchivedView ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    type="button"
                                                    className="min-h-11 flex-1"
                                                    onClick={() => setRestoreTarget(transaction)}
                                                >
                                                    Restore
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    type="button"
                                                    className="min-h-11 flex-1"
                                                    onClick={() => setArchiveTarget(transaction)}
                                                >
                                                    Archive
                                                </Button>
                                            )}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>

                        {transactions.last_page > 1 && <PaginationNav links={transactions.links} />}
                    </>
                )}
            </FintechPage>

            {archiveTarget ? (
                <ArchiveTransactionDialog
                    transactionId={archiveTarget.id}
                    description={archiveTarget.description}
                    open={true}
                    onOpenChange={(open) => {
                        if (!open) {
                            setArchiveTarget(null);
                        }
                    }}
                />
            ) : null}

            {restoreTarget ? (
                <RestoreTransactionDialog
                    transactionId={restoreTarget.id}
                    description={restoreTarget.description}
                    open={true}
                    onOpenChange={(open) => {
                        if (!open) {
                            setRestoreTarget(null);
                        }
                    }}
                />
            ) : null}
        </AppLayout>
    );
}

function TypeBadge({ type }: { type: Transaction['type'] }) {
    return (
        <Badge
            variant="outline"
            className={cn('font-normal capitalize', type === 'income' ? 'border-emerald-500/30 text-emerald-400' : 'border-white/15 text-[#b8b8b8]')}
        >
            {type}
        </Badge>
    );
}

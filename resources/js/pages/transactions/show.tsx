import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { RestoreTransactionDialog } from '@/components/transactions/restore-transaction-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type Transaction } from '@/types';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';

const breadcrumbs = (id: number): BreadcrumbItem[] => [
    { title: 'Transactions', href: '/transactions' },
    { title: 'View', href: `/transactions/${id}` },
];

type ShowProps = {
    transaction: Transaction;
};

export default function ShowTransaction({ transaction }: ShowProps) {
    const [restoreOpen, setRestoreOpen] = useState(false);
    const isArchived = Boolean(transaction.is_archived);

    return (
        <AppLayout breadcrumbs={breadcrumbs(transaction.id)}>
            <Head title="Transaction details" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Transaction details"
                    description={isArchived ? 'Archived entry — restore it before editing' : 'Read-only view of this income or expense entry'}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {isArchived ? (
                                <Button type="button" onClick={() => setRestoreOpen(true)}>
                                    Restore
                                </Button>
                            ) : null}
                            <Button variant="outline" asChild>
                                <Link href={route('transactions.index', isArchived ? { status: 'archived' } : {})}>Back to transactions</Link>
                            </Button>
                        </div>
                    }
                />

                {isArchived ? (
                    <div role="status" className="rounded-lg border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-[#b0b0b0]">
                        This transaction is archived and excluded from active balances and totals.
                    </div>
                ) : null}

                <section
                    className="border-border bg-card mx-auto w-full max-w-xl rounded-xl border p-4 shadow-sm"
                    aria-labelledby="transaction-details-heading"
                >
                    <h2 id="transaction-details-heading" className="sr-only">
                        Transaction fields
                    </h2>
                    <dl className="space-y-4">
                        <DetailRow label="Status">
                            <Badge variant={isArchived ? 'outline' : 'secondary'}>{isArchived ? 'Archived' : 'Active'}</Badge>
                        </DetailRow>

                        <DetailRow label="Type">
                            <Badge variant={transaction.type === 'income' ? 'default' : 'secondary'} className="capitalize">
                                {transaction.type}
                            </Badge>
                        </DetailRow>

                        <DetailRow label="Amount">
                            <span className="font-semibold tabular-nums">
                                {formatMoney(transaction.amount, {
                                    signed: true,
                                    type: transaction.type,
                                })}
                            </span>
                        </DetailRow>

                        <DetailRow label="Category">
                            <span>{transaction.category?.name ?? 'Uncategorized'}</span>
                        </DetailRow>

                        <DetailRow label="Description">
                            <span>{transaction.description || 'No description'}</span>
                        </DetailRow>

                        <DetailRow label="Transaction date">
                            <span className="tabular-nums">{transaction.transaction_date}</span>
                        </DetailRow>

                        {transaction.created_at ? (
                            <DetailRow label="Created">
                                <span className="text-sm tabular-nums">{formatTimestamp(transaction.created_at)}</span>
                            </DetailRow>
                        ) : null}
                    </dl>
                </section>
            </FintechPage>

            {isArchived ? (
                <RestoreTransactionDialog
                    transactionId={transaction.id}
                    description={transaction.description}
                    open={restoreOpen}
                    onOpenChange={setRestoreOpen}
                />
            ) : null}
        </AppLayout>
    );
}

function DetailRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="border-border grid gap-1 border-b pb-3 last:border-0 last:pb-0 sm:grid-cols-[10rem_1fr] sm:items-start sm:gap-4">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

function formatTimestamp(value: string): string {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

import { ClearSignalVisual, DuplicateReviewVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Duplicate Review', href: '/duplicate-review' },
];

export type DuplicateCandidate = {
    id: number;
    signal_type: string;
    signal_label: string;
    severity: string;
    status: string;
    reason: string;
    detected_at: string | null;
    match_type?: string;
    evidence?: string[];
    suggested_remove_transaction_id?: number | null;
    suggested_keep_transaction_id?: number | null;
    transaction: {
        id: number;
        description: string;
        amount: string;
        type: string;
        category: string | null;
        transaction_date: string;
    } | null;
    comparison_transaction: {
        id: number;
        description: string;
        amount: string;
        type: string;
        category: string | null;
        transaction_date: string;
    } | null;
    side_by_side?: {
        a: DuplicateSide | null;
        b: DuplicateSide | null;
    };
};

type DuplicateSide = {
    id: number;
    description: string;
    amount: string;
    type: string;
    category: string | null;
    transaction_date: string;
    created_at: string | null;
    source_hint: string;
};

type ReviewPayload = {
    pending: DuplicateCandidate[];
    resolved: DuplicateCandidate[];
    pending_count: number;
    disclaimer: string;
    detection: {
        date_window_days: number;
        expense_only: boolean;
    };
};

type Props = {
    review: ReviewPayload | null;
    error: string | null;
};

export default function DuplicateReviewIndex({ review, error }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [confirmSignalId, setConfirmSignalId] = useState<number | null>(null);
    const [removeId, setRemoveId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const keepBoth = (signalId: number) => {
        if (processing) return;
        setProcessing(true);
        router.post(
            route('duplicate-review.keep-both', signalId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmRemove = (signal: DuplicateCandidate) => {
        if (processing || removeId === null) return;
        setProcessing(true);
        router.post(
            route('duplicate-review.remove', signal.id),
            {
                remove_transaction_id: removeId,
                confirm: 1,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirmSignalId(null);
                    setRemoveId(null);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Duplicate Review" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Transaction integrity"
                    title="Duplicate Review"
                    description="Possible accidental duplicates — especially from CSV imports. You decide; CampusCoin never auto-deletes."
                    visual={<DuplicateReviewVisual className="size-14" />}
                    actions={review ? <Badge variant="secondary">{review.pending_count} pending</Badge> : null}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Duplicate review unavailable</p>
                        <p className="mt-1 text-[#b8b8b8]">{error}</p>
                    </div>
                ) : null}

                {review ? (
                    <>
                        <p className="cc-text-tertiary text-xs">{review.disclaimer}</p>
                        <p className="cc-text-tertiary text-xs">
                            Match rule: expense + normalized merchant + exact amount + same day
                            {review.detection.date_window_days > 0 ? ` (moderate matches allow ±${review.detection.date_window_days} day)` : ''}.
                        </p>

                        <FintechPanel aria-labelledby="pending-heading">
                            <h2 id="pending-heading" className="text-lg font-semibold text-white">
                                Possible duplicates
                            </h2>
                            {review.pending.length === 0 ? (
                                <FintechEmpty
                                    className="mt-4 border-0 bg-transparent py-8"
                                    visual={<ClearSignalVisual className="size-14" />}
                                    title="No duplicate transactions found"
                                    description="Your transaction records look clean. We'll flag transactions that appear unusually similar during future imports."
                                    action={
                                        <Button asChild variant="outline">
                                            <Link href={route('transactions.index')}>View Transactions</Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <ul className="mt-4 space-y-4">
                                    {review.pending.map((signal) => (
                                        <li key={signal.id}>
                                            <CandidateCard
                                                signal={signal}
                                                expanded={expandedId === signal.id}
                                                confirming={confirmSignalId === signal.id}
                                                removeId={removeId}
                                                processing={processing}
                                                onToggleReview={() => setExpandedId((id) => (id === signal.id ? null : signal.id))}
                                                onKeepBoth={() => keepBoth(signal.id)}
                                                onStartRemove={() => {
                                                    setConfirmSignalId(signal.id);
                                                    setRemoveId(signal.suggested_remove_transaction_id ?? signal.comparison_transaction?.id ?? null);
                                                    setExpandedId(signal.id);
                                                }}
                                                onCancelRemove={() => {
                                                    setConfirmSignalId(null);
                                                    setRemoveId(null);
                                                }}
                                                onChangeRemoveId={setRemoveId}
                                                onConfirmRemove={() => confirmRemove(signal)}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </FintechPanel>

                        {review.resolved.length > 0 ? (
                            <FintechPanel aria-labelledby="resolved-heading">
                                <h2 id="resolved-heading" className="text-lg font-semibold text-white">
                                    Recently resolved
                                </h2>
                                <ul className="mt-3 space-y-2 text-sm">
                                    {review.resolved.slice(0, 8).map((signal) => (
                                        <li key={signal.id} className="flex flex-wrap items-center gap-2 text-[#8a8a8a]">
                                            <Badge variant="outline">{statusLabel(signal.status)}</Badge>
                                            <span>{signal.transaction?.description ?? 'Transaction'}</span>
                                            <span className="tabular-nums">{signal.transaction ? formatMoney(signal.transaction.amount) : ''}</span>
                                        </li>
                                    ))}
                                </ul>
                            </FintechPanel>
                        ) : null}
                    </>
                ) : null}
            </FintechPage>
        </AppLayout>
    );
}

function statusLabel(status: string): string {
    switch (status) {
        case 'kept_both':
            return 'Kept both';
        case 'removed_duplicate':
            return 'Removed duplicate';
        case 'dismissed':
            return 'Dismissed';
        case 'reviewed':
            return 'Reviewed';
        default:
            return status;
    }
}

function CandidateCard({
    signal,
    expanded,
    confirming,
    removeId,
    processing,
    onToggleReview,
    onKeepBoth,
    onStartRemove,
    onCancelRemove,
    onChangeRemoveId,
    onConfirmRemove,
}: {
    signal: DuplicateCandidate;
    expanded: boolean;
    confirming: boolean;
    removeId: number | null;
    processing: boolean;
    onToggleReview: () => void;
    onKeepBoth: () => void;
    onStartRemove: () => void;
    onCancelRemove: () => void;
    onChangeRemoveId: (id: number) => void;
    onConfirmRemove: () => void;
}) {
    const a = signal.side_by_side?.a ?? signal.transaction;
    const b = signal.side_by_side?.b ?? signal.comparison_transaction;
    const moderate = signal.match_type === 'moderate';

    return (
        <article className="border-border rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-semibold text-white">Possible Duplicate</p>
                    <p className="text-muted-foreground mt-1 text-sm">These transactions look very similar.</p>
                </div>
                <Badge variant={moderate ? 'secondary' : 'outline'}>{moderate ? 'Moderate match' : 'High confidence'}</Badge>
            </div>

            <p className="mt-3 text-sm">
                <span className="font-medium">Why flagged:</span> {signal.reason}
            </p>

            <div className="mt-4 overflow-x-auto">
                <table className="w-full min-w-[28rem] text-left text-sm">
                    <thead>
                        <tr className="text-muted-foreground border-b text-xs">
                            <th className="py-2 pr-3 font-medium">Field</th>
                            <th className="py-2 pr-3 font-medium">Transaction A</th>
                            <th className="py-2 font-medium">Transaction B</th>
                        </tr>
                    </thead>
                    <tbody>
                        <CompareRow label="Description" left={a?.description} right={b?.description} />
                        <CompareRow label="Amount" left={a ? formatMoney(a.amount) : '—'} right={b ? formatMoney(b.amount) : '—'} />
                        <CompareRow label="Date" left={a?.transaction_date} right={b?.transaction_date} />
                        <CompareRow label="Category" left={a?.category ?? '—'} right={b?.category ?? '—'} />
                        <CompareRow
                            label="Hint"
                            left={'source_hint' in (a ?? {}) ? (a as DuplicateSide).source_hint : 'Existing / earlier'}
                            right={'source_hint' in (b ?? {}) ? (b as DuplicateSide).source_hint : 'Newer / import'}
                        />
                    </tbody>
                </table>
            </div>

            {expanded ? (
                <div className="bg-muted/30 mt-4 space-y-2 rounded-lg border px-3 py-3 text-sm">
                    <p className="font-medium">Matching evidence</p>
                    <ul className="text-muted-foreground list-inside list-disc space-y-1">
                        {(signal.evidence ?? []).map((point) => (
                            <li key={point}>{point}</li>
                        ))}
                    </ul>
                    {a?.id ? (
                        <p>
                            <Link href={route('transactions.show', a.id)} className="text-primary underline-offset-2 hover:underline">
                                Open transaction A
                            </Link>
                            {b?.id ? (
                                <>
                                    {' · '}
                                    <Link href={route('transactions.show', b.id)} className="text-primary underline-offset-2 hover:underline">
                                        Open transaction B
                                    </Link>
                                </>
                            ) : null}
                        </p>
                    ) : null}
                </div>
            ) : null}

            {confirming ? (
                <div className="border-destructive/30 bg-destructive/5 mt-4 space-y-3 rounded-lg border px-3 py-3 text-sm">
                    <p className="font-medium">Remove this transaction?</p>
                    <p className="text-muted-foreground">
                        This will archive the selected transaction from your active history (soft-delete — recoverable from Archived).
                    </p>
                    <div className="space-y-2">
                        {a ? (
                            <label className="flex cursor-pointer items-start gap-2">
                                <input
                                    type="radio"
                                    name={`remove-${signal.id}`}
                                    checked={removeId === a.id}
                                    onChange={() => onChangeRemoveId(a.id)}
                                    className="mt-1"
                                />
                                <span>
                                    Archive A — {a.description} · {formatMoney(a.amount)} · {a.transaction_date}
                                </span>
                            </label>
                        ) : null}
                        {b ? (
                            <label className="flex cursor-pointer items-start gap-2">
                                <input
                                    type="radio"
                                    name={`remove-${signal.id}`}
                                    checked={removeId === b.id}
                                    onChange={() => onChangeRemoveId(b.id)}
                                    className="mt-1"
                                />
                                <span>
                                    Archive B — {b.description} · {formatMoney(b.amount)} · {b.transaction_date}
                                    {signal.suggested_remove_transaction_id === b.id ? (
                                        <span className="text-muted-foreground"> (suggested)</span>
                                    ) : null}
                                </span>
                            </label>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="destructive" size="sm" disabled={processing || removeId === null} onClick={onConfirmRemove}>
                            Confirm Remove
                        </Button>
                        <Button type="button" variant="outline" size="sm" disabled={processing} onClick={onCancelRemove}>
                            Cancel
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="mt-4 flex flex-wrap gap-2">
                    <Button type="button" size="sm" disabled={processing} onClick={onKeepBoth}>
                        Keep Both
                    </Button>
                    <Button type="button" variant="destructive" size="sm" disabled={processing} onClick={onStartRemove}>
                        Remove Duplicate
                    </Button>
                    <Button type="button" variant="outline" size="sm" onClick={onToggleReview}>
                        {expanded ? 'Hide details' : 'Review'}
                    </Button>
                </div>
            )}
        </article>
    );
}

function CompareRow({ label, left, right }: { label: string; left?: string | null; right?: string | null }) {
    return (
        <tr className="border-border/60 border-b last:border-0">
            <td className="text-muted-foreground py-2 pr-3 align-top text-xs font-medium">{label}</td>
            <td className={cn('py-2 pr-3 align-top tabular-nums')}>{left ?? '—'}</td>
            <td className={cn('py-2 align-top tabular-nums')}>{right ?? '—'}</td>
        </tr>
    );
}

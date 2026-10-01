import { RecurringVisual } from '@/components/dashboard/visuals/RecurringVisual';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pause, Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Recurring', href: '/recurring-transactions' }];

type PendingOccurrence = {
    id: number;
    occurrence_date: string;
    status: string;
    status_label: string;
};

type RecurringRow = {
    id: number;
    type: 'income' | 'expense';
    category: { id: number; name: string } | null;
    amount: string;
    description: string | null;
    frequency: string;
    frequency_label: string;
    next_run_date: string;
    is_active: boolean;
    pending_occurrences: PendingOccurrence[];
};

type Props = {
    recurringTransactions: RecurringRow[];
};

export default function RecurringIndex({ recurringTransactions }: Props) {
    const page = usePage<SharedData>();
    const { flash } = page.props;
    const errors = (page.props as SharedData & { errors?: Record<string, string> }).errors ?? {};
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [busyOccurrenceId, setBusyOccurrenceId] = useState<number | null>(null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const labelFor = (item: RecurringRow) => item.description?.trim() || item.category?.name || `recurring ${item.id}`;

    const toggle = (item: RecurringRow) => {
        if (busyId !== null) {
            return;
        }

        setBusyId(item.id);
        router.post(route('recurring-transactions.toggle', item.id), {}, { onFinish: () => setBusyId(null) });
    };

    const destroy = (item: RecurringRow) => {
        if (busyId !== null) {
            return;
        }

        if (!confirm('Delete this recurring rule? Past transactions will be kept.')) {
            return;
        }

        setBusyId(item.id);
        router.delete(route('recurring-transactions.destroy', item.id), {
            onFinish: () => setBusyId(null),
        });
    };

    const approveOccurrence = (occurrenceId: number) => {
        if (busyOccurrenceId !== null) {
            return;
        }
        setBusyOccurrenceId(occurrenceId);
        router.post(route('recurring-occurrences.approve', occurrenceId), {}, { onFinish: () => setBusyOccurrenceId(null) });
    };

    const rejectOccurrence = (occurrenceId: number) => {
        if (busyOccurrenceId !== null) {
            return;
        }
        setBusyOccurrenceId(occurrenceId);
        router.post(route('recurring-occurrences.reject', occurrenceId), {}, { onFinish: () => setBusyOccurrenceId(null) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recurring transactions" />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow="Finance"
                    title="Recurring"
                    description="Allowances, subscriptions, and other repeating entries — payments post only after you approve"
                    visual={<RecurringVisual className="size-14" />}
                    actions={
                        <Button asChild>
                            <Link href={route('recurring-transactions.create')}>
                                <Plus className="mr-2 size-4" aria-hidden />
                                Create
                            </Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errors.amount ? (
                    <div role="alert" className="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                        {errors.amount}
                    </div>
                ) : null}
                {errors.occurrence ? (
                    <div role="alert" className="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                        {errors.occurrence}
                    </div>
                ) : null}

                {recurringTransactions.length === 0 ? (
                    <FintechEmpty
                        visual={<RecurringVisual className="size-16" />}
                        title="No recurring transactions"
                        description="Create a recurring rule for monthly allowances or subscription charges."
                        action={
                            <Button asChild>
                                <Link href={route('recurring-transactions.create')}>Create</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="cc-table-shell overflow-x-auto">
                            <table className="min-w-[720px]">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th className="text-right">Amount</th>
                                        <th>Frequency</th>
                                        <th>Next date</th>
                                        <th>Status</th>
                                        <th className="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recurringTransactions.map((item) => (
                                        <tr key={item.id}>
                                            <td className="font-medium text-white">{item.description || '—'}</td>
                                            <td className="capitalize">{item.type}</td>
                                            <td>{item.category?.name ?? '—'}</td>
                                            <td className="text-right tabular-nums">{formatMoney(item.amount, { signed: true, type: item.type })}</td>
                                            <td>{item.frequency_label}</td>
                                            <td className="whitespace-nowrap tabular-nums">{item.next_run_date}</td>
                                            <td>
                                                <Badge variant={item.is_active ? 'default' : 'secondary'}>
                                                    {item.is_active ? 'Active' : 'Paused'}
                                                </Badge>
                                            </td>
                                            <td>
                                                <div className="flex justify-end gap-1">
                                                    <Button variant="ghost" size="icon" className="cc-icon-btn" asChild>
                                                        <Link
                                                            href={route('recurring-transactions.edit', item.id)}
                                                            aria-label={`Edit ${labelFor(item)}`}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        type="button"
                                                        className="cc-icon-btn"
                                                        disabled={busyId !== null}
                                                        aria-busy={busyId === item.id}
                                                        aria-label={item.is_active ? `Pause ${labelFor(item)}` : `Resume ${labelFor(item)}`}
                                                        onClick={() => toggle(item)}
                                                    >
                                                        {item.is_active ? <Pause className="size-4" /> : <Play className="size-4" />}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        type="button"
                                                        className="cc-icon-btn"
                                                        disabled={busyId !== null}
                                                        aria-busy={busyId === item.id}
                                                        aria-label={`Delete ${labelFor(item)}`}
                                                        onClick={() => destroy(item)}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {recurringTransactions.some((item) => item.pending_occurrences?.length) ? (
                            <section aria-labelledby="pending-approvals-heading" className="space-y-3">
                                <h2 id="pending-approvals-heading" className="text-sm font-semibold tracking-wide text-white uppercase">
                                    Pending approvals
                                </h2>
                                <ul className="space-y-2">
                                    {recurringTransactions.flatMap((item) =>
                                        (item.pending_occurrences ?? []).map((occurrence) => (
                                            <li
                                                key={occurrence.id}
                                                className="border-border flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="space-y-1">
                                                    <p className="font-medium text-white">
                                                        {labelFor(item)} · {formatMoney(item.amount, { signed: true, type: item.type })}
                                                    </p>
                                                    <p className="cc-text-secondary text-xs">
                                                        Scheduled {occurrence.occurrence_date} · {occurrence.status_label}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={busyOccurrenceId !== null}
                                                        aria-busy={busyOccurrenceId === occurrence.id}
                                                        onClick={() => approveOccurrence(occurrence.id)}
                                                    >
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={busyOccurrenceId !== null}
                                                        aria-busy={busyOccurrenceId === occurrence.id}
                                                        onClick={() => rejectOccurrence(occurrence.id)}
                                                    >
                                                        Reject
                                                    </Button>
                                                </div>
                                            </li>
                                        )),
                                    )}
                                </ul>
                            </section>
                        ) : null}
                    </div>
                )}
            </FintechPage>
        </AppLayout>
    );
}

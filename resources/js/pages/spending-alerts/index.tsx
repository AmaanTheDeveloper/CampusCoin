import { SignalActions } from '@/components/dashboard/intelligence-summary';
import { ClearSignalVisual, SpendingAlertVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { FintechFlash, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { IntelligenceSignal, SpendingAlertsPayload } from '@/types/intelligence';
import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Spending Alerts', href: '/spending-alerts' },
];

type Props = {
    alerts: SpendingAlertsPayload | null;
    error: string | null;
};

export default function SpendingAlertsIndex({ alerts, error }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Spending Alerts" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Attention"
                    title="Spending Alerts"
                    description="Unusual expenses compared with your own recent category averages — explainable and deterministic, not AI scoring."
                    visual={<SpendingAlertVisual className="size-14" />}
                    actions={alerts ? <Badge variant="secondary">{alerts.open_count} unread</Badge> : null}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Spending alerts unavailable</p>
                        <p className="mt-1 text-[#b8b8b8]">{error}</p>
                    </div>
                ) : null}

                {alerts ? (
                    <>
                        <p className="cc-text-tertiary text-xs">{alerts.disclaimer}</p>
                        <p className="cc-text-tertiary text-xs">
                            Flagged when an expense is at least {alerts.detection.multiplier}× your category average from the last{' '}
                            {alerts.detection.lookback_days} days (needs ≥{alerts.detection.min_observations} prior expenses, amount ≥ Rs.{' '}
                            {alerts.detection.absolute_floor}).
                        </p>

                        {alerts.unread.length === 0 && alerts.earlier.length === 0 ? (
                            <FintechPanel className="cc-elev-2 py-10 text-center">
                                <div className="mx-auto flex justify-center">
                                    <ClearSignalVisual className="size-16" />
                                </div>
                                <h2 className="mt-4 text-lg font-semibold text-white">No unusual spending detected</h2>
                                <p className="cc-text-secondary mx-auto mt-2 max-w-md text-sm">
                                    You&apos;re all clear. We&apos;ll flag expenses that look unusually large versus your recent category average.
                                </p>
                                <Button asChild variant="outline" className="mt-6">
                                    <Link href={route('transactions.index')}>View transactions</Link>
                                </Button>
                            </FintechPanel>
                        ) : (
                            <>
                                <AlertGroup
                                    title="Unread"
                                    empty="You're all clear. No unusual spending has been detected recently."
                                    signals={alerts.unread}
                                />
                                <AlertGroup title="Earlier" empty="No reviewed or dismissed alerts yet." signals={alerts.earlier} />
                            </>
                        )}
                    </>
                ) : null}
            </FintechPage>
        </AppLayout>
    );
}

function AlertGroup({ title, empty, signals }: { title: string; empty: string; signals: IntelligenceSignal[] }) {
    return (
        <FintechPanel aria-labelledby={`alerts-${title}`}>
            <h2 id={`alerts-${title}`} className="text-lg font-semibold text-white">
                {title}
            </h2>
            {signals.length === 0 ? (
                <div className="mt-4 flex items-start gap-3">
                    <ClearSignalVisual className="size-12" />
                    <p className="text-sm text-[#8a8a8a]">{empty}</p>
                </div>
            ) : (
                <ul className="mt-4 space-y-3">
                    {signals.map((signal) => (
                        <li key={signal.id}>
                            <AlertCard signal={signal} />
                        </li>
                    ))}
                </ul>
            )}
        </FintechPanel>
    );
}

function AlertCard({ signal }: { signal: IntelligenceSignal }) {
    const category = signal.explanation?.category_name ?? signal.transaction?.category ?? 'this category';
    const amount = signal.explanation?.amount ?? signal.transaction?.amount;
    const ratio = signal.explanation?.deviation_ratio;

    return (
        <article className="cc-row rounded-xl border border-white/8 bg-white/[0.025] px-4 py-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <SpendingAlertVisual className="size-8" />
                        <h3 className="text-sm font-semibold text-white">Unusual Expense Detected</h3>
                        <Badge variant="outline" className="capitalize">
                            {signal.severity}
                        </Badge>
                    </div>
                    {amount ? (
                        <p className="text-sm font-medium text-[#f3f3f3]">
                            {formatMoney(amount)} · {category}
                        </p>
                    ) : null}
                    {ratio ? <p className="text-xs text-[#8a8a8a]">{ratio}× your recent average</p> : null}
                    <p className="text-sm text-[#a0a0a0]">{signal.reason}</p>
                    {signal.detected_at ? <p className="text-xs text-[#737373] tabular-nums">{formatDetectedAt(signal.detected_at)}</p> : null}
                </div>
                <div className="flex flex-col gap-2 sm:items-end">
                    {signal.transaction ? (
                        <Button type="button" size="sm" variant="outline" asChild>
                            <Link href={route('transactions.show', signal.transaction.id)}>View transaction</Link>
                        </Button>
                    ) : null}
                    {signal.status === 'open' ? <SignalActions signalId={signal.id} /> : null}
                </div>
            </div>

            {signal.explanation?.baseline_average ? (
                <dl className="mt-3 grid gap-2 border-t border-white/8 pt-3 text-sm sm:grid-cols-2">
                    <Detail label="Transaction" value={amount ? formatMoney(amount) : '—'} />
                    <Detail label="Category" value={category} />
                    <Detail label="Your recent average" value={formatMoney(signal.explanation.baseline_average)} />
                    <Detail label="Difference" value={signal.explanation.difference ? formatMoney(signal.explanation.difference) : '—'} />
                    <Detail label="Compared with average" value={ratio ? `${ratio}×` : '—'} />
                    <Detail
                        label="Based on"
                        value={
                            signal.explanation.observations
                                ? `${signal.explanation.observations} prior expenses${
                                      signal.explanation.lookback_days ? ` · ${signal.explanation.lookback_days} days` : ''
                                  }`
                                : '—'
                        }
                    />
                </dl>
            ) : null}
        </article>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-[#8a8a8a]">{label}</dt>
            <dd className="font-medium text-[#f3f3f3] tabular-nums">{value}</dd>
        </div>
    );
}

function formatDetectedAt(value: string): string {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

import { FintechCard } from '@/components/dashboard/fintech-card';
import { SpendingAlertVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { Badge } from '@/components/ui/badge';
import { formatMoney } from '@/lib/money';
import type { IntelligenceSignal } from '@/types/intelligence';
import { Link } from '@inertiajs/react';

/**
 * Compact Spending Alerts teaser — consumes existing intelligence.large_signals.
 */
export function SpendingAlertSection({ signals }: { signals: IntelligenceSignal[] }) {
    const open = signals.filter((s) => s.status === 'open' || s.is_unread);
    const latest = open[0] ?? signals[0] ?? null;
    const count = open.length > 0 ? open.length : signals.length;

    return (
        <section aria-labelledby="spending-alert-heading" className="h-full min-w-0">
            <Link href={route('spending-alerts.index')} className="block h-full min-w-0 focus-visible:outline-none">
                <FintechCard elevation="primary" className="group flex h-full min-w-0 flex-col p-5">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="cc-label">{count > 1 ? 'Spending Alerts' : 'Spending Alert'}</p>
                            <h2 id="spending-alert-heading" className="mt-2 text-xl font-semibold break-words text-white">
                                {latest ? 'Unusual expense detected' : 'All clear'}
                            </h2>
                        </div>
                        {count > 0 ? (
                            <Badge className="shrink-0 border-transparent bg-[#E94F09]/20 text-[#ffb38a]">{count} open</Badge>
                        ) : (
                            <Badge variant="outline" className="cc-text-tertiary shrink-0 border-white/10 bg-white/5">
                                Clear
                            </Badge>
                        )}
                    </div>

                    <div className="mt-4">
                        <SpendingAlertVisual />
                    </div>

                    {!latest ? (
                        <p className="cc-text-secondary mt-4 text-sm">No unusual spending detected.</p>
                    ) : (
                        <div className="mt-4 space-y-2">
                            {count > 1 ? <p className="cc-text-tertiary text-sm">{count} unusual spending alerts — latest below</p> : null}
                            <p className="text-2xl font-semibold text-white tabular-nums">
                                {latest.transaction ? formatMoney(latest.transaction.amount) : formatMoney(latest.explanation?.amount ?? '0')}
                            </p>
                            <p className="cc-text-secondary text-sm">
                                {latest.transaction?.category ?? latest.explanation?.category_name ?? 'Expense'}
                            </p>
                            {latest.explanation?.deviation_ratio != null ? (
                                <p className="text-sm text-[#ffb38a]">
                                    {Number(latest.explanation.deviation_ratio).toFixed(1)}× your recent category average
                                </p>
                            ) : (
                                <p className="cc-text-secondary text-sm">{latest.reason}</p>
                            )}
                        </div>
                    )}

                    <p className="mt-auto pt-4 text-sm font-medium text-[#E94F09]">
                        View Spending Alerts <span className="cc-cta-arrow">→</span>
                    </p>
                </FintechCard>
            </Link>
        </section>
    );
}

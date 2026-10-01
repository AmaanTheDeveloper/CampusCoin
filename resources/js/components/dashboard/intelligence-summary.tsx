import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import type { DashboardIntelligence, IntelligenceForecast, IntelligenceSignal } from '@/types/intelligence';
import { Link, router } from '@inertiajs/react';
import { Brain } from 'lucide-react';

export function IntelligenceSummarySection({ panel }: { panel: DashboardIntelligence }) {
    return (
        <section className="border-border bg-card rounded-xl border p-4 shadow-sm" aria-labelledby="intelligence-heading">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">System intelligence</p>
                    <h2 id="intelligence-heading" className="text-lg font-semibold">
                        Forecast & review signals
                    </h2>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Deterministic estimates and review flags from your own history — nothing is changed automatically.
                    </p>
                </div>
                <Button type="button" size="sm" variant="outline" asChild>
                    <Link href={route('intelligence.index')}>
                        <Brain className="mr-2 size-4" aria-hidden />
                        Open intelligence
                    </Link>
                </Button>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <ForecastCompact forecast={panel.forecast} />
                <SignalList title="Unusual amounts" empty="No unusual transactions detected." signals={panel.large_signals} />
                <SignalList title="Possible duplicates" empty="No possible duplicates detected." signals={panel.duplicate_signals} />
            </div>

            {(panel.recent_viewed.length > 0 || panel.recent_edited.length > 0) && (
                <div className="border-border mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2">
                    <ActivityList title="Recently viewed" items={panel.recent_viewed} />
                    <ActivityList title="Recently edited" items={panel.recent_edited} />
                </div>
            )}
        </section>
    );
}

function ForecastCompact({ forecast }: { forecast: IntelligenceForecast }) {
    if (forecast.empty_message || forecast.estimated_expense === null) {
        return (
            <div>
                <h3 className="text-sm font-medium">Next month forecast</h3>
                <p className="text-muted-foreground mt-2 text-sm">{forecast.empty_message ?? 'Forecast unavailable.'}</p>
            </div>
        );
    }

    return (
        <div>
            <h3 className="text-sm font-medium">Next month forecast</h3>
            <p className="text-muted-foreground mt-1 text-xs">{forecast.target_label}</p>
            <dl className="mt-3 space-y-1 text-sm">
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">Estimated income</dt>
                    <dd className="font-medium tabular-nums">{formatMoney(forecast.estimated_income!)}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">Estimated expenses</dt>
                    <dd className="font-medium tabular-nums">{formatMoney(forecast.estimated_expense)}</dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-muted-foreground">Estimated balance</dt>
                    <dd className="font-medium tabular-nums">{formatMoney(forecast.estimated_balance!, { signed: true })}</dd>
                </div>
            </dl>
            <Badge variant="secondary" className="mt-3">
                {forecast.confidence_label}
            </Badge>
        </div>
    );
}

function SignalList({ title, empty, signals }: { title: string; empty: string; signals: IntelligenceSignal[] }) {
    return (
        <div>
            <h3 className="text-sm font-medium">{title}</h3>
            {signals.length === 0 ? (
                <p className="text-muted-foreground mt-2 text-sm">{empty}</p>
            ) : (
                <ul className="mt-2 space-y-2">
                    {signals.slice(0, 3).map((signal) => (
                        <li key={signal.id} className="border-border bg-muted/30 rounded-lg border px-3 py-2 text-sm">
                            <p className="font-medium">{signal.signal_label}</p>
                            <p className="text-muted-foreground mt-1 text-xs">{signal.reason}</p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function ActivityList({ title, items }: { title: string; items: DashboardIntelligence['recent_viewed'] }) {
    if (items.length === 0) {
        return (
            <div>
                <h3 className="text-sm font-medium">{title}</h3>
                <p className="text-muted-foreground mt-2 text-sm">No recent activity yet.</p>
            </div>
        );
    }

    return (
        <div>
            <h3 className="text-sm font-medium">{title}</h3>
            <ul className="mt-2 space-y-1 text-sm">
                {items.map((item) => (
                    <li key={item.id}>
                        <Link
                            href={route('transactions.edit', item.transaction_id)}
                            className="hover:text-primary underline-offset-2 hover:underline"
                        >
                            {item.description}
                            {item.category ? ` — ${item.category}` : ''}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function SignalActions({ signalId }: { signalId: number }) {
    return (
        <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => router.post(route('intelligence.signals.review', signalId))}>
                Mark reviewed
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => router.post(route('intelligence.signals.dismiss', signalId))}>
                Dismiss
            </Button>
        </div>
    );
}

import { CampusCoinAssistant, type AssistantMeta } from '@/components/dashboard/campus-coin-assistant';
import { SignalActions } from '@/components/dashboard/intelligence-summary';
import { ChartEmptyVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { InsightStarVisual } from '@/components/dashboard/visuals/InsightStarVisual';
import { NextMonthForecastVisual } from '@/components/dashboard/visuals/NextMonthForecastVisual';
import { FintechEmpty, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import type { IntelligencePayload, IntelligenceSignal } from '@/types/intelligence';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Intelligence', href: '/intelligence' },
];

type SuggestedPlanRow = {
    category: string;
    amount: string;
    reason: string;
};

type SuggestedPlan = {
    transaction_id: number;
    amount: string;
    plan: SuggestedPlanRow[];
    total: string;
    basis: string[];
    has_history: boolean;
    explanation: string;
    why: string;
    plan_source: string;
} | null;

type Props = {
    intelligence: IntelligencePayload | null;
    assistant: AssistantMeta;
    suggested_plan: SuggestedPlan;
    error: string | null;
};

export default function IntelligenceIndex({ intelligence, assistant, suggested_plan, error }: Props) {
    const hasAnySignals = Boolean(intelligence && intelligence.signals.length > 0);
    const forecastEmpty = Boolean(intelligence && (intelligence.forecast.empty_message || intelligence.forecast.estimated_expense === null));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Intelligence" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Intelligence"
                    title="System intelligence"
                    description="Upcoming-month estimates, unusually large amounts, possible duplicates, and recent activity — all from your data. Review only; Campus Coin never auto-changes transactions."
                    visual={<InsightStarVisual variant="review" className="size-14" />}
                />

                {suggested_plan ? <SuggestedPlanPanel plan={suggested_plan} /> : null}

                <CampusCoinAssistant assistant={assistant} />

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Intelligence unavailable</p>
                        <p className="cc-text-secondary mt-1">{error}</p>
                    </div>
                ) : null}

                {intelligence ? (
                    <>
                        <ForecastSection forecast={intelligence.forecast} />
                        {!hasAnySignals && forecastEmpty ? (
                            <FintechEmpty
                                visual={<ChartEmptyVisual className="size-14" />}
                                title="Not enough data yet"
                                description="Track more financial activity to unlock richer intelligence signals."
                                action={
                                    <Button asChild>
                                        <Link href={route('transactions.index')}>View Transactions</Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <>
                                <SignalsSection
                                    title="Unusual transactions"
                                    empty="No unusual transactions detected."
                                    signals={intelligence.signals.filter((s) => s.signal_type === 'large_transaction')}
                                />
                                <SignalsSection
                                    title="Possible duplicates"
                                    empty="No possible duplicates detected."
                                    signals={intelligence.signals.filter((s) => s.signal_type === 'duplicate_transaction')}
                                    showPeer
                                />
                            </>
                        )}
                        <ActivitySection activity={intelligence.activity} />
                    </>
                ) : null}
            </FintechPage>
        </AppLayout>
    );
}

function SuggestedPlanPanel({ plan }: { plan: NonNullable<SuggestedPlan> }) {
    const sourceLabel = plan.plan_source === 'planner_with_ai' ? 'AI-assisted explanation' : 'Planner-based suggestion';

    return (
        <FintechPanel aria-label="Suggested income plan">
            <FintechSectionHeader title="Suggested plan" meta={sourceLabel} />
            <p className="cc-text-secondary mt-1 text-sm">Income {formatMoney(plan.amount)}</p>
            <p className="cc-text-secondary mt-3 text-sm leading-relaxed">{plan.explanation}</p>
            {plan.plan.length > 0 ? (
                <ul className="mt-4 space-y-2" aria-label="Allocation rows">
                    {plan.plan.map((row) => (
                        <li
                            key={row.category}
                            className="border-border/60 flex items-center justify-between gap-3 border-b py-2 text-sm last:border-0"
                        >
                            <div>
                                <p className="font-medium">{row.category}</p>
                                <p className="cc-text-secondary text-xs">{row.reason}</p>
                            </div>
                            <span className="font-semibold tabular-nums">{formatMoney(row.amount)}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
            <p className="mt-3 text-sm font-medium">Total {formatMoney(plan.total)}</p>
            <div className="mt-4 space-y-1">
                <p className="text-sm font-semibold">Why this plan?</p>
                <p className="cc-text-secondary text-sm leading-relaxed">{plan.why}</p>
            </div>
            <Badge className="mt-4" variant="secondary">
                You stay in control — nothing was changed automatically
            </Badge>
        </FintechPanel>
    );
}

function ForecastSection({ forecast }: { forecast: IntelligencePayload['forecast'] }) {
    return (
        <FintechPanel className="cc-elev-2" aria-labelledby="forecast-heading">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 items-start gap-3">
                    <NextMonthForecastVisual className="size-12 shrink-0" />
                    <div className="min-w-0">
                        <h2 id="forecast-heading" className="text-lg font-semibold text-white">
                            Next month forecast
                        </h2>
                        <p className="cc-text-tertiary text-xs">{forecast.target_label ?? 'Upcoming month'}</p>
                    </div>
                </div>
                <Badge variant="secondary">{forecast.confidence_label}</Badge>
            </div>

            {forecast.empty_message || forecast.estimated_expense === null ? (
                <p className="cc-text-secondary text-sm">{forecast.empty_message ?? 'Forecast unavailable.'}</p>
            ) : (
                <>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Metric label="Estimated income" value={formatMoney(forecast.estimated_income!)} />
                        <Metric label="Estimated expenses" value={formatMoney(forecast.estimated_expense)} />
                        <Metric label="Estimated balance" value={formatMoney(forecast.estimated_balance!, { signed: true })} />
                    </div>
                    {forecast.method ? <p className="cc-text-tertiary mt-4 text-xs">{forecast.method}</p> : null}
                    {forecast.categories.length > 0 ? (
                        <div className="mt-4">
                            <h3 className="text-sm font-medium text-white">Projected spending by category</h3>
                            <ul className="mt-2 space-y-1 text-sm">
                                {forecast.categories.map((category) => (
                                    <li key={`${category.category_id ?? 'none'}-${category.name}`} className="flex justify-between gap-3">
                                        <span className="text-[#cfcfcf]">{category.name}</span>
                                        <span className="text-white tabular-nums">{formatMoney(category.estimated_amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </>
            )}
            <p className="cc-text-tertiary mt-4 text-xs">{forecast.disclaimer}</p>
        </FintechPanel>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border border-white/8 bg-white/[0.03] px-3 py-3">
            <p className="cc-text-tertiary text-xs">{label}</p>
            <p className="mt-1 text-lg font-semibold text-white tabular-nums">{value}</p>
        </div>
    );
}

function SignalsSection({
    title,
    empty,
    signals,
    showPeer = false,
}: {
    title: string;
    empty: string;
    signals: IntelligenceSignal[];
    showPeer?: boolean;
}) {
    return (
        <FintechPanel className="cc-elev-2">
            <FintechSectionHeader title={title} />
            {signals.length === 0 ? (
                <p className="cc-text-secondary mt-2 text-sm">{empty}</p>
            ) : (
                <ul className="mt-4 space-y-3">
                    {signals.map((signal) => (
                        <li key={signal.id} className="rounded-lg border border-white/8 bg-white/[0.02] p-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary">{signal.signal_label}</Badge>
                                <Badge variant="outline">{signal.severity}</Badge>
                            </div>
                            <p className="mt-2 text-sm text-[#e8e8e8]">{signal.reason}</p>
                            {signal.transaction ? (
                                <p className="cc-text-tertiary mt-2 text-xs">
                                    {signal.transaction.transaction_date} · {signal.transaction.description} ·{' '}
                                    {formatMoney(signal.transaction.amount)}
                                    {signal.transaction.category ? ` · ${signal.transaction.category}` : ''}
                                </p>
                            ) : null}
                            {showPeer && signal.comparison_transaction ? (
                                <p className="cc-text-tertiary mt-1 text-xs">
                                    Similar to: {signal.comparison_transaction.transaction_date} · {signal.comparison_transaction.description} ·{' '}
                                    {formatMoney(signal.comparison_transaction.amount)}
                                </p>
                            ) : null}
                            <div className="mt-3 flex flex-wrap gap-2">
                                {signal.transaction ? (
                                    <Button type="button" size="sm" variant="outline" asChild>
                                        <Link href={route('transactions.edit', signal.transaction.id)}>Open transaction</Link>
                                    </Button>
                                ) : null}
                                <SignalActions signalId={signal.id} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </FintechPanel>
    );
}

function ActivitySection({ activity }: { activity: IntelligencePayload['activity'] }) {
    return (
        <section className="grid gap-4 lg:grid-cols-2" aria-labelledby="activity-heading">
            <h2 id="activity-heading" className="sr-only">
                Recent activity
            </h2>
            <ActivityColumn title="Recently viewed" items={activity.viewed} />
            <ActivityColumn title="Recently edited" items={activity.edited} />
        </section>
    );
}

function ActivityColumn({ title, items }: { title: string; items: IntelligencePayload['activity']['viewed'] }) {
    return (
        <FintechPanel className="cc-elev-2">
            <h3 className="font-semibold text-white">{title}</h3>
            {items.length === 0 ? (
                <p className="cc-text-secondary mt-2 text-sm">No recent activity yet.</p>
            ) : (
                <ul className="mt-3 space-y-2 text-sm">
                    {items.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <Link
                                    href={route('transactions.edit', item.transaction_id)}
                                    className="font-medium text-white underline-offset-2 hover:underline"
                                >
                                    {item.description}
                                </Link>
                                <p className="cc-text-tertiary text-xs">
                                    {item.category ?? 'Uncategorized'} · {item.transaction_date}
                                </p>
                            </div>
                            <span className="text-white tabular-nums">{formatMoney(item.amount)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </FintechPanel>
    );
}

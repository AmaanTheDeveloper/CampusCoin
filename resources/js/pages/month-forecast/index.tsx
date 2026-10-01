import { MonthForecastPaceChart } from '@/components/dashboard/charts';
import { ForecastVisual, WarningVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { MonthForecastPanel } from '@/types/dashboard';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Month Forecast', href: '/month-forecast' },
];

type Props = {
    forecast: MonthForecastPanel | null;
    error: string | null;
};

export default function MonthForecastPage({ forecast, error }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Month Forecast" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Projection"
                    title="Month Forecast"
                    description="Where will I end this month? Based on your real spending pace — not AI predictions."
                    visual={<ForecastVisual className="h-12 w-24" />}
                    actions={
                        <Button type="button" variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Back to dashboard</Link>
                        </Button>
                    }
                />

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Forecast unavailable</p>
                        <p className="mt-1 text-[#b8b8b8]">{error}</p>
                    </div>
                ) : null}

                {forecast ? <ForecastContent panel={forecast} /> : null}
            </FintechPage>
        </AppLayout>
    );
}

function ForecastContent({ panel }: { panel: MonthForecastPanel }) {
    const savingsNegative = panel.forecast.expected_savings !== null && Number(panel.forecast.expected_savings) < 0;

    return (
        <>
            <FintechPanel>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold text-white">{panel.period.label}</h2>
                        <p className="text-xs text-[#8a8a8a]">
                            Day {panel.period.days_elapsed} of {panel.period.days_in_month} · {panel.period.days_remaining} day
                            {panel.period.days_remaining === 1 ? '' : 's'} remaining
                        </p>
                    </div>
                    <Badge variant="outline">{panel.status_label}</Badge>
                </div>

                {!panel.available ? (
                    <p className="mt-4 text-sm text-[#8a8a8a]">{panel.status_message}</p>
                ) : (
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat label="Spent so far" value={formatMoney(panel.actual.expenses)} />
                        <Stat label="Daily spending pace" value={formatMoney(panel.pace.daily_expenses!)} hint="Per day" />
                        <Stat label="Estimated expenses" value={formatMoney(panel.forecast.expenses!)} />
                        <Stat
                            label="Expected savings"
                            value={formatMoney(panel.forecast.expected_savings!, { signed: true })}
                            tone={savingsNegative ? 'negative' : 'default'}
                        />
                    </div>
                )}

                {panel.budget_alert?.available ? (
                    <p className="mt-4 flex items-start gap-2 text-sm text-[#f3f3f3]">
                        <WarningVisual className="mt-0.5 size-5 shrink-0" />
                        <span>{panel.budget_alert.message}</span>
                    </p>
                ) : null}

                {panel.early_month_note ? <p className="mt-3 text-xs text-[#8a8a8a]">{panel.early_month_note}</p> : null}
            </FintechPanel>

            {panel.available && panel.series.length > 0 ? (
                <FintechPanel aria-labelledby="pace-chart-heading">
                    <h2 id="pace-chart-heading" className="font-semibold text-white">
                        Actual → Current pace → Forecast
                    </h2>
                    <div className="mt-4">
                        <MonthForecastPaceChart series={panel.series} />
                    </div>
                </FintechPanel>
            ) : null}

            <FintechPanel className="space-y-3">
                <h2 className="font-semibold text-white">How this is calculated</h2>
                <p className="text-sm text-[#cfcfcf]">{panel.explanation}</p>
                {panel.savings_explanation ? <p className="text-sm text-[#8a8a8a]">{panel.savings_explanation}</p> : null}
                <p className="text-xs text-[#737373]">{panel.formula}</p>
                <p className="text-xs text-[#737373]">{panel.disclaimer}</p>
            </FintechPanel>

            {panel.category_forecasts.length > 0 ? (
                <FintechPanel>
                    <h2 className="font-semibold text-white">Budget projections</h2>
                    <p className="mt-1 text-xs text-[#8a8a8a]">Largest projected overrun is highlighted on the dashboard card.</p>
                    <ul className="mt-4 space-y-3">
                        {panel.category_forecasts.map((row) => (
                            <li key={`${row.budget_id}-${row.category_name}`} className="rounded-lg border border-white/8 px-3 py-3 text-sm">
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <p className="font-medium text-white">{row.category_name}</p>
                                    <Badge variant="outline" className="capitalize">
                                        {row.status.replaceAll('_', ' ')}
                                    </Badge>
                                </div>
                                <dl className="mt-2 grid gap-1 text-[#8a8a8a] sm:grid-cols-3">
                                    <div>
                                        Spent {formatMoney(row.spent)} / {formatMoney(row.limit)}
                                    </div>
                                    <div>Forecast {formatMoney(row.forecast)}</div>
                                    <div>
                                        {row.already_over
                                            ? `Already over by ${formatMoney(row.current_overrun)}`
                                            : row.projected_over
                                              ? `May exceed by ${formatMoney(row.projected_overrun)}`
                                              : 'Within budget at this pace'}
                                    </div>
                                </dl>
                            </li>
                        ))}
                    </ul>
                </FintechPanel>
            ) : null}
        </>
    );
}

function Stat({ label, value, hint, tone = 'default' }: { label: string; value: string; hint?: string; tone?: 'default' | 'negative' }) {
    return (
        <div className="rounded-lg border border-white/8 bg-white/[0.03] px-3 py-2">
            <p className="cc-label">{label}</p>
            <p className={cn('mt-1 text-base font-semibold text-white tabular-nums', tone === 'negative' && 'text-red-400')}>{value}</p>
            {hint ? <p className="text-xs text-[#8a8a8a]">{hint}</p> : null}
        </div>
    );
}

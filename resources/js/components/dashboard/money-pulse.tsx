import { FintechCard } from '@/components/dashboard/fintech-card';
import { MoneyPulseVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import { cn } from '@/lib/utils';
import type { MoneyPulseMetric, MoneyPulsePanel, MoneyPulseTrend } from '@/types/dashboard';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

export function MoneyPulseSection({ panel }: { panel: MoneyPulsePanel }) {
    const [open, setOpen] = useState(false);
    const insufficient = panel.overall.status === 'insufficient';
    const animatedScore = useCountUp(panel.overall.score ?? 0, 900, panel.overall.score !== null);

    return (
        <>
            <section aria-labelledby="money-pulse-heading" className="min-w-0">
                <FintechCard elevation="hero" className="min-w-0 p-5 md:p-6">
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="group w-full text-left focus-visible:ring-2 focus-visible:ring-[#E94F09] focus-visible:outline-none"
                        aria-haspopup="dialog"
                        aria-expanded={open}
                    >
                        <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0 flex-1">
                                <p className="cc-label">Your Money Pulse</p>
                                <h2 id="money-pulse-heading" className="mt-2 text-2xl font-semibold tracking-tight text-white md:text-3xl">
                                    Financial health
                                </h2>
                                <div className="mt-4 flex flex-wrap items-end gap-4">
                                    {!insufficient && panel.overall.score !== null ? (
                                        <p className="text-5xl font-semibold tracking-tight text-white tabular-nums md:text-6xl">
                                            {Math.round(animatedScore)}
                                            <span className="text-[#E94F09]">%</span>
                                        </p>
                                    ) : null}
                                    <StatusBadge panel={panel} />
                                </div>
                                {insufficient ? (
                                    <p className="cc-text-secondary mt-3 text-sm">
                                        {panel.overall.explanation || 'Add a few transactions to see your financial health.'}
                                    </p>
                                ) : (
                                    <dl className="mt-5 grid gap-3 sm:grid-cols-3">
                                        <PulseStat label="Savings consistency" value={panel.metrics.savings_consistency.summary} />
                                        <PulseStat label="Budget discipline" value={panel.metrics.budget_discipline.summary} />
                                        <PulseStat
                                            label="Spending trend"
                                            value={panel.spending_trend.available ? (panel.spending_trend.label ?? '—') : 'Still developing'}
                                        />
                                    </dl>
                                )}
                                <p className="mt-4 text-sm font-medium text-[#E94F09]">
                                    Open breakdown <span className="cc-cta-arrow">→</span>
                                </p>
                            </div>
                            <div className="flex items-center gap-3 self-center md:self-auto">
                                <MoneyPulseVisual score={panel.overall.score} indicator={panel.overall.indicator} className="cc-hero-loop" />
                                <ChevronRight
                                    className="cc-text-secondary hidden size-5 transition-transform group-hover:translate-x-0.5 md:block"
                                    aria-hidden
                                />
                            </div>
                        </div>
                    </button>
                </FintechCard>
            </section>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="cc-dark-shell max-h-[90vh] overflow-y-auto border-[#242424] bg-[#0a0a0a] text-[#f3f3f3] sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Financial Health Breakdown</DialogTitle>
                        <DialogDescription className="cc-text-secondary">{panel.disclaimer}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <StatusBadge panel={panel} />
                                {panel.overall.score !== null ? (
                                    <p className="text-sm font-semibold tabular-nums">Overall {panel.overall.score}</p>
                                ) : null}
                            </div>
                            <p className="cc-text-secondary mt-2 text-sm">{panel.overall.explanation}</p>
                            <p className="cc-text-secondary mt-1 text-xs">{panel.overall.formula}</p>
                        </div>

                        <MetricDetail metric={panel.metrics.savings_consistency} />
                        <MetricDetail metric={panel.metrics.budget_discipline} />
                        <MetricDetail metric={panel.metrics.spending_stability} />
                        <MetricDetail metric={panel.metrics.goal_progress} />
                        <TrendDetail trend={panel.spending_trend} />

                        {!panel.metrics.goal_progress.available ? (
                            <Button type="button" variant="outline" size="sm" asChild>
                                <Link href={route('profile.edit')}>Set a savings goal</Link>
                            </Button>
                        ) : null}

                        {!panel.metrics.budget_discipline.available ? (
                            <Button type="button" variant="outline" size="sm" asChild>
                                <Link href={route('budgets.create')}>Create a budget</Link>
                            </Button>
                        ) : null}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusBadge({ panel }: { panel: MoneyPulsePanel }) {
    const tone =
        panel.overall.indicator === 'green'
            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
            : panel.overall.indicator === 'amber'
              ? 'border-amber-500/30 bg-amber-500/10 text-amber-200'
              : panel.overall.indicator === 'red'
                ? 'border-red-500/30 bg-red-500/10 text-red-300'
                : 'border-white/10 bg-white/5 cc-text-tertiary';

    return (
        <Badge variant="outline" className={cn('font-medium', tone)}>
            <span
                className={cn(
                    'mr-1.5 inline-block size-1.5 rounded-full',
                    panel.overall.indicator === 'green' && 'bg-emerald-400',
                    panel.overall.indicator === 'amber' && 'bg-amber-400',
                    panel.overall.indicator === 'red' && 'bg-red-400',
                    !['green', 'amber', 'red'].includes(panel.overall.indicator) && 'bg-[#8a8a8a]',
                )}
                aria-hidden
            />
            {panel.overall.status_label}
        </Badge>
    );
}

function PulseStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border border-white/8 bg-black/30 px-3 py-2.5">
            <dt className="cc-label">{label}</dt>
            <dd className="mt-1 text-base font-semibold text-white tabular-nums">{value}</dd>
        </div>
    );
}

function MetricDetail({ metric }: { metric: MoneyPulseMetric }) {
    return (
        <article className="rounded-lg border border-white/10 px-3 py-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-sm font-semibold">{metric.label}</h3>
                <p className="text-sm font-medium tabular-nums">{metric.available && metric.score !== null ? `${metric.score}%` : metric.summary}</p>
            </div>
            <p className="cc-text-secondary mt-2 text-sm">{metric.explanation}</p>
            <p className="cc-text-secondary mt-2 text-xs">{metric.formula}</p>
        </article>
    );
}

function TrendDetail({ trend }: { trend: MoneyPulseTrend }) {
    return (
        <article className="rounded-lg border border-white/10 px-3 py-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="text-sm font-semibold">Spending trend</h3>
                <p className="text-sm font-medium">{trend.available ? (trend.label ?? '—') : 'Still developing'}</p>
            </div>
            <p className="cc-text-secondary mt-2 text-sm">{trend.explanation}</p>
            <p className="cc-text-secondary mt-2 text-xs">{trend.formula}</p>
        </article>
    );
}

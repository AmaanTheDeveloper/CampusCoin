import { FintechCard } from '@/components/dashboard/fintech-card';
import { ForecastVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { Badge } from '@/components/ui/badge';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { MonthForecastPanel } from '@/types/dashboard';
import { Link } from '@inertiajs/react';

export function MonthForecastSection({ panel }: { panel: MonthForecastPanel }) {
    const expenseTarget = panel.forecast.expenses !== null ? Number(panel.forecast.expenses) : 0;
    const savingsTarget = panel.forecast.expected_savings !== null ? Number(panel.forecast.expected_savings) : 0;
    const expenseAnim = useCountUp(expenseTarget, 850, panel.available && panel.forecast.expenses !== null);
    const savingsAnim = useCountUp(savingsTarget, 850, panel.available && panel.forecast.expected_savings !== null);
    const savingsTone = panel.forecast.expected_savings !== null && Number(panel.forecast.expected_savings) < 0 ? 'text-red-300' : 'text-white';

    return (
        <section aria-labelledby="month-forecast-heading" className="h-full min-w-0">
            <Link href={route('month-forecast.index')} className="block h-full min-w-0 focus-visible:outline-none">
                <FintechCard elevation="hero" className="group flex h-full min-w-0 flex-col p-5">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="cc-label">Month Forecast</p>
                            <h2 id="month-forecast-heading" className="mt-2 text-xl font-semibold break-words text-white">
                                Where this month ends
                            </h2>
                        </div>
                        <StatusBadge panel={panel} />
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-3">
                        <ForecastVisual className="cc-hero-loop" />
                    </div>

                    {!panel.available ? (
                        <p className="cc-text-secondary mt-4 text-sm">{panel.status_message}</p>
                    ) : (
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-white/8 bg-black/25 px-3 py-3">
                                <p className="cc-label">Estimated expenses</p>
                                <p className="mt-1 text-xl font-semibold text-white tabular-nums">{formatMoney(expenseAnim)}</p>
                            </div>
                            <div className="rounded-xl border border-white/8 bg-black/25 px-3 py-3">
                                <p className="cc-label">Expected savings</p>
                                <p className={cn('mt-1 text-xl font-semibold tabular-nums', savingsTone)}>
                                    {formatMoney(savingsAnim, { signed: true })}
                                </p>
                            </div>
                        </div>
                    )}

                    {panel.budget_alert?.available ? (
                        <p className="mt-3 text-sm text-[#ffb38a]">
                            <span
                                className={cn(
                                    'mr-1.5 inline-block size-1.5 rounded-full',
                                    panel.budget_alert.kind === 'already_over' ? 'bg-orange-400' : 'bg-amber-400',
                                )}
                                aria-hidden
                            />
                            {panel.budget_alert.message}
                        </p>
                    ) : panel.available ? (
                        <p className="cc-text-secondary mt-3 text-sm">{panel.status_message}</p>
                    ) : null}

                    {panel.early_month_note ? <p className="cc-text-secondary mt-2 text-xs">{panel.early_month_note}</p> : null}

                    <p className="mt-auto pt-4 text-sm font-medium text-[#E94F09]">
                        View forecast <span className="cc-cta-arrow">→</span>
                    </p>
                </FintechCard>
            </Link>
        </section>
    );
}

function StatusBadge({ panel }: { panel: MonthForecastPanel }) {
    const tone =
        panel.status_indicator === 'green'
            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
            : panel.status_indicator === 'amber'
              ? 'border-amber-500/30 bg-amber-500/10 text-amber-200'
              : panel.status_indicator === 'orange'
                ? 'border-orange-500/30 bg-orange-500/10 text-orange-200'
                : 'border-white/10 bg-white/5 cc-text-tertiary';

    return (
        <Badge variant="outline" className={cn('shrink-0 font-medium', tone)}>
            {panel.status_label}
        </Badge>
    );
}

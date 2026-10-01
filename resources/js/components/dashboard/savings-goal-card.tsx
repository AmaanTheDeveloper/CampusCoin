import { FintechCard } from '@/components/dashboard/fintech-card';
import { EmptyOrbitVisual, SavingsGoalVisual } from '@/components/dashboard/visuals/fintech-visuals';
import { Badge } from '@/components/ui/badge';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import { formatMoney } from '@/lib/money';
import type { SavingsGoalDetail, SavingsGoalTrackerPanel } from '@/types/dashboard';
import { Link } from '@inertiajs/react';

export function SavingsGoalSection({ panel }: { panel: SavingsGoalTrackerPanel }) {
    if (!panel.has_goal || !panel.goal) {
        return <SavingsGoalEmpty panel={panel} />;
    }

    return <SavingsGoalActive goal={panel.goal} />;
}

function SavingsGoalEmpty({ panel }: { panel: SavingsGoalTrackerPanel }) {
    return (
        <section aria-labelledby="savings-goal-heading" className="h-full min-w-0">
            <Link href={route('savings-goal.index')} className="block h-full min-w-0 focus-visible:outline-none">
                <FintechCard elevation="hero" className="group flex h-full min-w-0 flex-col items-start border-dashed p-5">
                    <p className="cc-label">Savings Goal</p>
                    <h2 id="savings-goal-heading" className="mt-2 text-xl font-semibold text-white">
                        Set a Savings Goal
                    </h2>
                    <div className="mt-4">
                        <EmptyOrbitVisual />
                    </div>
                    <p className="cc-text-secondary mt-3 text-sm">{panel.empty_message}</p>
                    <p className="mt-auto pt-4 text-sm font-medium text-[#E94F09]">
                        {panel.cta ?? 'Create Goal'} <span className="cc-cta-arrow">→</span>
                    </p>
                </FintechCard>
            </Link>
        </section>
    );
}

function SavingsGoalActive({ goal }: { goal: SavingsGoalDetail }) {
    const progressAnim = useCountUp(goal.progress_percent, 900);

    return (
        <section aria-labelledby="savings-goal-heading" className="h-full min-w-0">
            <Link href={route('savings-goal.index')} className="block h-full min-w-0 focus-visible:outline-none">
                <FintechCard elevation="hero" className="group flex h-full min-w-0 flex-col p-5">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="cc-label">Savings Goal</p>
                            <h2 id="savings-goal-heading" className="mt-2 text-xl font-semibold break-words text-white">
                                {goal.name}
                            </h2>
                        </div>
                        <Badge variant="outline" className="cc-text-tertiary shrink-0 border-white/10 bg-white/5">
                            {goal.status_label}
                        </Badge>
                    </div>

                    <div className="mt-4 flex items-center gap-4">
                        <SavingsGoalVisual percent={goal.progress_percent} className="cc-hero-loop" />
                        <div>
                            <p className="text-2xl font-semibold text-white tabular-nums">
                                {Math.round(progressAnim)}
                                <span className="text-[#E94F09]">%</span>
                            </p>
                            <p className="cc-text-secondary mt-1 text-sm tabular-nums">
                                {formatMoney(goal.current_amount)} of {formatMoney(goal.target_amount)}
                            </p>
                        </div>
                    </div>

                    <div className="mt-4">
                        <div className="cc-progress-track">
                            <div className="cc-progress-fill" style={{ width: `${goal.progress_percent}%` }} />
                            <div className="cc-progress-shine" aria-hidden />
                        </div>
                    </div>

                    {goal.is_completed ? (
                        <p className="mt-3 text-sm font-medium text-white">Goal reached</p>
                    ) : (
                        <>
                            <p className="cc-text-tertiary mt-3 text-sm">
                                <span className="font-medium text-white tabular-nums">{formatMoney(goal.remaining_amount)}</span> remaining
                            </p>
                            {goal.completion_estimate ? (
                                <p className="cc-text-secondary mt-1 text-sm">
                                    Estimated completion: <span className="font-medium text-white">{goal.completion_estimate.label}</span>
                                </p>
                            ) : (
                                <p className="cc-text-secondary mt-1 text-sm">
                                    {goal.savings_rate.explanation ?? 'Estimated completion unavailable.'}
                                </p>
                            )}
                        </>
                    )}

                    <p className="mt-auto pt-4 text-sm font-medium text-[#E94F09]">
                        View goal <span className="cc-cta-arrow">→</span>
                    </p>
                </FintechCard>
            </Link>
        </section>
    );
}

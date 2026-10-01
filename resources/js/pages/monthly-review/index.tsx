import { InsightStarVisual } from '@/components/dashboard/visuals/InsightStarVisual';
import { FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Monthly Review', href: '/monthly-review' },
];

type MoneyChange = {
    category_id: number | null;
    name: string;
    current_amount: string;
    previous_amount: string;
    change_amount: string;
    change_percent: number | null;
    direction: 'up' | 'down' | 'new' | string;
    label: string;
    summary: string;
};

type ReviewPayload = {
    month: { key: string; label: string; start: string; end: string; timezone: string };
    navigation: {
        previous_key: string | null;
        next_key: string | null;
        can_go_previous: boolean;
        can_go_next: boolean;
    };
    has_activity: boolean;
    empty_message: string | null;
    summary: {
        income: string;
        expenses: string;
        saved: string;
        savings_rate: number | null;
        transaction_count: number;
    };
    comparison: {
        available: boolean;
        previous_label: string;
        message: string | null;
        expenses_change_percent: number | null;
        income_change_percent: number | null;
        saved_change_percent: number | null;
    };
    top_category: { category_id: number | null; name: string; amount: string; percentage: number | null } | null;
    biggest_change: MoneyChange | null;
    budget_performance: {
        available: boolean;
        total: number;
        within_limit: number;
        exceeded: number;
        label: string;
        items: Array<{
            id: number;
            category_name: string;
            spent: string;
            limit: string;
            status: string;
            status_label: string;
        }>;
    };
    overall_trend: {
        available: boolean;
        direction: string | null;
        change_percent: number | null;
        statement: string;
    };
    insight: {
        available: boolean;
        text: string;
        evidence: Array<{ label: string; value: string }>;
    };
    actions: Array<{ id: string; title: string; reason: string; priority: number }>;
    disclaimer: string;
};

type Props = {
    review: ReviewPayload | null;
    error: string | null;
};

export default function MonthlyReviewPage({ review, error }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={review ? `${review.month.label} Money Review` : 'Monthly Review'} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Monthly Money Review"
                    title={review ? `${review.month.label} Money Review` : 'Monthly Review'}
                    description="Your financial story for the month — what changed, and what to try next."
                    visual={<InsightStarVisual variant="review" className="size-14" />}
                    actions={
                        <Button type="button" variant="outline" size="sm" asChild>
                            <Link href={route('dashboard')}>Back to dashboard</Link>
                        </Button>
                    }
                />

                {error ? (
                    <div role="alert" className="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
                        <p className="font-medium">Monthly review unavailable</p>
                        <p className="mt-1 text-[#b8b8b8]">{error}</p>
                    </div>
                ) : null}

                {review ? <ReviewContent review={review} /> : null}
            </FintechPage>
        </AppLayout>
    );
}

function ReviewContent({ review }: { review: ReviewPayload }) {
    const goMonth = (key: string | null) => {
        if (!key) return;
        router.get(route('monthly-review.index'), { month: key }, { preserveState: true, preserveScroll: true });
    };

    const primaryInsightLabel = review.biggest_change?.label ?? review.top_category?.name ?? null;
    const primaryInsightSupport = review.biggest_change?.summary ?? null;

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        disabled={!review.navigation.can_go_previous}
                        onClick={() => goMonth(review.navigation.previous_key)}
                        aria-label="Previous month"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <p className="min-w-[9rem] text-center text-sm font-medium text-white tabular-nums">{review.month.label}</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11"
                        disabled={!review.navigation.can_go_next}
                        onClick={() => goMonth(review.navigation.next_key)}
                        aria-label="Next month"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
                <Badge variant="secondary">{review.has_activity ? `${review.summary.transaction_count} transactions` : 'No activity'}</Badge>
            </div>

            {!review.has_activity ? (
                <FintechPanel className="border-dashed px-6 py-12 text-center">
                    <p className="text-lg font-semibold text-white">No financial activity yet for this month</p>
                    <p className="cc-text-secondary mx-auto mt-2 max-w-md text-sm">{review.empty_message}</p>
                    <Button asChild className="mt-6">
                        <Link href={route('transactions.create')}>Add a transaction</Link>
                    </Button>
                </FintechPanel>
            ) : (
                <>
                    {/* HERO SUMMARY */}
                    <section className="cc-card-hero p-5 md:p-8" aria-labelledby="glance-heading">
                        <p className="cc-label">Hero summary</p>
                        <h2 id="glance-heading" className="mt-2 text-2xl font-semibold tracking-tight text-white md:text-3xl">
                            {review.month.label} at a glance
                        </h2>
                        <div className="mt-6 grid gap-5 sm:grid-cols-3">
                            <Metric
                                label="Income"
                                value={formatMoney(review.summary.income)}
                                hint={changeHint(review.comparison.income_change_percent, review.comparison)}
                            />
                            <Metric
                                label="Expenses"
                                value={formatMoney(review.summary.expenses)}
                                hint={changeHint(review.comparison.expenses_change_percent, review.comparison)}
                            />
                            <Metric
                                label="Saved"
                                value={formatMoney(review.summary.saved, { signed: true })}
                                hint={
                                    review.summary.savings_rate === null
                                        ? 'Unavailable when income is zero'
                                        : `Savings rate ${review.summary.savings_rate.toFixed(1)}%`
                                }
                                emphasize
                            />
                        </div>
                        {!review.comparison.available ? <p className="cc-text-tertiary mt-4 text-xs">{review.comparison.message}</p> : null}
                    </section>

                    {/* WHAT CHANGED — one strong visual insight */}
                    <section className="cc-card p-5 md:p-6" aria-labelledby="changed-heading">
                        <p className="cc-label">What changed?</p>
                        <h2 id="changed-heading" className="mt-2 text-xl font-semibold text-white">
                            {primaryInsightLabel ?? 'Spending held steady'}
                        </h2>
                        {primaryInsightSupport ? (
                            <p className="cc-text-secondary mt-2 max-w-2xl text-sm leading-relaxed">{primaryInsightSupport}</p>
                        ) : null}
                        <div className="mt-5 flex flex-wrap gap-2">
                            {review.top_category ? (
                                <span className="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-[#cfcfcf]">
                                    Top: {review.top_category.name}
                                    {review.top_category.percentage !== null ? ` · ${review.top_category.percentage.toFixed(1)}%` : ''}
                                </span>
                            ) : null}
                            {review.biggest_change?.change_percent !== null && review.biggest_change?.change_percent !== undefined ? (
                                <span className="rounded-full border border-[#E94F09]/30 bg-[#E94F09]/10 px-3 py-1 text-xs text-[#ffb089]">
                                    {review.biggest_change.change_percent > 0 ? '+' : ''}
                                    {review.biggest_change.change_percent.toFixed(1)}% vs prior
                                </span>
                            ) : null}
                            <span className="rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-xs text-[#cfcfcf]">
                                Budgets: {review.budget_performance.label}
                            </span>
                        </div>
                        {review.budget_performance.available && review.budget_performance.items.length > 0 ? (
                            <ul className="mt-4 space-y-2 border-t border-white/8 pt-4 text-sm">
                                {review.budget_performance.items.slice(0, 3).map((item) => (
                                    <li key={item.id} className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="text-[#cfcfcf]">{item.category_name}</span>
                                        <span className="cc-text-tertiary tabular-nums">
                                            {formatMoney(item.spent)} / {formatMoney(item.limit)} · {item.status_label}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </section>

                    {/* WHAT HAPPENED — storytelling centerpiece */}
                    <section className="cc-card-hero relative overflow-hidden p-5 md:p-8" aria-labelledby="happened-heading">
                        <div className="pointer-events-none absolute -top-10 -right-10 size-40 rounded-full bg-[#E94F09]/10 blur-3xl" aria-hidden />
                        <p className="cc-label">What happened?</p>
                        <h2 id="happened-heading" className="mt-3 max-w-3xl text-2xl font-semibold tracking-tight text-white md:text-3xl">
                            {review.insight.text}
                        </h2>
                        {review.insight.evidence.length > 0 ? (
                            <div className="mt-6 flex flex-wrap gap-2">
                                {review.insight.evidence.map((row) => (
                                    <div
                                        key={`${row.label}-${row.value}`}
                                        className="rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm"
                                    >
                                        <p className="cc-text-tertiary text-[10px] tracking-wide uppercase">{row.label}</p>
                                        <p className="mt-0.5 font-medium text-white">{row.value}</p>
                                    </div>
                                ))}
                            </div>
                        ) : null}
                        <p className="cc-text-secondary mt-5 text-sm">{review.overall_trend.statement}</p>
                    </section>

                    {/* ACTIONS */}
                    <section aria-labelledby="actions-heading">
                        <div className="mb-4 flex items-end justify-between gap-3">
                            <div>
                                <p className="cc-label">Next month</p>
                                <h2 id="actions-heading" className="mt-1 text-xl font-semibold text-white">
                                    Your 3 actions
                                </h2>
                            </div>
                        </div>
                        {review.actions.length === 0 ? (
                            <FintechPanel>
                                <p className="cc-text-secondary text-sm">
                                    No strong action items this month — keep tracking and check back after more activity.
                                </p>
                            </FintechPanel>
                        ) : (
                            <ol className="grid gap-3 md:grid-cols-3">
                                {review.actions.map((action, index) => (
                                    <li key={action.id} className="cc-card cc-card-interactive p-4">
                                        <p className="text-xs font-medium text-[#E94F09]">Action {index + 1}</p>
                                        <p className="mt-2 text-sm font-semibold text-white">{action.title}</p>
                                        <p className="cc-text-secondary mt-2 text-sm leading-relaxed">{action.reason}</p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </>
            )}

            <p className="cc-text-tertiary text-xs">{review.disclaimer}</p>
        </>
    );
}

function Metric({ label, value, hint, emphasize = false }: { label: string; value: string; hint?: string | null; emphasize?: boolean }) {
    return (
        <div>
            <p className="cc-label">{label}</p>
            <p className={cn('mt-2 text-2xl font-semibold tabular-nums md:text-3xl', emphasize ? 'text-[#E94F09]' : 'text-white')}>{value}</p>
            {hint ? <p className="cc-text-tertiary mt-1 text-xs">{hint}</p> : null}
        </div>
    );
}

function changeHint(percent: number | null, comparison: ReviewPayload['comparison']): string | null {
    if (!comparison.available) {
        return null;
    }
    if (percent === null) {
        return `vs ${comparison.previous_label}`;
    }
    const arrow = percent > 0 ? '↑' : percent < 0 ? '↓' : '→';
    return `${arrow} ${Math.abs(percent).toFixed(1)}% vs ${comparison.previous_label}`;
}

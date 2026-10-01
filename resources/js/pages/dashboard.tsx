import { BudgetProgress, BudgetStatusBadge } from '@/components/budgets/budget-progress';
import { CategoryBreakdownChart, DailyExpenseChart, MonthlyOverviewChart } from '@/components/dashboard/charts';
import { FintechCard } from '@/components/dashboard/fintech-card';
import { MoneyPulseSection } from '@/components/dashboard/money-pulse';
import { MonthForecastSection } from '@/components/dashboard/month-forecast-card';
import { SavingsGoalSection } from '@/components/dashboard/savings-goal-card';
import { SpendingAlertSection } from '@/components/dashboard/spending-alert-card';
import { EmptyStateCoinVisual } from '@/components/dashboard/visuals/EmptyStateCoinVisual';
import { InsightStarVisual } from '@/components/dashboard/visuals/InsightStarVisual';
import { SmartScanTrigger } from '@/components/document-scan/smart-scan-trigger';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import AppLayout from '@/layouts/app-layout';
import { formatSavingsRateDisplay, isMeaningfulSavingsRate } from '@/lib/format-savings-rate';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { DashboardData, MonthlyInsightPanel } from '@/types/dashboard';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Upload } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Props = {
    dashboard: DashboardData | null;
    error: string | null;
};

export default function Dashboard({ dashboard, error }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div
                className={cn(
                    'cc-dashboard relative flex min-w-0 flex-col gap-5 p-4 pb-6 md:gap-6 md:p-6 md:pb-8',
                    !error && dashboard && !dashboard.has_transactions && dashboard.budgets.length === 0 && 'min-h-full flex-1',
                )}
            >
                {error ? <ErrorState message={error} /> : null}
                {!error && dashboard && !dashboard.has_transactions && dashboard.budgets.length === 0 ? (
                    <EmptyState name={dashboard.greeting.name} />
                ) : null}
                {!error && dashboard && (dashboard.has_transactions || dashboard.budgets.length > 0) ? <DashboardContent data={dashboard} /> : null}
            </div>
        </AppLayout>
    );
}

function ErrorState({ message }: { message: string }) {
    return (
        <div role="alert" className="cc-card rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-6 text-sm text-red-100">
            <p className="font-medium">Dashboard unavailable</p>
            <p className="cc-text-tertiary mt-1">{message}</p>
            <Button asChild className="cc-dash-btn-primary mt-4" variant="default">
                <Link href={route('dashboard')}>Try again</Link>
            </Button>
        </div>
    );
}

function EmptyState({ name }: { name: string }) {
    return (
        <section className="cc-card flex flex-1 flex-col items-center justify-center border-dashed px-6 py-16 text-center">
            <EmptyStateCoinVisual />
            <p className="cc-label mt-4">Dashboard ready</p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Your financial command center</h1>
            <p className="cc-text-secondary mt-3 max-w-lg text-sm">
                Welcome, {name}. Add income or expenses to unlock Money Pulse, forecasts, goals, and alerts.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
                <Button asChild className="cc-dash-btn-primary">
                    <Link href={route('transactions.create', { type: 'income' })}>
                        <ArrowDownLeft className="mr-2 size-4" aria-hidden />
                        Add income
                    </Link>
                </Button>
                <Button asChild variant="secondary" className="cc-dash-btn-secondary">
                    <Link href={route('transactions.create', { type: 'expense' })}>
                        <ArrowUpRight className="mr-2 size-4" aria-hidden />
                        Add expense
                    </Link>
                </Button>
                <SmartScanTrigger className="cc-dash-btn-ghost" />
                <Button asChild variant="outline" className="cc-dash-btn-ghost">
                    <Link href={route('transactions.import.create')}>
                        <Upload className="mr-2 size-4" aria-hidden />
                        Import CSV
                    </Link>
                </Button>
            </div>
        </section>
    );
}

function DashboardContent({ data }: { data: DashboardData }) {
    const greetingEyebrow =
        data.greeting.period === 'morning' ? 'Good morning' : data.greeting.period === 'afternoon' ? 'Good afternoon' : 'Good evening';
    const largeSignals = data.intelligence?.large_signals ?? [];

    return (
        <div className="cc-stagger flex min-w-0 flex-col gap-5 md:gap-6">
            <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p className="cc-label">{greetingEyebrow}</p>
                    <h1 className="mt-2 text-4xl font-semibold tracking-tight text-white md:text-5xl">{data.greeting.name}</h1>
                    <p className="cc-text-secondary mt-2 text-sm">
                        {data.period.label}
                        <span className="mx-2 text-[#242424]">·</span>
                        Here&apos;s your financial snapshot.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild className="cc-dash-btn-primary">
                        <Link href={route('transactions.create', { type: 'income' })}>
                            <ArrowDownLeft className="mr-2 size-4" aria-hidden />
                            Income
                        </Link>
                    </Button>
                    <Button asChild variant="secondary" className="cc-dash-btn-secondary">
                        <Link href={route('transactions.create', { type: 'expense' })}>
                            <ArrowUpRight className="mr-2 size-4" aria-hidden />
                            Expense
                        </Link>
                    </Button>
                    <SmartScanTrigger />
                </div>
            </header>

            <SummaryPanel data={data} />

            <div className="grid min-w-0 gap-4 xl:grid-cols-[1.35fr_1fr]">
                {data.money_pulse ? <MoneyPulseSection panel={data.money_pulse} /> : null}
                {data.month_forecast ? <MonthForecastSection panel={data.month_forecast} /> : null}
            </div>

            <div className="grid min-w-0 gap-4 lg:grid-cols-[1.2fr_0.9fr]">
                <FintechCard elevation="utility" className="min-w-0 p-5" interactive={false}>
                    <h3 className="text-lg font-semibold text-white">Spending overview</h3>
                    <p className="cc-text-secondary mb-3 text-xs">Daily expenses through today</p>
                    <DailyExpenseChart points={data.daily_expense_trend} />
                </FintechCard>
                {data.savings_goal_tracker ? <SavingsGoalSection panel={data.savings_goal_tracker} /> : null}
            </div>

            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <SpendingAlertSection signals={largeSignals} />
                {data.monthly_insight ? <MonthlyInsightCompact panel={data.monthly_insight} /> : null}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <FintechCard elevation="utility" className="p-5" interactive={false}>
                    <h3 className="text-lg font-semibold text-white">Category breakdown</h3>
                    <p className="cc-text-secondary mb-3 text-xs">Current-month expenses by category</p>
                    <CategoryBreakdownChart items={data.category_breakdown} />
                </FintechCard>
                <FintechCard elevation="utility" className="p-5" interactive={false}>
                    <h3 className="text-lg font-semibold text-white">Six-month overview</h3>
                    <p className="cc-text-secondary mb-3 text-xs">Income vs expenses</p>
                    <MonthlyOverviewChart points={data.monthly_trend} />
                </FintechCard>
            </div>

            <div className="grid gap-4 lg:grid-cols-[1.35fr_1fr]">
                <FintechCard elevation="utility" className="p-5" interactive={false}>
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h3 className="text-lg font-semibold text-white">Recent transactions</h3>
                        <Link href={route('transactions.index')} className="cc-dash-link text-sm font-medium">
                            View all <span className="cc-cta-arrow">→</span>
                        </Link>
                    </div>
                    <RecentTransactions data={data} />
                </FintechCard>

                <FintechCard elevation="utility" className="p-5" interactive={false}>
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h3 className="text-lg font-semibold text-white">Budget progress</h3>
                        <Link href={route('budgets.index')} className="cc-dash-link text-sm font-medium">
                            Manage <span className="cc-cta-arrow">→</span>
                        </Link>
                    </div>
                    <BudgetPanel data={data} />
                </FintechCard>
            </div>

            {(data.top_category || data.largest_expense) && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {data.top_category ? (
                        <FintechCard elevation="utility" className="p-4" interactive={false}>
                            <p className="cc-label">Top category</p>
                            <p className="mt-2 text-lg font-semibold text-white">{data.top_category.name}</p>
                            <p className="cc-text-secondary mt-1 text-sm tabular-nums">
                                {formatMoney(data.top_category.amount)}
                                {data.top_category.percentage !== null ? ` · ${data.top_category.percentage.toFixed(1)}%` : ''}
                            </p>
                        </FintechCard>
                    ) : null}
                    {data.largest_expense ? (
                        <FintechCard elevation="utility" className="p-4" interactive={false}>
                            <p className="cc-label">Largest expense</p>
                            <p className="mt-2 text-lg font-semibold text-white">{data.largest_expense.description}</p>
                            <p className="cc-text-secondary mt-1 text-sm">
                                {formatMoney(data.largest_expense.amount)} · {data.largest_expense.category}
                            </p>
                        </FintechCard>
                    ) : null}
                </div>
            )}

            <section aria-labelledby="review-cta-heading">
                <Link href={route('monthly-review.index')} className="group block focus-visible:outline-none">
                    <FintechCard elevation="hero" className="relative overflow-hidden px-6 py-10 text-center">
                        <div
                            className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(233,79,9,0.18),transparent_65%)]"
                            aria-hidden
                        />
                        <div className="relative flex flex-col items-center gap-3">
                            <span className="cc-insight-star-hover">
                                <InsightStarVisual variant="review" />
                            </span>
                            <p className="cc-label">Monthly Review</p>
                            <h2 id="review-cta-heading" className="text-2xl font-semibold tracking-tight text-white md:text-3xl">
                                Review My Month
                            </h2>
                            <p className="cc-text-secondary max-w-md text-sm">See your financial story, key changes, and next actions.</p>
                            <span className="cc-dash-btn-primary mt-2 inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold">
                                Review My Month <span className="cc-cta-arrow">→</span>
                            </span>
                        </div>
                    </FintechCard>
                </Link>
            </section>
        </div>
    );
}

function SummaryPanel({ data }: { data: DashboardData }) {
    const availableBalance = useCountUp(Number(data.summary.available_balance), 850);
    const income = useCountUp(Number(data.summary.income), 850);
    const expense = useCountUp(Number(data.summary.expense), 850);
    const rate = data.summary.savings_rate;
    const rateMeaningful = isMeaningfulSavingsRate(rate);
    const rateAnim = useCountUp(rateMeaningful ? rate : 0, 850, rateMeaningful);
    const savingsDisplay = formatSavingsRateDisplay(rate, rateMeaningful ? rateAnim : undefined);

    return (
        <section aria-labelledby="summary-heading">
            <h2 id="summary-heading" className="sr-only">
                Current month summary
            </h2>
            <FintechCard elevation="utility" className="grid grid-cols-2 gap-px overflow-hidden p-0 md:grid-cols-4" interactive={false}>
                <SummaryMetric
                    label="Balance"
                    value={Math.abs(Number(data.summary.available_balance)) < 0.005 ? formatMoney(0) : formatMoney(availableBalance)}
                    hint="Available funds (all income − all expenses)"
                    accent
                />
                <SummaryMetric label="Income" value={formatMoney(income)} hint="Current month" />
                <SummaryMetric label="Expenses" value={formatMoney(expense)} hint="Current month" />
                <SummaryMetric label="Savings" value={savingsDisplay.value} hint={savingsDisplay.hint} />
            </FintechCard>
        </section>
    );
}

function SummaryMetric({ label, value, hint, accent = false }: { label: string; value: string; hint: string; accent?: boolean }) {
    return (
        <div className="cc-summary-metric px-4 py-5 md:px-5">
            <p className="cc-label">{label}</p>
            <p
                className={cn(
                    'cc-summary-metric__value mt-2 text-2xl font-semibold tracking-tight tabular-nums md:text-3xl',
                    accent && 'cc-summary-metric__value--accent',
                )}
            >
                {value}
            </p>
            <p className="cc-summary-metric__hint mt-1 text-xs">{hint}</p>
        </div>
    );
}

function RecentTransactions({ data }: { data: DashboardData }) {
    if (data.recent_transactions.length === 0) {
        return <p className="cc-text-secondary text-sm">No transactions yet.</p>;
    }

    return (
        <ul className="space-y-1">
            {data.recent_transactions.map((transaction) => (
                <li key={transaction.id} className="cc-tx-row group/row -mx-1">
                    <div className="min-w-0">
                        <p className="truncate text-[0.95rem] font-medium text-white">{transaction.description || 'No description'}</p>
                        <p className="cc-text-secondary mt-0.5 text-xs">
                            {transaction.category?.name ?? 'Uncategorized'}
                            <span className="mx-1.5 text-[#3a3a3a]">·</span>
                            {transaction.transaction_date}
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <p
                            className={cn(
                                'text-sm font-semibold tabular-nums transition-transform group-hover/row:scale-[1.03]',
                                transaction.type === 'income' ? 'text-emerald-300' : 'text-white',
                            )}
                        >
                            {formatMoney(transaction.amount, { signed: true, type: transaction.type })}
                        </p>
                        <span className="cc-cta-arrow cc-text-tertiary opacity-0 transition group-hover/row:opacity-100" aria-hidden>
                            →
                        </span>
                    </div>
                </li>
            ))}
        </ul>
    );
}

function BudgetPanel({ data }: { data: DashboardData }) {
    if (data.budgets.length === 0) {
        return (
            <div className="space-y-3">
                <p className="cc-text-secondary text-sm">You haven&apos;t set a monthly budget yet.</p>
                <Button asChild size="sm" className="cc-dash-btn-primary">
                    <Link href={route('budgets.create')}>Set your first budget</Link>
                </Button>
            </div>
        );
    }

    return (
        <ul className="space-y-4">
            {data.budgets.map((budget) => (
                <li key={budget.id} className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-medium text-white">{budget.category.name}</p>
                        <BudgetStatusBadge status={budget.status} label={budget.status_label} />
                    </div>
                    <p className="cc-text-secondary text-xs tabular-nums">
                        {formatMoney(budget.spent)} / {formatMoney(budget.limit)} · {budget.percentage.toFixed(0)}%
                    </p>
                    <div className="cc-progress-track">
                        <div className="cc-progress-fill" style={{ width: `${Math.min(100, Math.max(0, budget.percentage))}%` }} />
                        <div className="cc-progress-shine" aria-hidden />
                    </div>
                    <div className="sr-only">
                        <BudgetProgress categoryName={budget.category.name} percentage={budget.percentage} status={budget.status} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function MonthlyInsightCompact({ panel }: { panel: MonthlyInsightPanel }) {
    const display = panel.insight ?? {
        ...panel.factual_fallback,
        id: 0,
        period: panel.period,
        period_label: panel.period_label,
        generated_source: 'fallback' as const,
        status: 'ready',
        generated_at: null,
        is_bookmarked: false,
    };

    return (
        <section aria-labelledby="monthly-insight-heading" className="h-full min-w-0">
            <Link href={route('insights.index')} className="block h-full min-w-0 focus-visible:outline-none">
                <FintechCard elevation="primary" className="group flex h-full min-w-0 flex-col p-5">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="flex min-w-0 items-start gap-3">
                            <InsightStarVisual variant="insight" />
                            <div className="min-w-0">
                                <p className="cc-label">Monthly insight</p>
                                <h2 id="monthly-insight-heading" className="mt-2 text-xl font-semibold break-words text-white">
                                    {display.headline}
                                </h2>
                            </div>
                        </div>
                        <Badge variant="outline" className="cc-text-tertiary shrink-0 border-white/10 bg-white/5">
                            {display.generated_source === 'ai' ? 'AI + facts' : 'Factual'}
                        </Badge>
                    </div>
                    <p className="cc-text-secondary mt-3 line-clamp-3 text-sm">{display.summary}</p>
                    <p className="cc-dash-link mt-auto pt-4 text-sm font-medium">
                        View insight <span className="cc-cta-arrow">→</span>
                    </p>
                </FintechCard>
            </Link>
        </section>
    );
}

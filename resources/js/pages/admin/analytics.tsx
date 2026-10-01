import { AdminChartEmpty, moneySeriesHasActivity, seriesHasActivity } from '@/components/admin/admin-chart-empty';
import { AdminAnalyticsVisual, AdminCategoriesVisual, AdminUsersVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechPage, FintechPageHeader, FintechSectionHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import AdminLayout from '@/layouts/admin-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { AdminCharts, AdminPeriod, KpiComparison, PeriodOption } from '@/types/admin';
import { Head, router } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Analytics', href: '/admin/analytics' },
];

type Props = {
    period: AdminPeriod;
    period_options: PeriodOption[];
    summary: {
        total_students: number;
        active_students: number;
        transactions: number;
        income_total: string;
        expense_total: string;
    };
    charts: AdminCharts;
    comparisons: {
        students: KpiComparison;
    };
    active_definition: string;
};

function useIsDarkTheme() {
    const [isDark, setIsDark] = useState(() => (typeof document === 'undefined' ? true : document.documentElement.classList.contains('dark')));
    useEffect(() => {
        const root = document.documentElement;
        const sync = () => setIsDark(root.classList.contains('dark'));
        sync();
        const observer = new MutationObserver(sync);
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);
    return isDark;
}

function useChartTheme() {
    const isDark = useIsDarkTheme();
    return {
        axisTick: { fontSize: 11, fill: isDark ? '#8a8a8a' : '#5c5c5c' },
        gridStroke: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.08)',
    };
}

function CountTooltip({ active, payload, label }: { active?: boolean; payload?: Array<{ name?: string; value?: number }>; label?: string }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="border-border bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-lg">
            {label ? <p className="mb-1 font-medium">{label}</p> : null}
            {payload.map((entry) => (
                <p key={`${entry.name}-${entry.value}`} className="tabular-nums">
                    <span className="text-muted-foreground">{entry.name}: </span>
                    {entry.value ?? 0}
                </p>
            ))}
        </div>
    );
}

function MoneyTooltip({ active, payload, label }: { active?: boolean; payload?: Array<{ name?: string; value?: number }>; label?: string }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="border-border bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-lg">
            {label ? <p className="mb-1 font-medium">{label}</p> : null}
            {payload.map((entry) => (
                <p key={`${entry.name}-${entry.value}`} className="tabular-nums">
                    <span className="text-muted-foreground">{entry.name}: </span>
                    {formatMoney(Number(entry.value ?? 0))}
                </p>
            ))}
        </div>
    );
}

function SummaryStat({ label, value }: { label: string; value: number }) {
    const animated = useCountUp(value);
    return (
        <div className="cc-admin-kpi">
            <p className="cc-admin-kpi__label">{label}</p>
            <p className="cc-admin-kpi__value">{Math.round(animated).toLocaleString()}</p>
        </div>
    );
}

export default function AdminAnalytics({ period, period_options, summary, charts, comparisons, active_definition }: Props) {
    const theme = useChartTheme();
    const growthGrad = useId();

    const setPeriod = (next: AdminPeriod) => {
        router.get(route('admin.analytics'), { period: next }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Analytics" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Insights"
                    title="Analytics"
                    description="Deeper platform trends across students, transactions, and category usage."
                    visual={<AdminAnalyticsVisual />}
                    actions={
                        <div className="cc-admin-period" role="group" aria-label="Period">
                            {period_options.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    className={cn('cc-admin-period__btn', period === option.value && 'is-active')}
                                    onClick={() => setPeriod(option.value)}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    }
                />

                <p className="text-muted-foreground text-xs">{active_definition}</p>

                {comparisons.students.percent !== null && comparisons.students.direction ? (
                    <p className="text-sm">
                        Student growth vs prior period:{' '}
                        <span className={comparisons.students.direction === 'up' ? 'text-[#FF8B5C]' : 'text-muted-foreground'}>
                            {comparisons.students.direction === 'up' ? '+' : '−'}
                            {Math.abs(comparisons.students.percent)}% {comparisons.students.label}
                        </span>
                    </p>
                ) : null}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryStat label="Total students" value={summary.total_students} />
                    <SummaryStat label="Active students" value={summary.active_students} />
                    <SummaryStat label="Transactions" value={summary.transactions} />
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Income total</p>
                        <p className="cc-admin-kpi__value" style={{ fontSize: '1.25rem' }}>
                            {formatMoney(Number(summary.income_total))}
                        </p>
                    </div>
                    <div className="cc-admin-kpi">
                        <p className="cc-admin-kpi__label">Expense total</p>
                        <p className="cc-admin-kpi__value" style={{ fontSize: '1.25rem' }}>
                            {formatMoney(Number(summary.expense_total))}
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="User growth" />
                        <div className="mt-3 min-h-64">
                            {!seriesHasActivity(charts.user_growth) ? (
                                <AdminChartEmpty
                                    visual={<AdminUsersVisual className="size-12" />}
                                    title="No student growth yet"
                                    description="New student registrations for this period will appear here."
                                />
                            ) : (
                                <div className="h-64">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart data={charts.user_growth} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                            <defs>
                                                <linearGradient id={growthGrad} x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#E94F09" stopOpacity={0.42} />
                                                    <stop offset="95%" stopColor="#E94F09" stopOpacity={0.02} />
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                                            <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                                            <YAxis tick={theme.axisTick} width={36} axisLine={false} tickLine={false} allowDecimals={false} />
                                            <Tooltip content={<CountTooltip />} />
                                            <Area
                                                type="monotone"
                                                dataKey="count"
                                                name="Students"
                                                stroke="#E94F09"
                                                fill={`url(#${growthGrad})`}
                                                strokeWidth={2.2}
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="Transaction activity" />
                        <div className="mt-3 min-h-64">
                            {!seriesHasActivity(charts.transaction_activity) ? (
                                <AdminChartEmpty
                                    visual={<AdminAnalyticsVisual className="size-12" />}
                                    title="No transaction activity yet"
                                    description="System transaction activity will appear here once students begin recording transactions."
                                />
                            ) : (
                                <div className="h-64">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={charts.transaction_activity} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                            <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                                            <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                                            <YAxis tick={theme.axisTick} width={36} axisLine={false} tickLine={false} allowDecimals={false} />
                                            <Tooltip content={<CountTooltip />} />
                                            <Bar dataKey="count" name="Transactions" fill="#E94F09" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader
                            title="Income vs expense"
                            meta={`${charts.income_vs_expense.income_count} income · ${charts.income_vs_expense.expense_count} expense records`}
                        />
                        <div className="mt-3 min-h-64">
                            {!moneySeriesHasActivity(
                                charts.income_vs_expense.points,
                                charts.income_vs_expense.income_total,
                                charts.income_vs_expense.expense_total,
                            ) ? (
                                <AdminChartEmpty
                                    visual={<AdminAnalyticsVisual className="size-12" />}
                                    title="No income or expense totals yet"
                                    description="Platform income and expense totals will appear here once students record transactions."
                                />
                            ) : (
                                <div className="h-64">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={charts.income_vs_expense.points} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                            <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                                            <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                                            <YAxis tick={theme.axisTick} width={48} axisLine={false} tickLine={false} />
                                            <Tooltip content={<MoneyTooltip />} />
                                            <Bar dataKey="income" name="Income" fill="#FF8B5C" radius={[3, 3, 0, 0]} />
                                            <Bar dataKey="expense" name="Expense" fill="#E94F09" radius={[3, 3, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="Category popularity" />
                        <div className="mt-4 space-y-3">
                            {charts.category_popularity.length === 0 ? (
                                <AdminChartEmpty
                                    visual={<AdminCategoriesVisual className="size-12" />}
                                    title="No category usage yet"
                                    description="Category popularity will appear once students start using income and expense categories."
                                />
                            ) : (
                                charts.category_popularity.map((cat) => (
                                    <div key={cat.id} className="space-y-1.5">
                                        <div className="flex items-center justify-between gap-2 text-sm">
                                            <span className="truncate font-medium">
                                                {cat.name}
                                                <Badge variant="outline" className="ml-2 text-[10px] uppercase">
                                                    {cat.type}
                                                </Badge>
                                            </span>
                                            <span className="text-muted-foreground shrink-0 tabular-nums">
                                                {cat.count} · {cat.percent}%
                                            </span>
                                        </div>
                                        <div className="cc-admin-category-bar">
                                            <div className="cc-admin-category-bar__fill" style={{ width: `${Math.min(100, cat.percent)}%` }} />
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </FintechPage>
        </AdminLayout>
    );
}

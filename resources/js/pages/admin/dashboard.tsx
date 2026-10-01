import { AdminChartEmpty, moneySeriesHasActivity, seriesHasActivity } from '@/components/admin/admin-chart-empty';
import {
    AdminAnalyticsVisual,
    AdminAnnouncementsVisual,
    AdminCategoriesVisual,
    AdminControlCenterVisual,
    AdminIntelligenceVisual,
    AdminSystemClearVisual,
    AdminTipsVisual,
    AdminUsersVisual,
} from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCountUp } from '@/hooks/use-dashboard-motion';
import AdminLayout from '@/layouts/admin-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type {
    AdminActivityItem,
    AdminCharts,
    AdminDashboardPayload,
    AdminKpiCategory,
    AdminKpiNumber,
    AdminPeriod,
    KpiComparison,
} from '@/types/admin';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Lightbulb, Megaphone, Shield, User } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin Dashboard', href: '/admin' }];

type Props = {
    dashboard: AdminDashboardPayload;
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
        surface: isDark ? '#030303' : '#ffffff',
    };
}

function CountTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: Array<{ name?: string; value?: number; color?: string }>;
    label?: string;
}) {
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="border-border bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-lg">
            {label ? <p className="text-foreground mb-1 font-medium">{label}</p> : null}
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
    if (!active || !payload?.length) {
        return null;
    }
    return (
        <div className="border-border bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-lg">
            {label ? <p className="text-foreground mb-1 font-medium">{label}</p> : null}
            {payload.map((entry) => (
                <p key={`${entry.name}-${entry.value}`} className="tabular-nums">
                    <span className="text-muted-foreground">{entry.name}: </span>
                    {formatMoney(Number(entry.value ?? 0))}
                </p>
            ))}
        </div>
    );
}

function ComparisonDelta({ comparison }: { comparison: KpiComparison | null }) {
    if (!comparison || comparison.percent === null || !comparison.direction) {
        return null;
    }
    return (
        <span className={cn(comparison.direction === 'up' ? 'cc-admin-kpi__delta--up' : 'cc-admin-kpi__delta--down')}>
            {comparison.direction === 'up' ? '+' : '−'}
            {Math.abs(comparison.percent)}% {comparison.label}
        </span>
    );
}

function KpiCard({ kpi }: { kpi: AdminKpiNumber }) {
    const animated = useCountUp(kpi.value);
    return (
        <Link href={kpi.href} className="cc-admin-kpi block">
            <p className="cc-admin-kpi__label">{kpi.label}</p>
            <p className="cc-admin-kpi__value">{Math.round(animated).toLocaleString()}</p>
            <p className="cc-admin-kpi__meta">
                <ComparisonDelta comparison={kpi.comparison} />
                {kpi.comparison ? ' · ' : null}
                {kpi.supporting}
            </p>
        </Link>
    );
}

function CategoryKpiCard({ kpi }: { kpi: AdminKpiCategory }) {
    return (
        <Link href={kpi.href} className="cc-admin-kpi block">
            <p className="cc-admin-kpi__label">{kpi.label}</p>
            <p className="cc-admin-kpi__value" style={{ fontSize: '1.25rem' }}>
                {kpi.value}
            </p>
            <p className="cc-admin-kpi__meta">{kpi.supporting}</p>
        </Link>
    );
}

function GrowthChart({ data }: { data: AdminCharts['user_growth'] }) {
    const theme = useChartTheme();
    const gradientId = useId();
    if (!seriesHasActivity(data)) {
        return (
            <AdminChartEmpty
                visual={<AdminUsersVisual className="size-12" />}
                title="No student growth yet"
                description="New student registrations for this period will appear here."
            />
        );
    }
    return (
        <div className="h-56 w-full" role="img" aria-label="User growth chart">
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="5%" stopColor="#E94F09" stopOpacity={0.42} />
                            <stop offset="95%" stopColor="#E94F09" stopOpacity={0.02} />
                        </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                    <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                    <YAxis tick={theme.axisTick} width={36} axisLine={false} tickLine={false} allowDecimals={false} />
                    <Tooltip content={<CountTooltip />} />
                    <Area type="monotone" dataKey="count" name="Students" stroke="#E94F09" fill={`url(#${gradientId})`} strokeWidth={2.2} />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

function ActivityChart({ data }: { data: AdminCharts['transaction_activity'] }) {
    const theme = useChartTheme();
    if (!seriesHasActivity(data)) {
        return (
            <AdminChartEmpty
                visual={<AdminAnalyticsVisual className="size-12" />}
                title="No transaction activity yet"
                description="System transaction activity will appear here once students begin recording transactions."
            />
        );
    }
    return (
        <div className="h-56 w-full" role="img" aria-label="Transaction activity chart">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                    <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                    <YAxis tick={theme.axisTick} width={36} axisLine={false} tickLine={false} allowDecimals={false} />
                    <Tooltip content={<CountTooltip />} />
                    <Bar dataKey="count" name="Transactions" fill="#E94F09" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function IncomeExpenseChart({ data }: { data: AdminCharts['income_vs_expense'] }) {
    const theme = useChartTheme();
    if (!moneySeriesHasActivity(data.points, data.income_total, data.expense_total)) {
        return (
            <AdminChartEmpty
                visual={<AdminAnalyticsVisual className="size-12" />}
                title="No income or expense totals yet"
                description="Platform income and expense totals will appear here once students record transactions."
            />
        );
    }
    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-4 text-sm">
                <p>
                    <span className="text-muted-foreground">Income </span>
                    <span className="font-medium tabular-nums">{formatMoney(Number(data.income_total))}</span>
                </p>
                <p>
                    <span className="text-muted-foreground">Expense </span>
                    <span className="font-medium tabular-nums">{formatMoney(Number(data.expense_total))}</span>
                </p>
            </div>
            <div className="h-56 w-full" role="img" aria-label="Income versus expense chart">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data.points} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                        <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                        <YAxis tick={theme.axisTick} width={48} axisLine={false} tickLine={false} />
                        <Tooltip content={<MoneyTooltip />} />
                        <Bar dataKey="income" name="Income" fill="#FF8B5C" radius={[3, 3, 0, 0]} />
                        <Bar dataKey="expense" name="Expense" fill="#E94F09" radius={[3, 3, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function activityIcon(icon: AdminActivityItem['icon']) {
    switch (icon) {
        case 'shield':
            return Shield;
        case 'user':
            return User;
        case 'megaphone':
            return Megaphone;
        case 'lightbulb':
            return Lightbulb;
    }
}

function quickVisual(href: string) {
    if (href.includes('users')) return <AdminUsersVisual className="size-10" />;
    if (href.includes('categories')) return <AdminCategoriesVisual className="size-10" />;
    if (href.includes('announcements')) return <AdminAnnouncementsVisual className="size-10" />;
    if (href.includes('saving-tips')) return <AdminTipsVisual className="size-10" />;
    if (href.includes('analytics')) return <AdminAnalyticsVisual className="size-10" />;
    if (href.includes('intelligence')) return <AdminIntelligenceVisual className="size-10" />;
    return <AdminControlCenterVisual className="size-10" />;
}

export default function AdminDashboard({ dashboard }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const setPeriod = (period: AdminPeriod) => {
        router.get(route('admin.dashboard'), { period }, { preserveState: true, preserveScroll: true });
    };

    const intel = dashboard.intelligence_summary;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <FintechPage className="cc-admin-page">
                <FintechPageHeader
                    eyebrow="Control center"
                    title="CampusCoin Admin"
                    description="Platform health, student activity, and content operations."
                    visual={<AdminControlCenterVisual />}
                    actions={
                        <div className="cc-admin-period" role="group" aria-label="Period">
                            {dashboard.period_options.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    className={cn('cc-admin-period__btn', dashboard.period === option.value && 'is-active')}
                                    onClick={() => setPeriod(option.value)}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <p className="text-muted-foreground text-xs">{dashboard.active_definition}</p>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <KpiCard kpi={dashboard.kpis.total_students} />
                    <KpiCard kpi={dashboard.kpis.active_students} />
                    <KpiCard kpi={dashboard.kpis.total_transactions} />
                    <KpiCard kpi={dashboard.kpis.income_records} />
                    <KpiCard kpi={dashboard.kpis.expense_records} />
                    <CategoryKpiCard kpi={dashboard.kpis.most_used_category} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="User growth" meta="New student accounts" />
                        <div className="mt-3">
                            <GrowthChart data={dashboard.charts.user_growth} />
                        </div>
                    </div>
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="Transaction activity" meta="Recorded transactions" />
                        <div className="mt-3">
                            <ActivityChart data={dashboard.charts.transaction_activity} />
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="Income vs expense" meta="Platform totals" />
                        <div className="mt-3">
                            <IncomeExpenseChart data={dashboard.charts.income_vs_expense} />
                        </div>
                    </div>
                    <div className="cc-admin-chart-panel">
                        <FintechSectionHeader title="Category popularity" meta="Most used system categories" />
                        <div className="mt-4 space-y-3">
                            {dashboard.charts.category_popularity.length === 0 ? (
                                <AdminChartEmpty
                                    visual={<AdminCategoriesVisual className="size-12" />}
                                    title="No category usage yet"
                                    description="Category popularity will appear once students start using income and expense categories."
                                />
                            ) : (
                                dashboard.charts.category_popularity.map((cat) => (
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

                <div className="grid gap-4 lg:grid-cols-2">
                    <FintechPanel>
                        <FintechSectionHeader title="System clear" meta="Operational alerts" />
                        <div className="mt-3">
                            {dashboard.alerts.length === 0 ? (
                                <FintechEmpty
                                    visual={<AdminSystemClearVisual />}
                                    title="All clear"
                                    description="No operational alerts for this period."
                                />
                            ) : (
                                <ul className="space-y-2">
                                    {dashboard.alerts.map((alert) => (
                                        <li key={alert.id} className={cn('cc-admin-alert', `cc-admin-alert--${alert.severity}`)}>
                                            <p className="text-sm font-medium">{alert.title}</p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">{alert.detail}</p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </FintechPanel>

                    <FintechPanel>
                        <FintechSectionHeader
                            title="AI intelligence"
                            meta={intel.has_data ? 'Suggestion feedback' : 'Awaiting feedback'}
                            action={
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={intel.href}>Open</Link>
                                </Button>
                            }
                        />
                        <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Suggestions</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{intel.suggestions}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Accepted</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{intel.accepted}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Corrected</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{intel.corrected}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Accept rate</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">
                                    {intel.acceptance_rate === null ? '—' : `${intel.acceptance_rate}%`}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Open duplicates</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{intel.open_duplicates}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-xs tracking-wide uppercase">Open unusual</p>
                                <p className="mt-1 text-lg font-semibold tabular-nums">{intel.open_unusual}</p>
                            </div>
                        </div>
                    </FintechPanel>
                </div>

                <FintechPanel>
                    <FintechSectionHeader title="Recent activity" meta="Admin and platform events" />
                    <div className="mt-2">
                        {dashboard.activity.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center text-sm">No recent activity.</p>
                        ) : (
                            dashboard.activity.map((item) => {
                                const Icon = activityIcon(item.icon);
                                return (
                                    <div key={item.id} className="cc-admin-activity-item">
                                        <div className="cc-admin-activity-icon">
                                            <Icon className="size-4" aria-hidden />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium">{item.title}</p>
                                            {item.context ? <p className="text-muted-foreground text-xs">{item.context}</p> : null}
                                        </div>
                                        {item.occurred_at ? (
                                            <time className="text-muted-foreground shrink-0 text-xs tabular-nums" dateTime={item.occurred_at}>
                                                {new Date(item.occurred_at).toLocaleString()}
                                            </time>
                                        ) : null}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </FintechPanel>

                <section>
                    <FintechSectionHeader title="Quick management" meta="Jump to admin tools" />
                    <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {dashboard.quick_links.map((link) => (
                            <Link key={link.href} href={link.href} className="cc-admin-quick-tile">
                                {quickVisual(link.href)}
                                <span className="text-sm font-semibold">{link.label}</span>
                            </Link>
                        ))}
                    </div>
                </section>
            </FintechPage>
        </AdminLayout>
    );
}

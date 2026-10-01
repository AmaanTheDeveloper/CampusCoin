import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { DailySpendingPoint, MonthForecastSeriesPoint, MonthlyFinancialPoint } from '@/types/dashboard';
import { useEffect, useId, useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const CHART_COLORS = ['#E94F09', '#FF7A3D', '#B8B8B8', '#6B6B6B', '#3A3A3A'];
type TooltipPayload = {
    name?: string;
    value?: number | string;
    color?: string;
    payload?: Record<string, unknown>;
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
        isDark,
        axisTick: { fontSize: 11, fill: isDark ? '#8a8a8a' : '#5c5c5c' },
        gridStroke: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.08)',
        surface: isDark ? '#030303' : '#ffffff',
        muted: isDark ? '#a8a8a8' : '#5c5c5c',
        strong: isDark ? '#cfcfcf' : '#404040',
        barCursor: isDark ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.04)',
    };
}

function ChartTooltip({ active, payload, label, valueLabel }: { active?: boolean; payload?: TooltipPayload[]; label?: string; valueLabel?: string }) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="border-border bg-popover text-popover-foreground rounded-lg border px-3 py-2 text-xs shadow-lg">
            {label ? <p className="text-foreground mb-1 font-medium">{label}</p> : null}
            {payload.map((entry) => (
                <p key={`${entry.name}-${entry.value}`} className="tabular-nums">
                    <span className="text-muted-foreground">{valueLabel ?? entry.name}: </span>
                    {formatMoney(Number(entry.value ?? 0))}
                </p>
            ))}
        </div>
    );
}

export function DailyExpenseChart({ points }: { points: DailySpendingPoint[] }) {
    const gradientId = useId();
    const theme = useChartTheme();
    const data = points.map((point) => ({
        date: point.date.slice(8),
        fullDate: point.date,
        amount: Number(point.amount),
    }));

    const total = data.reduce((sum, point) => sum + point.amount, 0);

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">Daily expenses from the start of this month through today total {formatMoney(total)}.</p>
            <div className="h-64 w-full" role="img" aria-label="Daily expense trend chart for the current month">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <defs>
                            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#E94F09" stopOpacity={0.42} />
                                <stop offset="95%" stopColor="#E94F09" stopOpacity={0.02} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                        <XAxis dataKey="date" tick={theme.axisTick} axisLine={false} tickLine={false} />
                        <YAxis tick={theme.axisTick} width={48} axisLine={false} tickLine={false} />
                        <Tooltip content={<ChartTooltip valueLabel="Expense" />} cursor={{ stroke: 'rgba(233,79,9,0.25)', strokeWidth: 1 }} />
                        <Area
                            type="monotone"
                            dataKey="amount"
                            stroke="#E94F09"
                            fill={`url(#${gradientId})`}
                            strokeWidth={2.4}
                            name="Expense"
                            isAnimationActive
                            animationDuration={1000}
                            animationEasing="ease-out"
                            activeDot={{ r: 4, fill: '#E94F09', stroke: theme.surface, strokeWidth: 2 }}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function CategoryBreakdownChart({ items }: { items: Array<{ name: string; amount: string; percentage: number | null }> }) {
    const theme = useChartTheme();
    const data = items.map((item) => ({
        name: item.name,
        amount: Number(item.amount),
        percentage: item.percentage,
    }));

    if (data.length === 0) {
        return <p className="text-muted-foreground text-sm">No expense data yet.</p>;
    }

    return (
        <div className="grid gap-4 lg:grid-cols-[220px_1fr] lg:items-center">
            <div className="mx-auto h-48 w-48" role="img" aria-label="Category spending breakdown chart">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={data}
                            dataKey="amount"
                            nameKey="name"
                            innerRadius={50}
                            outerRadius={74}
                            paddingAngle={2.5}
                            stroke={theme.surface}
                            strokeWidth={2}
                            isAnimationActive
                            animationDuration={1000}
                        >
                            {data.map((entry, index) => (
                                <Cell key={entry.name} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                            ))}
                        </Pie>
                        <Tooltip content={<ChartTooltip />} />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <ul className="space-y-2">
                {items.map((item, index) => (
                    <li key={item.name} className="flex items-center justify-between gap-3 text-sm">
                        <span className="flex min-w-0 items-center gap-2">
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: CHART_COLORS[index % CHART_COLORS.length] }}
                                aria-hidden
                            />
                            <span className="text-foreground truncate">{item.name}</span>
                        </span>
                        <span className="text-foreground text-right font-medium tabular-nums">
                            {formatMoney(item.amount)}
                            <span className="text-muted-foreground ml-2 text-xs font-normal">
                                {item.percentage === null ? 'N/A' : `${item.percentage.toFixed(1)}%`}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function MonthlyOverviewChart({ points }: { points: MonthlyFinancialPoint[] }) {
    const theme = useChartTheme();
    const data = points.map((point) => ({
        label: point.label,
        income: Number(point.income),
        expense: Number(point.expense),
    }));

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">Income and expenses across the last six calendar months.</p>
            <div className="h-64 w-full" role="img" aria-label="Six month income and expense overview chart">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                        <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                        <YAxis tick={theme.axisTick} width={48} axisLine={false} tickLine={false} />
                        <Tooltip content={<ChartTooltip />} cursor={{ fill: theme.barCursor }} />
                        <Bar dataKey="income" name="Income" fill="#E94F09" radius={[5, 5, 0, 0]} isAnimationActive animationDuration={1000} />
                        <Bar dataKey="expense" name="Expense" fill="#6B6B6B" radius={[5, 5, 0, 0]} isAnimationActive animationDuration={1000} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function MonthForecastPaceChart({ series }: { series: MonthForecastSeriesPoint[] }) {
    const theme = useChartTheme();
    const data = series.map((point) => ({
        label: point.label,
        date: point.date,
        actual: point.actual !== null ? Number(point.actual) : null,
        forecast: point.forecast !== null ? Number(point.forecast) : null,
    }));

    return (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">Actual spending through today, then projected pace to month-end.</p>
            <div className="h-64 w-full" role="img" aria-label="Actual spending versus forecast pace chart">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 6" stroke={theme.gridStroke} vertical={false} />
                        <XAxis dataKey="label" tick={theme.axisTick} axisLine={false} tickLine={false} />
                        <YAxis tick={theme.axisTick} width={48} axisLine={false} tickLine={false} />
                        <Tooltip content={<ChartTooltip />} cursor={{ stroke: 'rgba(233,79,9,0.25)', strokeWidth: 1 }} />
                        <Line
                            type="monotone"
                            dataKey="actual"
                            stroke="#E94F09"
                            strokeWidth={2.5}
                            dot={false}
                            connectNulls={false}
                            name="Actual"
                            isAnimationActive
                            animationDuration={1000}
                            animationEasing="ease-out"
                        />
                        <Line
                            type="monotone"
                            dataKey="forecast"
                            stroke={theme.muted}
                            strokeWidth={2}
                            strokeDasharray="7 5"
                            strokeLinecap="round"
                            dot={(props) => {
                                const { cx, cy, index, payload } = props;
                                if (payload?.forecast === null || payload?.forecast === undefined) {
                                    return null;
                                }
                                if (index !== data.length - 1) {
                                    return null;
                                }
                                return <circle cx={cx} cy={cy} r={4.5} fill="#E94F09" stroke={theme.surface} strokeWidth={2} />;
                            }}
                            connectNulls
                            name="Forecast"
                            isAnimationActive
                            animationDuration={1000}
                            animationEasing="ease-out"
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
            <div className="text-muted-foreground flex flex-wrap gap-5 text-xs">
                <span className="inline-flex items-center gap-2">
                    <span className="inline-block h-0.5 w-5 rounded-full bg-[#E94F09]" aria-hidden />
                    <span className="font-medium" style={{ color: theme.strong }}>
                        Actual
                    </span>
                    <span className="cc-text-tertiary">solid line</span>
                </span>
                <span className="inline-flex items-center gap-2">
                    <span className="inline-block h-0.5 w-5 border-t-2 border-dashed border-current" aria-hidden />
                    <span className="font-medium" style={{ color: theme.strong }}>
                        Forecast pace
                    </span>
                    <span className="cc-text-tertiary">dashed + end marker</span>
                </span>
            </div>
        </div>
    );
}

export function MetricCard({
    label,
    value,
    hint,
    tone = 'default',
}: {
    label: string;
    value: string;
    hint?: string;
    tone?: 'default' | 'positive' | 'negative' | 'muted';
}) {
    return (
        <article className="border-border bg-card rounded-xl border p-4 shadow-sm">
            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</p>
            <p
                className={cn(
                    'mt-2 text-2xl font-semibold tabular-nums',
                    tone === 'positive' && 'text-primary',
                    tone === 'negative' && 'text-destructive',
                    tone === 'muted' && 'text-muted-foreground',
                )}
            >
                {value}
            </p>
            {hint ? <p className="text-muted-foreground mt-1 text-xs">{hint}</p> : null}
        </article>
    );
}

import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { DailyReportPoint, MonthlyTrendItem, WeeklyReportItem } from '@/types/report';
import { useId } from 'react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/** Screen (dark fintech) — restrained orange + neutrals */
const SCREEN_CATEGORY = ['#E94F09', '#FF7A3D', '#B8B8B8', '#6B6B6B', '#3A3A3A'];
/** Export / print — higher-contrast neutrals that remain distinguishable in PDF */
const EXPORT_CATEGORY = ['#C2410C', '#9A3412', '#57534E', '#A8A29E', '#292524'];

type TooltipPayload = {
    name?: string;
    value?: number | string;
    color?: string;
};

function ChartTooltip({
    active,
    payload,
    label,
    valueLabel,
    exportMode,
}: {
    active?: boolean;
    payload?: TooltipPayload[];
    label?: string;
    valueLabel?: string;
    exportMode?: boolean;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div
            style={{
                borderRadius: 8,
                border: exportMode ? '1px solid #e2e8f0' : '1px solid rgba(255,255,255,0.12)',
                backgroundColor: exportMode ? '#ffffff' : '#101010',
                padding: '8px 12px',
                fontSize: 12,
                color: exportMode ? '#0f172a' : '#f3f3f3',
                boxShadow: exportMode ? '0 1px 2px rgba(15,23,42,0.08)' : '0 12px 28px rgba(0,0,0,0.45)',
            }}
        >
            {label ? <p style={{ margin: '0 0 4px', fontWeight: 600 }}>{label}</p> : null}
            {payload.map((entry) => (
                <p key={`${entry.name}-${entry.value}`} style={{ margin: 0, fontVariantNumeric: 'tabular-nums' }}>
                    <span style={{ color: exportMode ? '#64748b' : '#a8a8a8' }}>{valueLabel ?? entry.name}: </span>
                    {formatMoney(Number(entry.value ?? 0))}
                </p>
            ))}
        </div>
    );
}

function theme(exportMode: boolean) {
    return {
        grid: exportMode ? '#e2e8f0' : 'rgba(255,255,255,0.08)',
        tick: exportMode ? '#64748b' : '#a8a8a8',
        muted: exportMode ? '#475569' : '#a8a8a8',
        body: exportMode ? '#334155' : '#cfcfcf',
        primary: exportMode ? '#C2410C' : '#E94F09',
        secondary: exportMode ? '#57534E' : '#8A8A8A',
        income: exportMode ? '#0f766e' : '#D4D4D4',
        expense: exportMode ? '#be123c' : '#E94F09',
        category: exportMode ? EXPORT_CATEGORY : SCREEN_CATEGORY,
    };
}

export function ReportDailyChart({ points, exportMode = false }: { points: DailyReportPoint[]; exportMode?: boolean }) {
    const gradientId = useId().replace(/:/g, '');
    const t = theme(exportMode);
    const data = points.map((point) => ({
        date: point.date.slice(8),
        fullDate: point.date,
        amount: Number(point.amount),
    }));
    const total = data.reduce((sum, point) => sum + point.amount, 0);

    return (
        <div style={exportMode ? { display: 'grid', gap: 12 } : undefined} className={exportMode ? undefined : 'space-y-3'}>
            <p
                className={exportMode ? undefined : 'cc-text-secondary text-sm'}
                style={exportMode ? { margin: 0, fontSize: 14, color: t.muted } : undefined}
            >
                Daily expenses for the selected period total {formatMoney(total)}.
            </p>
            <div
                className={exportMode ? undefined : 'h-56 w-full min-w-0'}
                style={exportMode ? { height: 224, width: 840 } : undefined}
                role="img"
                aria-label="Daily spending chart"
            >
                <ResponsiveContainer width="100%" height="100%" minWidth={0} minHeight={224}>
                    <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <defs>
                            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor={t.primary} stopOpacity={0.35} />
                                <stop offset="95%" stopColor={t.primary} stopOpacity={0.02} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke={t.grid} />
                        <XAxis dataKey="date" tick={{ fontSize: 11, fill: t.tick }} />
                        <YAxis tick={{ fontSize: 11, fill: t.tick }} width={48} />
                        <Tooltip content={<ChartTooltip valueLabel="Expense" exportMode={exportMode} />} />
                        <Area
                            type="monotone"
                            dataKey="amount"
                            stroke={t.primary}
                            fill={`url(#${gradientId})`}
                            strokeWidth={2}
                            name="Expense"
                            isAnimationActive={!exportMode}
                            animationDuration={900}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

export function ReportCategoryChart({
    items,
    exportMode = false,
}: {
    items: Array<{ name: string; amount: string; percentage: number | null }>;
    exportMode?: boolean;
}) {
    const t = theme(exportMode);
    const data = items.map((item) => ({
        name: item.name,
        amount: Number(item.amount),
        percentage: item.percentage,
    }));

    if (data.length === 0) {
        return (
            <p
                className={exportMode ? undefined : 'cc-text-secondary text-sm'}
                style={exportMode ? { margin: 0, fontSize: 14, color: t.muted } : undefined}
            >
                No expense data for this period.
            </p>
        );
    }

    return (
        <div
            className={exportMode ? undefined : 'grid min-w-0 gap-4 lg:grid-cols-[200px_1fr] lg:items-center'}
            style={exportMode ? { display: 'grid', gridTemplateColumns: '176px 1fr', gap: 16, alignItems: 'center' } : undefined}
        >
            <div
                className={exportMode ? undefined : 'mx-auto h-44 w-44'}
                style={exportMode ? { height: 176, width: 176, margin: '0 auto' } : undefined}
                role="img"
                aria-label="Category spending chart"
            >
                <ResponsiveContainer width="100%" height="100%" minWidth={176} minHeight={176}>
                    <PieChart>
                        <Pie
                            data={data}
                            dataKey="amount"
                            nameKey="name"
                            innerRadius={42}
                            outerRadius={68}
                            paddingAngle={2}
                            isAnimationActive={!exportMode}
                            animationDuration={900}
                        >
                            {data.map((entry, index) => (
                                <Cell
                                    key={entry.name}
                                    fill={t.category[index % t.category.length]}
                                    stroke={exportMode ? '#fff' : '#030303'}
                                    strokeWidth={1}
                                />
                            ))}
                        </Pie>
                        <Tooltip content={<ChartTooltip exportMode={exportMode} />} />
                    </PieChart>
                </ResponsiveContainer>
            </div>
            <ul className={exportMode ? undefined : 'space-y-2'} style={exportMode ? { listStyle: 'none', margin: 0, padding: 0 } : undefined}>
                {items.map((item, index) => (
                    <li
                        key={item.name}
                        className={exportMode ? undefined : 'flex items-center justify-between gap-3 text-sm text-[#cfcfcf]'}
                        style={
                            exportMode
                                ? {
                                      display: 'flex',
                                      alignItems: 'center',
                                      justifyContent: 'space-between',
                                      gap: 12,
                                      fontSize: 14,
                                      color: '#1e293b',
                                      marginBottom: 8,
                                  }
                                : undefined
                        }
                    >
                        <span
                            className={exportMode ? undefined : 'flex min-w-0 items-center gap-2'}
                            style={exportMode ? { display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 } : undefined}
                        >
                            <span
                                className={exportMode ? undefined : 'size-2.5 shrink-0 rounded-full'}
                                style={{
                                    backgroundColor: t.category[index % t.category.length],
                                    width: 10,
                                    height: 10,
                                    borderRadius: 999,
                                    flexShrink: 0,
                                    display: 'inline-block',
                                }}
                                aria-hidden
                            />
                            <span
                                className={exportMode ? undefined : 'truncate'}
                                style={exportMode ? { overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } : undefined}
                            >
                                {item.name}
                            </span>
                        </span>
                        <span
                            className={exportMode ? undefined : 'text-right text-white tabular-nums'}
                            style={exportMode ? { textAlign: 'right', fontVariantNumeric: 'tabular-nums' } : undefined}
                        >
                            {formatMoney(item.amount)}
                            <span
                                className={exportMode ? undefined : 'cc-text-tertiary ml-2 text-xs'}
                                style={exportMode ? { marginLeft: 8, fontSize: 12, color: '#64748b' } : undefined}
                            >
                                {item.percentage === null ? 'N/A' : `${item.percentage.toFixed(1)}%`}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ReportWeeklyChart({ items, exportMode = false }: { items: WeeklyReportItem[]; exportMode?: boolean }) {
    const t = theme(exportMode);
    const data = items.map((item) => ({
        label: item.label,
        amount: Number(item.amount),
        range: `${item.start} – ${item.end}`,
    }));

    return (
        <div style={exportMode ? { display: 'grid', gap: 12 } : undefined} className={exportMode ? undefined : 'space-y-3'}>
            <p
                className={exportMode ? undefined : 'cc-text-secondary text-sm'}
                style={exportMode ? { margin: 0, fontSize: 14, color: t.muted } : undefined}
            >
                Weekly expense totals (weeks start on Monday).
            </p>
            <div
                className={exportMode ? undefined : 'h-56 w-full min-w-0'}
                style={exportMode ? { height: 224, width: 840 } : undefined}
                role="img"
                aria-label="Weekly spending chart"
            >
                <ResponsiveContainer width="100%" height="100%" minWidth={exportMode ? 840 : 0} minHeight={224}>
                    <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke={t.grid} />
                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: t.tick }} />
                        <YAxis tick={{ fontSize: 11, fill: t.tick }} width={48} />
                        <Tooltip content={<ChartTooltip valueLabel="Expense" exportMode={exportMode} />} />
                        <Bar
                            dataKey="amount"
                            name="Expense"
                            fill={t.primary}
                            radius={[4, 4, 0, 0]}
                            isAnimationActive={!exportMode}
                            animationDuration={900}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <ul
                className={exportMode ? undefined : 'cc-text-secondary space-y-1 text-sm'}
                style={exportMode ? { listStyle: 'none', margin: 0, padding: 0, fontSize: 14, color: t.body } : undefined}
            >
                {items.map((item) => (
                    <li
                        key={item.week}
                        className={exportMode ? undefined : 'flex justify-between gap-3'}
                        style={exportMode ? { display: 'flex', justifyContent: 'space-between', gap: 12, marginBottom: 4 } : undefined}
                    >
                        <span>
                            {item.label} ({item.start} – {item.end})
                        </span>
                        <span
                            className={cn(!exportMode && 'text-white tabular-nums')}
                            style={exportMode ? { fontVariantNumeric: 'tabular-nums' } : undefined}
                        >
                            {formatMoney(item.amount)}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ReportSixMonthChart({ points, exportMode = false }: { points: MonthlyTrendItem[]; exportMode?: boolean }) {
    const t = theme(exportMode);
    const data = points.map((point) => ({
        label: point.label,
        income: Number(point.income),
        expense: Number(point.expense),
    }));

    return (
        <div style={exportMode ? { display: 'grid', gap: 12 } : undefined} className={exportMode ? undefined : 'space-y-3'}>
            <p
                className={exportMode ? undefined : 'cc-text-secondary text-sm'}
                style={exportMode ? { margin: 0, fontSize: 14, color: t.muted } : undefined}
            >
                Income versus expenses for the last six calendar months.
            </p>
            <div
                className={exportMode ? undefined : 'h-56 w-full min-w-0'}
                style={exportMode ? { height: 224, width: 840 } : undefined}
                role="img"
                aria-label="Six month income versus expense chart"
            >
                <ResponsiveContainer width="100%" height="100%" minWidth={exportMode ? 840 : 0} minHeight={224}>
                    <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke={t.grid} />
                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: t.tick }} />
                        <YAxis tick={{ fontSize: 11, fill: t.tick }} width={48} />
                        <Tooltip content={<ChartTooltip exportMode={exportMode} />} />
                        <Bar
                            dataKey="income"
                            name="Income"
                            fill={t.income}
                            radius={[4, 4, 0, 0]}
                            isAnimationActive={!exportMode}
                            animationDuration={900}
                        />
                        <Bar
                            dataKey="expense"
                            name="Expense"
                            fill={t.expense}
                            radius={[4, 4, 0, 0]}
                            isAnimationActive={!exportMode}
                            animationDuration={900}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

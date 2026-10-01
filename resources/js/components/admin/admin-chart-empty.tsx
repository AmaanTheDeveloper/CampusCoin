import { FintechEmpty } from '@/components/fintech-page';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type AdminChartEmptyProps = {
    title: string;
    description: string;
    visual?: ReactNode;
    className?: string;
};

/** Compact empty panel for Admin charts — keeps FintechEmpty structure without oversized chrome. */
export function AdminChartEmpty({ title, description, visual, className }: AdminChartEmptyProps) {
    return (
        <FintechEmpty
            visual={visual}
            title={title}
            description={description}
            className={cn('cc-admin-chart-empty border-0 bg-transparent px-4 py-8 shadow-none', className)}
        />
    );
}

export function seriesHasActivity(points: Array<{ count?: number }>): boolean {
    return points.some((point) => Number(point.count ?? 0) > 0);
}

export function moneySeriesHasActivity(
    points: Array<{ income?: number; expense?: number }>,
    incomeTotal?: number | string,
    expenseTotal?: number | string,
): boolean {
    if (Number(incomeTotal ?? 0) > 0 || Number(expenseTotal ?? 0) > 0) {
        return true;
    }

    return points.some((point) => Number(point.income ?? 0) > 0 || Number(point.expense ?? 0) > 0);
}

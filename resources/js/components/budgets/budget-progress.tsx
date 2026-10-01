import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { BudgetStatusValue } from '@/types/budget';

export function BudgetStatusBadge({ status, label }: { status: BudgetStatusValue; label: string }) {
    return (
        <Badge
            variant={status === 'exceeded' ? 'destructive' : status === 'near_limit' ? 'secondary' : 'default'}
            className={cn(status === 'healthy' && 'bg-primary')}
        >
            {label}
        </Badge>
    );
}

function safePercentage(value: number): number {
    if (!Number.isFinite(value) || value < 0) {
        return 0;
    }

    return value;
}

export function BudgetProgress({ categoryName, percentage, status }: { categoryName: string; percentage: number; status: BudgetStatusValue }) {
    const safe = safePercentage(percentage);
    const capped = Math.min(safe, 100);
    const display = safe.toFixed(safe % 1 === 0 ? 0 : 1);
    const label = `${categoryName} budget: ${display}% used. Status ${status.replace('_', ' ')}.`;

    return (
        <div className="space-y-1">
            <div
                className="bg-muted h-2.5 w-full overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={Math.round(capped)}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={label}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width]',
                        status === 'exceeded' && 'bg-destructive',
                        status === 'near_limit' && 'bg-chart-3',
                        status === 'healthy' && 'bg-primary',
                    )}
                    style={{ width: `${capped}%` }}
                />
            </div>
            <p className="text-muted-foreground text-xs tabular-nums">{display}% used</p>
        </div>
    );
}

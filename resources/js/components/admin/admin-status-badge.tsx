import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type AdminStatusBadgeProps = {
    status: 'active' | 'inactive' | 'disabled';
    className?: string;
};

const labels: Record<AdminStatusBadgeProps['status'], string> = {
    active: 'Active',
    inactive: 'Inactive',
    disabled: 'Disabled',
};

/** Admin status chip — calm success/neutral, distinct from brand-orange CTAs. */
export function AdminStatusBadge({ status, className }: AdminStatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'cc-admin-status border font-medium capitalize',
                status === 'active' && 'cc-admin-status--active',
                status !== 'active' && 'cc-admin-status--muted',
                className,
            )}
        >
            {labels[status]}
        </Badge>
    );
}

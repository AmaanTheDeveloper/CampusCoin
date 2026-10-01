import { cn } from '@/lib/utils';
import AppLogoIcon from './app-logo-icon';

type CampusCoinBrandProps = {
    /** Icon-only mark */
    compact?: boolean;
    /** Show tagline under the name */
    showTagline?: boolean;
    className?: string;
    iconClassName?: string;
};

/**
 * Reusable Campus Coin brand lockup for header, sidebar, and marketing surfaces.
 */
export default function CampusCoinBrand({ compact = false, showTagline = false, className, iconClassName }: CampusCoinBrandProps) {
    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div className="bg-primary text-primary-foreground flex aspect-square size-8 shrink-0 items-center justify-center rounded-md">
                <AppLogoIcon className={cn('text-primary-foreground size-5', iconClassName)} />
            </div>
            {!compact && (
                <div className="grid flex-1 text-left text-sm leading-tight">
                    <span className="truncate font-semibold tracking-tight">Campus Coin</span>
                    {showTagline && <span className="text-muted-foreground truncate text-xs">Smart Spending Student Style</span>}
                </div>
            )}
        </div>
    );
}

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ScanLine } from 'lucide-react';
import { lazy, Suspense, useState, type ComponentProps } from 'react';

const SmartScanDialog = lazy(() => import('@/components/document-scan/smart-scan-dialog').then((m) => ({ default: m.SmartScanDialog })));

type Props = {
    className?: string;
    variant?: ComponentProps<typeof Button>['variant'];
    label?: string;
};

export function SmartScanTrigger({ className, variant = 'outline', label = 'Smart Scan' }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant={variant}
                className={cn('cc-dash-btn-ghost', className)}
                onClick={() => setOpen(true)}
                data-testid="smart-scan-trigger"
            >
                <ScanLine className="mr-2 size-4" aria-hidden />
                {label}
            </Button>
            {open ? (
                <Suspense fallback={null}>
                    <SmartScanDialog open={open} onOpenChange={setOpen} />
                </Suspense>
            ) : null}
        </>
    );
}

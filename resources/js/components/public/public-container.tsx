import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'react';

type PublicContainerProps = HTMLAttributes<HTMLDivElement>;

export default function PublicContainer({ className, children, ...props }: PublicContainerProps) {
    return (
        <div className={cn('cc-public-container', className)} {...props}>
            {children}
        </div>
    );
}

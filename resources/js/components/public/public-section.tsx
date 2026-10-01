import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'react';

type PublicSectionVariant = 'default' | 'muted' | 'accent' | 'dark';

type PublicSectionProps = HTMLAttributes<HTMLElement> & {
    variant?: PublicSectionVariant;
    as?: 'section' | 'div' | 'header' | 'footer';
};

const variantClass: Record<PublicSectionVariant, string> = {
    default: '',
    muted: 'cc-public-section--muted',
    accent: 'cc-public-section--accent',
    dark: 'cc-public-section--dark',
};

export default function PublicSection({ variant = 'default', as: Tag = 'section', className, children, ...props }: PublicSectionProps) {
    return (
        <Tag className={cn('cc-public-section', variantClass[variant], className)} {...props}>
            {children}
        </Tag>
    );
}

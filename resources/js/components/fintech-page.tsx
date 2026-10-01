import { cn } from '@/lib/utils';
import { type HTMLAttributes, type ReactNode } from 'react';

type FintechPageProps = {
    children: ReactNode;
    className?: string;
    stagger?: boolean;
};

/**
 * Shared authenticated page surface — padding, entrance motion, stagger.
 * Theme/atmosphere live on the app shell; this only structures page content.
 */
export function FintechPage({ children, className, stagger = true }: FintechPageProps) {
    return (
        <div className={cn('cc-page-enter relative flex min-w-0 flex-col gap-6 p-4 pb-6 md:p-6 md:pb-8', stagger && 'cc-stagger', className)}>
            {children}
        </div>
    );
}

type FintechPageHeaderProps = {
    eyebrow?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    visual?: ReactNode;
};

export function FintechPageHeader({ eyebrow, title, description, actions, visual }: FintechPageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="flex min-w-0 flex-1 items-start gap-4">
                {visual ? <div className="hidden shrink-0 sm:block">{visual}</div> : null}
                <div className="min-w-0">
                    {eyebrow ? <div className="cc-label mb-2 flex items-center gap-2">{eyebrow}</div> : null}
                    <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">{title}</h1>
                    {description ? <p className="cc-text-secondary mt-1 max-w-2xl text-sm">{description}</p> : null}
                </div>
            </div>
            {actions ? <div className="flex w-full flex-wrap items-center gap-2 lg:w-auto lg:justify-end">{actions}</div> : null}
        </header>
    );
}

export function FintechPanel({
    children,
    className,
    interactive = false,
    ...props
}: { children: ReactNode; className?: string; interactive?: boolean } & HTMLAttributes<HTMLElement>) {
    return (
        <section className={cn('cc-card p-4 md:p-5', interactive && 'cc-card-interactive', className)} {...props}>
            {children}
        </section>
    );
}

export function FintechFlash({ children, tone = 'success' }: { children: ReactNode; tone?: 'success' | 'warning' | 'error' }) {
    return (
        <div
            role="status"
            className={cn(
                'rounded-[var(--radius)] border px-4 py-3 text-sm',
                tone === 'success' && 'border-[#E94F09]/30 bg-[#E94F09]/10 text-foreground',
                tone === 'warning' && 'border-amber-500/30 bg-amber-500/10 text-foreground',
                tone === 'error' && 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-100',
            )}
        >
            {children}
        </div>
    );
}

type FintechSectionHeaderProps = {
    title: ReactNode;
    meta?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function FintechSectionHeader({ title, meta, action, className }: FintechSectionHeaderProps) {
    return (
        <div className={cn('flex flex-wrap items-end justify-between gap-3', className)}>
            <div className="min-w-0">
                <h2 className="text-foreground text-lg font-semibold tracking-tight">{title}</h2>
                {meta ? <div className="cc-text-secondary mt-0.5 text-xs">{meta}</div> : null}
            </div>
            {action ? <div className="flex shrink-0 flex-wrap items-center gap-2">{action}</div> : null}
        </div>
    );
}

type FintechEmptyProps = {
    visual?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function FintechEmpty({ visual, title, description, action, className }: FintechEmptyProps) {
    return (
        <div
            className={cn(
                'cc-card flex flex-col items-center justify-center border-dashed px-6 py-12 text-center md:py-16',
                className,
            )}
        >
            {visual ? <div className="mb-4 [&_svg]:size-6 opacity-90">{visual}</div> : null}
            <h3 className="text-foreground text-base font-semibold md:text-lg">{title}</h3>
            {description ? <p className="cc-text-secondary mt-2 max-w-md text-sm leading-relaxed">{description}</p> : null}
            {action ? <div className="mt-5 flex flex-wrap justify-center gap-2">{action}</div> : null}
        </div>
    );
}

type FintechFormShellProps = {
    children: ReactNode;
    className?: string;
    as?: 'form' | 'div';
} & HTMLAttributes<HTMLElement>;

/** Primary form surface — Level 2 elevation, intentional max width. */
export function FintechFormShell({ children, className, as: Tag = 'div', ...props }: FintechFormShellProps) {
    return (
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        <Tag className={cn('cc-form-shell mx-auto w-full max-w-xl space-y-5 p-5 md:p-6', className)} {...(props as any)}>
            {children}
        </Tag>
    );
}

type FintechFormFieldProps = {
    children: ReactNode;
    className?: string;
};

export function FintechFormField({ children, className }: FintechFormFieldProps) {
    return <div className={cn('cc-form-field grid gap-1.5', className)}>{children}</div>;
}

type FintechFormActionsProps = {
    children: ReactNode;
    className?: string;
};

export function FintechFormActions({ children, className }: FintechFormActionsProps) {
    return (
        <div
            className={cn(
                'cc-form-actions flex flex-col-reverse gap-2 border-t border-white/8 pt-4 sm:flex-row sm:items-center sm:justify-end',
                className,
            )}
        >
            {children}
        </div>
    );
}

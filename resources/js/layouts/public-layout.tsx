import PublicFooter from '@/components/public/public-footer';
import PublicNavbar from '@/components/public/public-navbar';
import PublicSeo from '@/components/public/public-seo';
import { cn } from '@/lib/utils';
import type { PropsWithChildren } from 'react';

type PublicLayoutProps = PropsWithChildren<{
    title: string;
    description: string;
    className?: string;
}>;

export default function PublicLayout({ title, description, className, children }: PublicLayoutProps) {
    return (
        <div className="cc-public-root bg-background text-foreground flex min-h-screen flex-col">
            <PublicSeo title={title} description={description} />
            <PublicNavbar />
            <main className={cn('cc-public-enter flex-1', className)}>{children}</main>
            <PublicFooter />
        </div>
    );
}

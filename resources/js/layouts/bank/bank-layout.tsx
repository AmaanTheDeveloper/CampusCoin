import { BankSidebar } from '@/components/bank/bank-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { useEffect } from 'react';

function BankHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const pageTitle = breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1]?.title : null;
    const parentCrumbs = breadcrumbs.length > 1 ? breadcrumbs.slice(0, -1) : [];

    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-white/5 px-4 transition-[width,height] ease-linear md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <SidebarTrigger className="-ml-1 size-11 min-h-11 min-w-11" />
                <div className="min-w-0">
                    {pageTitle ? <p className="truncate text-sm font-semibold tracking-tight text-white md:text-base">{pageTitle}</p> : null}
                    {parentCrumbs.length > 0 ? (
                        <div className="hidden sm:block">
                            <Breadcrumbs breadcrumbs={parentCrumbs} />
                        </div>
                    ) : null}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <AppearanceToggleDropdown />
            </div>
        </header>
    );
}

export default function BankLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    useEffect(() => {
        const root = document.documentElement;
        root.classList.add('cc-fintech-root');
        return () => {
            root.classList.remove('cc-fintech-root');
        };
    }, []);

    return (
        <div className="cc-fintech relative min-h-svh w-full">
            <div className="cc-fintech-atmosphere" aria-hidden />
            <div className="cc-fintech-grain" aria-hidden />
            <div className="cc-fintech-content relative z-10 min-h-svh w-full">
                <AppShell variant="sidebar">
                    <BankSidebar />
                    <AppContent variant="sidebar" className="relative bg-transparent">
                        <BankHeader breadcrumbs={breadcrumbs} />
                        {children}
                    </AppContent>
                </AppShell>
            </div>
        </div>
    );
}

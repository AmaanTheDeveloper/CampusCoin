import { AdminNotificationBell } from '@/components/admin/admin-notification-bell';
import { AdminSidebar } from '@/components/admin/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { useEffect } from 'react';

function AdminHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const pageTitle = breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1]?.title : null;
    const parentCrumbs = breadcrumbs.length > 1 ? breadcrumbs.slice(0, -1) : [];

    return (
        <header className="cc-admin-header sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-white/5 px-4 transition-[width,height] ease-linear md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <SidebarTrigger className="cc-admin-sidebar-trigger -ml-1 size-11 min-h-11 min-w-11" />
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
                <AdminNotificationBell />
                <AppearanceToggleDropdown className="cc-admin-theme-toggle" />
            </div>
        </header>
    );
}

export default function AdminLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    useEffect(() => {
        const root = document.documentElement;
        root.classList.add('cc-fintech-root', 'cc-admin');
        return () => {
            root.classList.remove('cc-fintech-root', 'cc-admin');
        };
    }, []);

    return (
        <div className="cc-fintech cc-admin relative min-h-svh w-full">
            <div className="cc-fintech-atmosphere" aria-hidden />
            <div className="cc-fintech-grain" aria-hidden />
            <div className="cc-fintech-content relative z-10 min-h-svh w-full">
                <AppShell variant="sidebar">
                    <AdminSidebar />
                    <AppContent variant="sidebar" className="relative bg-transparent">
                        <AdminHeader breadcrumbs={breadcrumbs} />
                        {children}
                    </AppContent>
                </AppShell>
            </div>
        </div>
    );
}

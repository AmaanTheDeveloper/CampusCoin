import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { RecurringNotificationSound } from '@/components/notifications/recurring-notification-sound';
import { type BreadcrumbItem } from '@/types';
import { useEffect } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    useEffect(() => {
        const root = document.documentElement;
        root.classList.add('cc-fintech-root');
        return () => {
            root.classList.remove('cc-fintech-root');
        };
    }, []);

    return (
        <div className="cc-fintech relative min-h-svh w-full">
            <RecurringNotificationSound />
            <div className="cc-fintech-atmosphere" aria-hidden />
            <div className="cc-fintech-grain" aria-hidden />
            <div className="cc-fintech-content relative z-10 min-h-svh w-full">
                <AppShell variant="sidebar">
                    <AppSidebar />
                    <AppContent variant="sidebar" className="relative bg-transparent">
                        <AppSidebarHeader breadcrumbs={breadcrumbs} />
                        {children}
                    </AppContent>
                </AppShell>
            </div>
        </div>
    );
}

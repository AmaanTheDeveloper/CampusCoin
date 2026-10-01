import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Globe } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { unread_notifications_count = 0 } = usePage<SharedData>().props;
    const pageTitle = breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1]?.title : null;
    const parentCrumbs = breadcrumbs.length > 1 ? breadcrumbs.slice(0, -1) : [];

    return (
        <header className="cc-app-header sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6">
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <SidebarTrigger className="cc-app-sidebar-trigger -ml-1 hover:bg-white/5" />
                <div className="min-w-0">
                    {pageTitle ? <p className="text-foreground truncate text-sm font-semibold tracking-tight md:text-base">{pageTitle}</p> : null}
                    {parentCrumbs.length > 0 ? (
                        <div className="hidden sm:block">
                            <Breadcrumbs breadcrumbs={parentCrumbs} />
                        </div>
                    ) : null}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    className="cc-icon-btn text-muted-foreground hover:text-foreground relative !size-11"
                >
                    <Link href={route('home')} aria-label="Visit website">
                        <Globe className="size-5" />
                    </Link>
                </Button>
                <AppearanceToggleDropdown className="cc-app-theme-toggle text-muted-foreground hover:text-foreground [&_button]:!size-11 [&_button]:text-muted-foreground hover:[&_button]:bg-white/5 hover:[&_button]:text-foreground" />
                <Button variant="ghost" size="icon" asChild className="cc-icon-btn text-muted-foreground hover:text-foreground relative !size-11">
                    <Link href={route('notifications.index')} aria-label="Open notifications">
                        <Bell className="size-5" />
                        {unread_notifications_count > 0 ? (
                            <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-[#E94F09] px-1 text-[10px] leading-4 text-white shadow-[0_0_12px_rgba(233,79,9,0.45)]">
                                {unread_notifications_count > 99 ? '99+' : unread_notifications_count}
                            </span>
                        ) : null}
                    </Link>
                </Button>
            </div>
        </header>
    );
}

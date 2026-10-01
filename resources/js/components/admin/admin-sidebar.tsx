import AppLogo from '@/components/app-logo';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavGroup } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Brain, FolderTree, Globe, Landmark, LayoutDashboard, LayoutGrid, Lightbulb, LogOut, Megaphone, Settings, Users } from 'lucide-react';

const adminNavGroups: NavGroup[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                url: '/admin',
                icon: LayoutGrid,
            },
        ],
    },
    {
        title: 'Management',
        items: [
            {
                title: 'Users',
                url: '/admin/users',
                icon: Users,
            },
            {
                title: 'Banks',
                url: '/admin/banks',
                icon: Landmark,
            },
            {
                title: 'Pending bank requests',
                url: '/admin/banks?status=pending',
                icon: Landmark,
            },
            {
                title: 'Categories',
                url: '/admin/categories',
                icon: FolderTree,
            },
        ],
    },
    {
        title: 'Content',
        items: [
            {
                title: 'Announcements',
                url: '/admin/announcements',
                icon: Megaphone,
            },
            {
                title: 'Saving Tips',
                url: '/admin/saving-tips',
                icon: Lightbulb,
            },
        ],
    },
    {
        title: 'Insights',
        items: [
            {
                title: 'Analytics',
                url: '/admin/analytics',
                icon: BarChart3,
            },
            {
                title: 'AI Intelligence',
                url: '/admin/intelligence',
                icon: Brain,
            },
        ],
    },
];

function pathnameOnly(url: string): string {
    return url.split('?')[0] || url;
}

function isAdminNavActive(itemUrl: string, currentUrl: string, allUrls: string[]): boolean {
    const current = pathnameOnly(currentUrl);
    if (current === itemUrl || current === `${itemUrl}/`) {
        return true;
    }

    // Dashboard is exact-match only so routes like /admin/notifications do not activate it.
    if (itemUrl === '/admin') {
        return false;
    }

    if (!current.startsWith(`${itemUrl}/`)) {
        return false;
    }

    return !allUrls.some((other) => other !== itemUrl && other.length > itemUrl.length && (current === other || current.startsWith(`${other}/`)));
}

export function AdminSidebar() {
    const page = usePage();
    const allUrls = adminNavGroups.flatMap((group) => group.items.map((item) => item.url));

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r border-white/5">
            <SidebarHeader className="border-b border-white/5 pb-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('admin.dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <p className="cc-admin-meta px-2 pt-1 text-[10px] tracking-[0.14em] uppercase group-data-[collapsible=icon]:hidden">Admin control</p>
            </SidebarHeader>

            <SidebarContent className="gap-5 py-3">
                {adminNavGroups.map((group) => (
                    <SidebarGroup key={group.title} className="px-2 py-0">
                        <SidebarGroupLabel className="cc-admin-meta mb-1 text-[10px] tracking-[0.16em] uppercase">{group.title}</SidebarGroupLabel>
                        <SidebarMenu className="gap-0.5">
                            {group.items.map((item) => {
                                const active = isAdminNavActive(item.url, page.url, allUrls);
                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            className={cn('cc-admin-nav-item', item.secondary && !active && 'cc-nav-secondary')}
                                        >
                                            <Link href={item.url} prefetch>
                                                {item.icon ? <item.icon /> : null}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}

                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="cc-admin-meta mb-1 text-[10px] tracking-[0.16em] uppercase">Switch</SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-admin-nav-item cc-nav-secondary cc-admin-switch-link">
                                <Link href={route('dashboard')} prefetch>
                                    <LayoutDashboard />
                                    <span>Student Dashboard</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-admin-nav-item cc-nav-secondary cc-admin-switch-link">
                                <Link href={route('home')}>
                                    <Globe />
                                    <span>View Website</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="border-t border-white/5 pt-3">
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="cc-admin-meta text-[10px] tracking-[0.14em] uppercase">System</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={page.url.startsWith('/admin/settings')} className="cc-admin-nav-item">
                                <Link href={route('admin.settings.index')} prefetch>
                                    <Settings />
                                    <span>Settings</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-admin-nav-item">
                                <Link href={route('admin.logout')} method="post" as="button">
                                    <LogOut />
                                    <span>Log out</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <p className="cc-admin-meta px-2 pb-2 text-[10px] leading-snug group-data-[collapsible=icon]:hidden">CampusCoin · Administrator</p>
            </SidebarFooter>
        </Sidebar>
    );
}

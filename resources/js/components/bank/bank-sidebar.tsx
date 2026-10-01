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
import { FileText, Globe, HandCoins, Landmark, LayoutGrid, LogOut, Package, Users } from 'lucide-react';

const bankNavGroups: NavGroup[] = [
    {
        title: 'Portal',
        items: [
            {
                title: 'Overview',
                url: '/bank',
                icon: LayoutGrid,
            },
            {
                title: 'Applications',
                url: '/bank/applications',
                icon: FileText,
            },
            {
                title: 'Products',
                url: '/bank/products',
                icon: Package,
            },
            {
                title: 'Loans',
                url: '/bank/loans',
                icon: HandCoins,
            },
            {
                title: 'Staff',
                url: '/bank/staff',
                icon: Users,
            },
        ],
    },
];

function pathnameOnly(url: string): string {
    return url.split('?')[0] || url;
}

function isBankNavActive(itemUrl: string, currentUrl: string, allUrls: string[]): boolean {
    const current = pathnameOnly(currentUrl);
    if (current === itemUrl || current === `${itemUrl}/`) {
        return true;
    }

    if (itemUrl === '/bank') {
        return false;
    }

    if (!current.startsWith(`${itemUrl}/`)) {
        return false;
    }

    return !allUrls.some(
        (other) => other !== itemUrl && other.length > itemUrl.length && (current === other || current.startsWith(`${other}/`)),
    );
}

export function BankSidebar() {
    const page = usePage();
    const allUrls = bankNavGroups.flatMap((group) => group.items.map((item) => item.url));

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r border-white/5">
            <SidebarHeader className="border-b border-white/5 pb-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('bank.dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <p className="px-2 pt-1 text-[10px] tracking-[0.14em] text-[#737373] uppercase group-data-[collapsible=icon]:hidden">
                    Bank portal
                </p>
            </SidebarHeader>

            <SidebarContent className="gap-5 py-3">
                {bankNavGroups.map((group) => (
                    <SidebarGroup key={group.title} className="px-2 py-0">
                        <SidebarGroupLabel className="mb-1 text-[10px] tracking-[0.16em] text-[#737373] uppercase">{group.title}</SidebarGroupLabel>
                        <SidebarMenu className="gap-0.5">
                            {group.items.map((item) => {
                                const active = isBankNavActive(item.url, page.url, allUrls);
                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton asChild isActive={active} className={cn(item.secondary && !active && 'cc-nav-secondary')}>
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
                    <SidebarGroupLabel className="mb-1 text-[10px] tracking-[0.16em] text-[#737373] uppercase">Links</SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-nav-secondary">
                                <Link href={route('home')}>
                                    <Globe />
                                    <span>Website</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-nav-secondary">
                                <Link href={route('student-loans')}>
                                    <Landmark />
                                    <span>Student loans</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter className="border-t border-white/5 pt-3">
                <SidebarGroup className="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild>
                                <Link href={route('logout')} method="post" as="button">
                                    <LogOut />
                                    <span>Log out</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <p className="px-2 pb-2 text-[10px] leading-snug text-[#737373] group-data-[collapsible=icon]:hidden">
                    CampusCoin · Partner bank
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}

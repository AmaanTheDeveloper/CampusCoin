import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { type NavGroup, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Bookmark,
    Brain,
    CalendarRange,
    CopyCheck,
    FileBarChart,
    FolderTree,
    Globe,
    Landmark,
    LayoutGrid,
    Lightbulb,
    PiggyBank,
    RefreshCw,
    Settings,
    Sparkles,
    Target,
    Upload,
} from 'lucide-react';
import AppLogo from './app-logo';

const navGroups: NavGroup[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                url: '/dashboard',
                icon: LayoutGrid,
            },
        ],
    },
    {
        title: 'Finance',
        items: [
            {
                title: 'Transactions',
                url: '/transactions',
                icon: ArrowLeftRight,
            },
            {
                title: 'Budgets',
                url: '/budgets',
                icon: PiggyBank,
            },
            {
                title: 'Loans',
                url: '/loans',
                icon: Landmark,
            },
            {
                title: 'Categories',
                url: '/categories',
                icon: FolderTree,
            },
            {
                title: 'Recurring',
                url: '/recurring-transactions',
                icon: RefreshCw,
            },
            {
                title: 'Reports',
                url: '/reports',
                icon: FileBarChart,
            },
            {
                title: 'Import CSV',
                url: '/transactions/import',
                icon: Upload,
                secondary: true,
            },
        ],
    },
    {
        title: 'Intelligence',
        items: [
            {
                title: 'Spending Alerts',
                url: '/spending-alerts',
                icon: AlertTriangle,
            },
            {
                title: 'Month Forecast',
                url: '/month-forecast',
                icon: CalendarRange,
            },
            {
                title: 'Savings Goal',
                url: '/savings-goal',
                icon: Target,
            },
            {
                title: 'Monthly Review',
                url: '/monthly-review',
                icon: Sparkles,
            },
            {
                title: 'Duplicate Review',
                url: '/duplicate-review',
                icon: CopyCheck,
                badgeKey: 'pending_duplicate_count',
            },
            {
                title: 'Intelligence',
                url: '/intelligence',
                icon: Brain,
                secondary: true,
            },
            {
                title: 'Insights',
                url: '/insights',
                icon: Lightbulb,
                secondary: true,
            },
            {
                title: 'Bookmarks',
                url: '/bookmarks',
                icon: Bookmark,
                secondary: true,
            },
        ],
    },
];

export function AppSidebar() {
    const page = usePage<SharedData>();
    const settingsActive = page.url.startsWith('/settings');
    const isAdmin = page.props.auth.user?.role === 'admin';
    const adminActive = page.url.startsWith('/admin');

    return (
        <Sidebar collapsible="icon" variant="inset" className="border-r border-white/5">
            <SidebarHeader className="border-b border-white/5 pb-3">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-5 py-3">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-white/5 pt-3">
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel className="text-[10px] tracking-[0.14em] text-[#737373] uppercase">Account</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild className="cc-nav-secondary">
                                <Link href={route('home')}>
                                    <Globe />
                                    <span>Website</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        {isAdmin ? (
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild isActive={adminActive} className="cc-nav-secondary">
                                    <Link href="/admin" prefetch>
                                        <LayoutGrid />
                                        <span>Admin Control Center</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ) : null}
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild isActive={settingsActive}>
                                <Link href="/settings/profile" prefetch>
                                    <Settings />
                                    <span>Settings</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
                <p className="px-2 pb-2 text-[10px] leading-snug text-[#737373] group-data-[collapsible=icon]:hidden">
                    CampusCoin · Premium student finance
                </p>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

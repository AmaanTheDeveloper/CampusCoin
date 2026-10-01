import { Breadcrumbs } from '@/components/breadcrumbs';
import { Icon } from '@/components/icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { NavigationMenu, NavigationMenuItem, NavigationMenuList, navigationMenuTriggerStyle } from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Bell,
    Bookmark,
    Brain,
    CalendarRange,
    CopyCheck,
    FileBarChart,
    FolderTree,
    LayoutGrid,
    Lightbulb,
    Menu,
    PiggyBank,
    RefreshCw,
    Sparkles,
    Target,
    Upload,
} from 'lucide-react';
import AppLogo from './app-logo';
import AppLogoIcon from './app-logo-icon';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Transactions',
        url: '/transactions',
        icon: ArrowLeftRight,
    },
    {
        title: 'Month Forecast',
        url: '/month-forecast',
        icon: CalendarRange,
    },
    {
        title: 'Monthly Review',
        url: '/monthly-review',
        icon: Sparkles,
    },
    {
        title: 'Savings Goal',
        url: '/savings-goal',
        icon: Target,
    },
    {
        title: 'Spending Alerts',
        url: '/spending-alerts',
        icon: AlertTriangle,
    },
    {
        title: 'Duplicate Review',
        url: '/duplicate-review',
        icon: CopyCheck,
        badgeKey: 'pending_duplicate_count',
    },
    {
        title: 'Reports',
        url: '/reports',
        icon: FileBarChart,
    },
    {
        title: 'Intelligence',
        url: '/intelligence',
        icon: Brain,
    },
    {
        title: 'Insights',
        url: '/insights',
        icon: Lightbulb,
    },
    {
        title: 'Bookmarks',
        url: '/bookmarks',
        icon: Bookmark,
    },
    {
        title: 'Budgets',
        url: '/budgets',
        icon: PiggyBank,
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
        title: 'Import',
        url: '/transactions/import',
        icon: Upload,
    },
];

const activeItemStyles = 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

interface AppHeaderProps {
    breadcrumbs?: BreadcrumbItem[];
}

export function AppHeader({ breadcrumbs = [] }: AppHeaderProps) {
    const page = usePage<SharedData>();
    const { auth, unread_notifications_count = 0 } = page.props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    const user = auth.user;

    return (
        <>
            <div className="border-sidebar-border/80 border-b">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" className="mr-2 h-[34px] w-[34px]">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="bg-sidebar flex h-full w-64 flex-col items-stretch justify-between">
                                <SheetTitle className="sr-only">Navigation Menu</SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="text-foreground h-6 w-6" />
                                </SheetHeader>
                                <div className="mt-6 flex h-full flex-1 flex-col space-y-4">
                                    <div className="flex h-full flex-col justify-between text-sm">
                                        <div className="flex flex-col space-y-4">
                                            {mainNavItems.map((item) => (
                                                <Link key={item.title} href={item.url} className="flex items-center space-x-2 font-medium">
                                                    {item.icon && <Icon iconNode={item.icon} className="h-5 w-5" />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}
                                            <Link href={route('notifications.index')} className="flex items-center space-x-2 font-medium">
                                                <Bell className="h-5 w-5" />
                                                <span>Notifications</span>
                                                {unread_notifications_count > 0 ? (
                                                    <span className="bg-primary text-primary-foreground rounded-full px-2 py-0.5 text-xs">
                                                        {unread_notifications_count}
                                                    </span>
                                                ) : null}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link href="/dashboard" prefetch className="flex items-center space-x-2">
                        <AppLogo />
                    </Link>

                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem key={index} className="relative flex h-full items-center">
                                        <Link
                                            href={item.url}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                page.url === item.url && activeItemStyles,
                                                'h-9 cursor-pointer px-3',
                                            )}
                                        >
                                            {item.icon && <Icon iconNode={item.icon} className="mr-2 h-4 w-4" />}
                                            {item.title}
                                        </Link>
                                        {page.url === item.url && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
                                        )}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex items-center space-x-2">
                        <Button variant="ghost" size="icon" asChild className="relative">
                            <Link href={route('notifications.index')} aria-label="Notifications">
                                <Bell className="size-5" />
                                {unread_notifications_count > 0 ? (
                                    <span className="bg-primary text-primary-foreground absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-4">
                                        {unread_notifications_count > 99 ? '99+' : unread_notifications_count}
                                    </span>
                                ) : null}
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" className="size-10 rounded-full p-1">
                                    <Avatar className="size-8 overflow-hidden rounded-full">
                                        <AvatarImage src={user.avatar} alt={user.name} />
                                        <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="border-sidebar-border/70 flex w-full border-b">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}

import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

function isNavActive(url: string, current: string, allUrls: string[]): boolean {
    if (current === url) {
        return true;
    }

    // Settings owned by Account group — never light Dashboard from /settings
    if (current.startsWith('/settings')) {
        return false;
    }

    if (!current.startsWith(`${url}/`)) {
        return false;
    }

    // Prefer longer / more specific nav matches (e.g. /transactions/import over /transactions)
    return !allUrls.some((other) => other !== url && other.length > url.length && (current === other || current.startsWith(`${other}/`)));
}

export function NavMain({ groups = [], items = [] }: { groups?: NavGroup[]; items?: NavItem[] }) {
    const page = usePage<SharedData>();
    const pendingDuplicates = page.props.pending_duplicate_count ?? 0;
    const resolvedGroups: NavGroup[] = groups.length > 0 ? groups : items.length > 0 ? [{ title: 'Platform', items }] : [];
    const allUrls = resolvedGroups.flatMap((group) => group.items.map((item) => item.url));

    return (
        <>
            {resolvedGroups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-0">
                    <SidebarGroupLabel className="mb-1 text-[10px] tracking-[0.16em] text-[#737373] uppercase">{group.title}</SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        {group.items.map((item) => {
                            const badge = item.badgeKey === 'pending_duplicate_count' && pendingDuplicates > 0 ? pendingDuplicates : null;
                            const active = isNavActive(item.url, page.url, allUrls);

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={active}
                                        className={cn(item.secondary && !active && 'cc-nav-secondary')}
                                        size={item.secondary ? 'sm' : 'default'}
                                    >
                                        <Link href={item.url} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                    {badge !== null ? <SidebarMenuBadge>{badge}</SidebarMenuBadge> : null}
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}

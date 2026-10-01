import { AdminNotificationsVisual } from '@/components/admin/visuals/AdminVisuals';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatAdminNotificationTime, formatUnreadBadge, type AdminNotificationItem } from '@/lib/admin-notifications';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CheckCircle2, Info, ShieldAlert } from 'lucide-react';
import { useMemo, useState } from 'react';

function SeverityIcon({ severity }: { severity: AdminNotificationItem['severity'] }) {
    if (severity === 'success') return <CheckCircle2 className="size-4" aria-hidden />;
    if (severity === 'warning') return <AlertTriangle className="size-4" aria-hidden />;
    if (severity === 'important') return <ShieldAlert className="size-4" aria-hidden />;
    return <Info className="size-4" aria-hidden />;
}

export function AdminNotificationBell() {
    const { admin_unread_notifications_count = 0, admin_notifications_preview = [] } = usePage<
        SharedData & {
            admin_unread_notifications_count?: number;
            admin_notifications_preview?: AdminNotificationItem[];
        }
    >().props;

    const [open, setOpen] = useState(false);
    const unreadLabel = formatUnreadBadge(admin_unread_notifications_count);
    const preview = useMemo(() => admin_notifications_preview ?? [], [admin_notifications_preview]);

    const markAllRead = () => {
        router.post(
            route('admin.notifications.read-all'),
            {},
            { preserveScroll: true, only: ['admin_unread_notifications_count', 'admin_notifications_preview', 'flash'] },
        );
    };

    const openNotification = (notification: AdminNotificationItem) => {
        setOpen(false);
        if (notification.is_unread) {
            router.post(
                route('admin.notifications.read', notification.id),
                {},
                {
                    preserveScroll: true,
                    only: ['admin_unread_notifications_count', 'admin_notifications_preview', 'flash'],
                    onSuccess: () => {
                        if (notification.action_url) {
                            router.visit(notification.action_url);
                        }
                    },
                },
            );
            return;
        }

        if (notification.action_url) {
            router.visit(notification.action_url);
        }
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'cc-admin-notification-trigger relative size-11 min-h-11 min-w-11',
                        admin_unread_notifications_count > 0 && 'cc-admin-notification-trigger--unread',
                    )}
                    aria-label={admin_unread_notifications_count > 0 ? `Notifications, ${admin_unread_notifications_count} unread` : 'Notifications'}
                >
                    <Bell className="size-5" aria-hidden />
                    {unreadLabel ? (
                        <span className="cc-admin-notification-badge absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-[#E94F09] px-1 text-[10px] leading-4 text-white">
                            {unreadLabel}
                        </span>
                    ) : null}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="cc-admin-notification-panel w-[min(100vw-1.5rem,22rem)] p-0 sm:w-[24rem]"
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <div className="flex items-center justify-between gap-2 px-3 py-2.5">
                    <DropdownMenuLabel className="p-0 text-sm font-semibold">Notifications</DropdownMenuLabel>
                    {admin_unread_notifications_count > 0 ? (
                        <Button type="button" variant="ghost" size="sm" className="h-8 px-2 text-xs" onClick={markAllRead}>
                            Mark all read
                        </Button>
                    ) : null}
                </div>
                <DropdownMenuSeparator className="m-0" />

                {preview.length === 0 ? (
                    <div className="flex flex-col items-center px-4 py-8 text-center">
                        <AdminNotificationsVisual className="mb-3 size-14" />
                        <p className="text-sm font-medium">No notifications yet</p>
                        <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                            System activity and important Admin alerts will appear here.
                        </p>
                    </div>
                ) : (
                    <ul className="max-h-[min(70vh,24rem)] overflow-y-auto py-1" aria-label="Recent admin notifications">
                        {preview.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    className={cn(
                                        'cc-admin-notification-item flex w-full gap-3 px-3 py-2.5 text-left transition-colors',
                                        notification.is_unread ? 'cc-admin-notification-item--unread' : '',
                                    )}
                                    onClick={() => openNotification(notification)}
                                >
                                    <span
                                        className={cn(
                                            'cc-admin-notification-icon mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg',
                                            `cc-admin-notification-icon--${notification.severity}`,
                                        )}
                                        aria-hidden
                                    >
                                        <SeverityIcon severity={notification.severity} />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-start justify-between gap-2">
                                            <span className="truncate text-sm font-medium">{notification.title}</span>
                                            {notification.is_unread ? <span className="sr-only">Unread</span> : null}
                                        </span>
                                        <span className="text-muted-foreground mt-0.5 line-clamp-2 text-xs leading-relaxed">
                                            {notification.message}
                                        </span>
                                        <span className="text-muted-foreground mt-1 block text-[11px]">
                                            {formatAdminNotificationTime(notification.created_at)}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <DropdownMenuSeparator className="m-0" />
                <div className="p-2">
                    <Button asChild variant="outline" size="sm" className="w-full">
                        <Link href={route('admin.notifications.index')} onClick={() => setOpen(false)}>
                            View all notifications
                        </Link>
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

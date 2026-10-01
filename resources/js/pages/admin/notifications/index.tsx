import { AdminNotificationsVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import AdminLayout from '@/layouts/admin-layout';
import { formatAdminNotificationTime, type AdminNotificationItem } from '@/lib/admin-notifications';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Info, MoreHorizontal, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Notifications', href: '/admin/notifications' },
];

type Props = {
    notifications: AdminNotificationItem[];
    grouped: Record<string, AdminNotificationItem[]>;
    filters: { filter: 'all' | 'unread' | 'important' };
    unread_count: number;
};

function SeverityIcon({ severity }: { severity: AdminNotificationItem['severity'] }) {
    if (severity === 'success') return <CheckCircle2 className="size-4" aria-hidden />;
    if (severity === 'warning') return <AlertTriangle className="size-4" aria-hidden />;
    if (severity === 'important') return <ShieldAlert className="size-4" aria-hidden />;
    return <Info className="size-4" aria-hidden />;
}

export default function AdminNotificationsIndex({ notifications, grouped, filters, unread_count }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [deleteTarget, setDeleteTarget] = useState<AdminNotificationItem | null>(null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const setFilter = (filter: Props['filters']['filter']) => {
        router.get(
            route('admin.notifications.index'),
            { filter: filter === 'all' ? undefined : filter },
            { preserveState: true, preserveScroll: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('admin.notifications.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Notifications" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="System"
                    title="Notifications"
                    description="System activity and important Admin alerts."
                    visual={<AdminNotificationsVisual />}
                    actions={
                        unread_count > 0 ? (
                            <Button type="button" variant="outline" onClick={() => router.post(route('admin.notifications.read-all'))}>
                                Mark all as read
                            </Button>
                        ) : null
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">{unread_count > 0 ? `${unread_count} unread` : 'All caught up'}</p>
                    <div className="cc-admin-period" role="group" aria-label="Notification filters">
                        {(['all', 'unread', 'important'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                className={cn('cc-admin-period__btn', filters.filter === value && 'is-active')}
                                onClick={() => setFilter(value)}
                            >
                                {value === 'all' ? 'All' : value === 'unread' ? 'Unread' : 'Important'}
                            </button>
                        ))}
                    </div>
                </div>

                {notifications.length === 0 ? (
                    <FintechEmpty
                        visual={<AdminNotificationsVisual className="size-16" />}
                        title="No notifications yet"
                        description="Operational Admin alerts will appear here when students register or system content changes."
                    />
                ) : (
                    <div className="space-y-6">
                        {Object.entries(grouped).map(([label, items]) => (
                            <section key={label} className="space-y-3">
                                <h2 className="text-muted-foreground text-xs tracking-[0.14em] uppercase">{label}</h2>
                                <ul className="space-y-2" aria-label={`${label} notifications`}>
                                    {items.map((notification) => (
                                        <li
                                            key={notification.id}
                                            className={cn(
                                                'cc-admin-notification-row flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-start sm:justify-between',
                                                notification.is_unread && 'cc-admin-notification-row--unread',
                                            )}
                                        >
                                            <div className="flex min-w-0 flex-1 gap-3">
                                                <span
                                                    className={cn(
                                                        'cc-admin-notification-icon mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                        `cc-admin-notification-icon--${notification.severity}`,
                                                    )}
                                                    aria-hidden
                                                >
                                                    <SeverityIcon severity={notification.severity} />
                                                </span>
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="font-medium">{notification.title}</p>
                                                        {notification.is_unread ? (
                                                            <Badge variant="outline" className="cc-admin-status cc-admin-status--active">
                                                                Unread
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="cc-admin-status cc-admin-status--muted">
                                                                Read
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="text-muted-foreground text-sm leading-relaxed">{notification.message}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {formatAdminNotificationTime(notification.created_at)}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                                {notification.action_url ? (
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link
                                                            href={notification.action_url}
                                                            onClick={() => {
                                                                if (notification.is_unread) {
                                                                    router.post(
                                                                        route('admin.notifications.read', notification.id),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            {notification.action_label ?? 'Open'}
                                                        </Link>
                                                    </Button>
                                                ) : null}

                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            aria-label={`More actions for ${notification.title}`}
                                                        >
                                                            <MoreHorizontal className="size-4" aria-hidden />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        {notification.is_unread ? (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        route('admin.notifications.read', notification.id),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                Mark as read
                                                            </DropdownMenuItem>
                                                        ) : (
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    router.post(
                                                                        route('admin.notifications.unread', notification.id),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                Mark as unread
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem onSelect={() => setDeleteTarget(notification)}>Delete</DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </FintechPage>

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete notification?</DialogTitle>
                        <DialogDescription>
                            Remove “{deleteTarget?.title}” from your Admin notification center. This does not undo the underlying system event.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeleteTarget(null)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

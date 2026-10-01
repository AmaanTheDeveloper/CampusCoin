import { FintechEmpty, FintechPage } from '@/components/fintech-page';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { AppNotification } from '@/types/budget';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

type Props = {
    notifications: AppNotification[];
};

function isRecurringApproval(notification: AppNotification): boolean {
    return notification.type === 'recurring_approval' || notification.type === 'recurring_approval_reminder';
}

export default function NotificationsIndex({ notifications }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [busyId, setBusyId] = useState<string | null>(null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const unreadCount = notifications.filter((n) => n.is_unread).length;

    const approve = (notification: AppNotification) => {
        if (!notification.occurrence_id || busyId !== null) {
            return;
        }
        setBusyId(notification.id);
        router.post(route('recurring-occurrences.approve', notification.occurrence_id), {}, { onFinish: () => setBusyId(null) });
    };

    const reject = (notification: AppNotification) => {
        if (!notification.occurrence_id || busyId !== null) {
            return;
        }
        setBusyId(notification.id);
        router.post(route('recurring-occurrences.reject', notification.occurrence_id), {}, { onFinish: () => setBusyId(null) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <FintechPage>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading title="Notifications" description="In-app alerts for budgets, income plans, and recurring payments" />
                    {unreadCount > 0 && (
                        <Button type="button" variant="outline" onClick={() => router.post(route('notifications.read-all'))}>
                            Mark all as read
                        </Button>
                    )}
                </div>

                {successMessage && (
                    <div role="status" className="border-primary/20 bg-primary/10 rounded-lg border px-4 py-3 text-sm">
                        {successMessage}
                    </div>
                )}

                {notifications.length === 0 ? (
                    <FintechEmpty
                        title="You're all caught up"
                        description="Budget alerts, income plans, and recurring payment approvals will appear here."
                    />
                ) : (
                    <ul className="space-y-3" aria-label="Notifications">
                        {notifications.map((notification) => (
                            <li
                                key={notification.id}
                                className={cn(
                                    'border-border rounded-xl border p-4',
                                    notification.is_unread ? 'bg-primary/5 border-primary/20' : 'bg-card',
                                )}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {notification.is_unread ? <Badge>Unread</Badge> : <Badge variant="secondary">Read</Badge>}
                                            {notification.title ? <span className="text-sm font-semibold">{notification.title}</span> : null}
                                            {notification.category_name && (
                                                <span className="text-muted-foreground text-xs">{notification.category_name}</span>
                                            )}
                                        </div>
                                        <p className="font-medium">{notification.message}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {notification.created_at ? new Date(notification.created_at).toLocaleString() : ''}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {isRecurringApproval(notification) && notification.occurrence_id ? (
                                            <>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={busyId !== null}
                                                    aria-busy={busyId === notification.id}
                                                    onClick={() => approve(notification)}
                                                >
                                                    Approve
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={busyId !== null}
                                                    aria-busy={busyId === notification.id}
                                                    onClick={() => reject(notification)}
                                                >
                                                    Reject
                                                </Button>
                                            </>
                                        ) : null}
                                        {notification.is_unread && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => router.post(route('notifications.read', notification.id))}
                                            >
                                                Mark as read
                                            </Button>
                                        )}
                                        {notification.budget_id && (
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={route('budgets.index')}>View budgets</Link>
                                            </Button>
                                        )}
                                        {!notification.budget_id && notification.action_url ? (
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={notification.action_url}>{notification.action_label ?? 'View details'}</Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </FintechPage>
        </AppLayout>
    );
}

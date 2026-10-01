export type AdminNotificationSeverity = 'info' | 'success' | 'warning' | 'important';

export type AdminNotificationItem = {
    id: string;
    type: string;
    title: string;
    message: string;
    severity: AdminNotificationSeverity;
    action_url: string | null;
    action_label: string | null;
    read_at: string | null;
    created_at: string | null;
    is_unread: boolean;
};

export function formatAdminNotificationTime(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    const diffMs = Date.now() - date.getTime();
    const minutes = Math.floor(diffMs / 60000);
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;

    return date.toLocaleString();
}

export function formatUnreadBadge(count: number): string {
    if (count <= 0) return '';
    if (count > 99) return '99+';
    return String(count);
}

import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminUsersVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { AdminUserListItem, Paginated } from '@/types/admin';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

type Props = {
    users: Paginated<AdminUserListItem>;
    filters: { search: string; status: 'all' | 'active' | 'disabled' };
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function AdminUsersIndex({ users, filters }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    useEffect(() => {
        setSearch(filters.search);
        setStatus(filters.status);
    }, [filters.search, filters.status]);

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();
        router.get(
            route('admin.users.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const setStatusFilter = (next: Props['filters']['status']) => {
        setStatus(next);
        router.get(
            route('admin.users.index'),
            { search: search || undefined, status: next === 'all' ? undefined : next },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Users" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Management"
                    title="Users"
                    description="Search and manage student accounts. Financial history is never deleted from admin actions."
                    visual={<AdminUsersVisual />}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="admin-user-search">Search</Label>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden />
                            <Input
                                id="admin-user-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Name or email"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <Button type="submit" className="cc-dash-btn-primary">Search</Button>
                </form>

                <div className="cc-admin-period" role="group" aria-label="Status filter">
                    {(['all', 'active', 'disabled'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            className={cn('cc-admin-period__btn', status === value && 'is-active')}
                            onClick={() => setStatusFilter(value)}
                        >
                            {value === 'all' ? 'All' : value === 'active' ? 'Active' : 'Disabled'}
                        </button>
                    ))}
                </div>

                {users.data.length === 0 ? (
                    <FintechEmpty title="No students found" description="Try adjusting search or status filters." />
                ) : (
                    <>
                        <div className="cc-admin-table-wrap hidden md:block">
                            <table className="cc-admin-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Transactions</th>
                                        <th>Joined</th>
                                        <th>Last activity</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.map((user) => (
                                        <tr key={user.id}>
                                            <td>
                                                <p className="font-medium">{user.name}</p>
                                                <p className="text-muted-foreground text-xs">{user.email}</p>
                                            </td>
                                            <td>
                                                <AdminStatusBadge status={user.status === 'active' ? 'active' : 'disabled'} />
                                            </td>
                                            <td className="tabular-nums">
                                                {user.transactions_count}
                                                <span className="text-muted-foreground text-xs">
                                                    {' '}
                                                    ({user.income_count} in / {user.expense_count} out)
                                                </span>
                                            </td>
                                            <td className="tabular-nums">{formatDate(user.joined_at)}</td>
                                            <td className="tabular-nums">{formatDate(user.last_activity_at)}</td>
                                            <td className="text-right">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={route('admin.users.show', user.id)}>View</Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="cc-admin-mobile-card-list grid gap-3 md:hidden">
                            {users.data.map((user) => (
                                <div key={user.id} className="cc-admin-mobile-card space-y-2">
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium" title={user.name}>
                                                {user.name}
                                            </p>
                                            <p className="cc-admin-mobile-card__email text-muted-foreground text-xs" title={user.email}>
                                                {user.email}
                                            </p>
                                        </div>
                                        <AdminStatusBadge status={user.status === 'active' ? 'active' : 'disabled'} />
                                    </div>
                                    <p className="cc-admin-mobile-card__meta text-muted-foreground text-xs">
                                        {user.transactions_count} transactions · Joined {formatDate(user.joined_at)}
                                    </p>
                                    <Button variant="outline" size="sm" asChild className="w-full max-w-full">
                                        <Link href={route('admin.users.show', user.id)}>View student</Link>
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <PaginationNav links={users.links} />
                    </>
                )}
            </FintechPage>
        </AdminLayout>
    );
}

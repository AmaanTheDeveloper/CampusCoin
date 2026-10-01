import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Landmark, Search } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Banks', href: '/admin/banks' },
];

type BankRow = {
    id: number;
    name: string;
    slug: string;
    reference_code: string | null;
    official_email: string;
    status: string;
    status_label: string;
    authorized_representative: string | null;
    products_count: number;
    applications_count: number;
    loans_count: number;
    memberships_count: number;
    documents_count?: number;
    created_at: string | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    banks: {
        data: BankRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    status_counts?: {
        pending: number;
        approved: number;
        rejected: number;
        suspended: number;
    };
    filters: { search: string; status: string };
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function AdminBanksIndex({
    eyebrow = 'Admin',
    title = 'Partner banks',
    description = 'Review bank registration requests and manage active partners.',
    banks,
    status_counts = { pending: 0, approved: 0, rejected: 0, suspended: 0 },
    filters,
}: Props) {
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
            route('admin.banks.index'),
            { search: search || undefined, status: status === 'all' ? undefined : status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const setStatusFilter = (next: string) => {
        setStatus(next);
        router.get(
            route('admin.banks.index'),
            { search: search || undefined, status: next === 'all' ? undefined : next },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Partner banks" />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <form onSubmit={applyFilters} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="admin-bank-search">Search</Label>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden />
                            <Input
                                id="admin-bank-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Name or email"
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <Button type="submit" className="cc-dash-btn-primary">
                        Search
                    </Button>
                </form>

                <div className="cc-admin-period" role="group" aria-label="Status filter">
                    {(
                        [
                            { value: 'all', label: 'All' },
                            { value: 'pending', label: `Pending requests (${status_counts.pending})` },
                            { value: 'under_review', label: 'Under review' },
                            { value: 'approved', label: `Active (${status_counts.approved})` },
                            { value: 'rejected', label: `Rejected (${status_counts.rejected})` },
                            { value: 'suspended', label: `Suspended (${status_counts.suspended})` },
                        ] as const
                    ).map((item) => (
                        <button
                            key={item.value}
                            type="button"
                            className={cn('cc-admin-period__btn', status === item.value && 'is-active')}
                            onClick={() => setStatusFilter(item.value)}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {banks.data.length === 0 ? (
                    <FintechEmpty title="No banks found" description="Try adjusting search or status filters." />
                ) : (
                    <>
                        <div className="grid gap-2">
                            {banks.data.map((bank) => (
                                <article
                                    key={bank.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold text-white">{bank.name}</p>
                                        <p className="cc-text-secondary text-sm">{bank.official_email}</p>
                                        <p className="cc-text-tertiary mt-1 text-xs">
                                            {bank.authorized_representative ?? '—'}
                                            {bank.reference_code ? ` · ${bank.reference_code}` : ''}
                                        </p>
                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                            <LoanStatusBadge status={bank.status} label={bank.status_label} />
                                            <span className="cc-text-tertiary text-xs">
                                                {bank.documents_count ?? 0} docs · {bank.products_count} products · Submitted{' '}
                                                {formatDate(bank.created_at)}
                                            </span>
                                        </div>
                                    </div>
                                    <Button asChild size="sm" className="cc-dash-btn-primary">
                                        <Link href={route('admin.banks.show', bank.id)}>Review</Link>
                                    </Button>
                                </article>
                            ))}
                        </div>
                        <PaginationNav links={banks.links} />
                    </>
                )}
            </FintechPage>
        </AdminLayout>
    );
}

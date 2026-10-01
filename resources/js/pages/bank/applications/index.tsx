import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Search } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/bank' },
    { title: 'Applications', href: '/bank/applications' },
];

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'under_review', label: 'Under review' },
    { value: 'more_information_required', label: 'Info required' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
] as const;

type ApplicationRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    requested_amount: string;
    requested_tenure_months: number;
    student: { id: number | null; name: string | null; email: string | null };
    product: string | null;
    submitted_at: string | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    applications: {
        data: ApplicationRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string; search: string };
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function BankApplicationsIndex({
    eyebrow = 'Applications',
    title = 'Loan applications',
    description = 'Review and decide on student loan applications.',
    bank,
    applications,
    filters,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        setSearch(filters.search ?? '');
    }, [filters.search]);

    const applyFilters = (next: { status?: string; search?: string }) => {
        const status = next.status ?? filters.status;
        const q = next.search ?? search;
        router.get(
            route('bank.applications.index'),
            {
                status: status === 'all' ? undefined : status,
                search: q.trim() ? q.trim() : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const onSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const filteredEmpty = applications.data.length === 0 && (filters.status !== 'all' || Boolean(filters.search));

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`Applications · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<FileText className="size-14 text-[#E94F09]" aria-hidden />}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                <form onSubmit={onSearch} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="grid min-w-0 flex-1 gap-1.5">
                        <Label htmlFor="app-search">Search</Label>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#737373]" aria-hidden />
                            <Input
                                id="app-search"
                                className="cc-field pl-9"
                                placeholder="Reference, student, or product"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>
                    <Button type="submit" variant="outline" className="cc-dash-btn-secondary min-h-11">
                        Search
                    </Button>
                </form>

                <div className="cc-admin-period flex flex-wrap gap-1" role="group" aria-label="Status filter">
                    {STATUS_FILTERS.map((item) => {
                        const active = filters.status === item.value;
                        return (
                            <button
                                key={item.value}
                                type="button"
                                className={cn('cc-admin-period__btn', active && 'is-active')}
                                aria-pressed={active}
                                onClick={() => applyFilters({ status: item.value })}
                            >
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {applications.data.length === 0 ? (
                    <FintechEmpty
                        title={filteredEmpty ? 'No matching applications' : 'No applications'}
                        description={
                            filteredEmpty
                                ? 'Try another status filter or clear the search.'
                                : 'Submitted student applications will appear here.'
                        }
                    />
                ) : (
                    <>
                        <div className="grid gap-2">
                            {applications.data.map((row) => (
                                <article
                                    key={row.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-white">{row.reference}</p>
                                            <LoanStatusBadge status={row.status} label={row.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">
                                            {row.student.name} · {row.product ?? 'Product'}
                                        </p>
                                        <p className="cc-text-tertiary mt-1 text-xs">Submitted {formatDate(row.submitted_at)}</p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="text-right">
                                            <p className="font-medium tabular-nums text-white">{formatMoney(row.requested_amount)}</p>
                                            <p className="cc-text-tertiary text-xs">{row.requested_tenure_months} months</p>
                                        </div>
                                        <Button asChild size="sm" className="cc-dash-btn-primary min-h-11">
                                            <Link href={route('bank.applications.show', row.id)}>Review</Link>
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <PaginationNav links={applications.links} />
                    </>
                )}
            </FintechPage>
        </BankLayout>
    );
}

import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/bank' },
    { title: 'Loans', href: '/bank/loans' },
];

const STATUS_FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'agreement_pending', label: 'Agreement pending' },
    { value: 'ready_for_disbursement', label: 'Ready to disburse' },
    { value: 'disbursed', label: 'Disbursed' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
] as const;

type LoanRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    principal_amount: string;
    outstanding_balance: string;
    student: string | null;
    product: string | null;
    disbursed_at: string | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    loans: {
        data: LoanRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string };
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function BankLoansIndex({
    eyebrow = 'Loans',
    title = 'Booked loans',
    description = 'Track agreements, disbursements, and active student loans.',
    bank,
    loans,
    filters,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    const setStatus = (status: string) => {
        router.get(
            route('bank.loans.index'),
            { status: status === 'all' ? undefined : status },
            { preserveState: true, preserveScroll: true },
        );
    };

    const filteredEmpty = loans.data.length === 0 && filters.status !== 'all';

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`Loans · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<HandCoins className="size-14 text-[#E94F09]" aria-hidden />}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                <div className="cc-admin-period flex flex-wrap gap-1" role="group" aria-label="Status filter">
                    {STATUS_FILTERS.map((item) => {
                        const active = filters.status === item.value;
                        return (
                            <button
                                key={item.value}
                                type="button"
                                className={cn('cc-admin-period__btn', active && 'is-active')}
                                aria-pressed={active}
                                onClick={() => setStatus(item.value)}
                            >
                                {item.label}
                            </button>
                        );
                    })}
                </div>

                {loans.data.length === 0 ? (
                    <FintechEmpty
                        title={filteredEmpty ? 'No matching loans' : 'No loans'}
                        description={
                            filteredEmpty
                                ? 'Try another status filter.'
                                : 'Approved applications will create loans here.'
                        }
                    />
                ) : (
                    <>
                        <div className="grid gap-2">
                            {loans.data.map((row) => (
                                <article
                                    key={row.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-white">{row.reference}</p>
                                            <LoanStatusBadge status={row.status} label={row.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">
                                            {row.student} · {row.product ?? 'Product'}
                                        </p>
                                        <p className="cc-text-tertiary mt-1 text-xs">
                                            {row.disbursed_at ? `Disbursed ${formatDate(row.disbursed_at)}` : 'Not disbursed yet'}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div className="text-right text-sm">
                                            <p className="tabular-nums text-white">{formatMoney(row.principal_amount)}</p>
                                            <p className="cc-text-tertiary text-xs">Outstanding {formatMoney(row.outstanding_balance)}</p>
                                        </div>
                                        <Button asChild size="sm" className="cc-dash-btn-primary min-h-11">
                                            <Link href={route('bank.loans.show', row.id)}>Open</Link>
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                        <PaginationNav links={loans.links} />
                    </>
                )}
            </FintechPage>
        </BankLayout>
    );
}

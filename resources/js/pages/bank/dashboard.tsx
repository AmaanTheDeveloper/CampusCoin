import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Clock, FileText, HandCoins } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/bank' }];

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string; status: string; status_label: string };
    stats: {
        new_applications: number;
        under_review: number;
        more_information_required: number;
        approved_applications: number;
        pending_disbursement: number;
        active_loans: number;
        overdue_installments: number;
        payment_issues: number;
        published_products: number;
        pending_applications?: number;
        products_total?: number;
    };
    recent_applications: {
        id: number;
        reference: string;
        status: string;
        status_label: string;
        requested_amount: string;
        student: string | null;
        product: string | null;
        submitted_at: string | null;
    }[];
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function BankDashboard({
    eyebrow = 'Bank portal',
    title,
    description = 'Review applications, manage products, and confirm disbursements.',
    bank,
    stats,
    recent_applications,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    const queueTiles = [
        {
            label: 'New applications',
            value: stats.new_applications,
            href: route('bank.applications.index', { status: 'submitted' }),
            icon: FileText,
        },
        {
            label: 'Under review',
            value: stats.under_review,
            href: route('bank.applications.index', { status: 'under_review' }),
            icon: Clock,
        },
        {
            label: 'Info required',
            value: stats.more_information_required,
            href: route('bank.applications.index', { status: 'more_information_required' }),
            icon: AlertTriangle,
        },
        {
            label: 'Approved apps',
            value: stats.approved_applications,
            href: route('bank.applications.index', { status: 'approved' }),
            icon: FileText,
        },
        {
            label: 'Pending disbursement',
            value: stats.pending_disbursement,
            href: route('bank.loans.index', { status: 'ready_for_disbursement' }),
            icon: HandCoins,
        },
        {
            label: 'Active loans',
            value: stats.active_loans,
            href: route('bank.loans.index', { status: 'active' }),
            icon: HandCoins,
        },
        {
            label: 'Overdue installments',
            value: stats.overdue_installments,
            href: route('bank.loans.index'),
            icon: AlertTriangle,
        },
        {
            label: 'Payment issues',
            value: stats.payment_issues,
            href: route('bank.loans.index'),
            icon: AlertTriangle,
        },
    ];

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`${bank.name} · Bank`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? bank.name}
                    description={description}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('bank.products.index')}>
                                    Products ({stats.published_products})
                                </Link>
                            </Button>
                            <Button asChild className="cc-dash-btn-primary">
                                <Link href={route('bank.applications.index')}>Review applications</Link>
                            </Button>
                        </div>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                <div className="flex flex-wrap items-center gap-2">
                    <span className="cc-text-secondary text-sm">Bank status</span>
                    <LoanStatusBadge status={bank.status} label={bank.status_label} />
                </div>

                {(stats.products_total ?? 0) === 0 ? (
                    <FintechPanel className="border-[#E94F09]/25">
                        <FintechSectionHeader title="Welcome to the Bank Portal" />
                        <p className="cc-text-secondary mt-2 text-sm">Complete these steps to start receiving student applications.</p>
                        <ol className="mt-4 space-y-2 text-sm">
                            <li className="flex gap-2">
                                <span className="text-[#E94F09]">○</span>
                                <span>Confirm bank profile details are correct</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-[#E94F09]">○</span>
                                <span>Create a loan product</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-[#E94F09]">○</span>
                                <span>Publish the product for students to discover</span>
                            </li>
                            <li className="flex gap-2">
                                <span className="text-[#E94F09]">○</span>
                                <span>Review incoming applications</span>
                            </li>
                        </ol>
                        <Button asChild className="cc-dash-btn-primary mt-4 min-h-11">
                            <Link href={route('bank.products.create')}>Create loan product</Link>
                        </Button>
                    </FintechPanel>
                ) : null}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {queueTiles.map((tile) => {
                        const Icon = tile.icon;
                        return (
                            <Link
                                key={tile.label}
                                href={tile.href}
                                className="cc-elev-2 block rounded-[0.85rem] p-4 transition hover:bg-white/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E94F09]"
                            >
                                <div className="flex items-center gap-2">
                                    <Icon className="size-4 text-[#E94F09]" aria-hidden />
                                    <p className="cc-text-tertiary text-xs">{tile.label}</p>
                                </div>
                                <p className="mt-2 text-2xl font-semibold tabular-nums text-white">{tile.value}</p>
                            </Link>
                        );
                    })}
                </div>

                <FintechPanel>
                    <FintechSectionHeader
                        title="Recent applications"
                        action={
                            <Button asChild variant="outline" size="sm" className="cc-dash-btn-secondary">
                                <Link href={route('bank.applications.index')}>View all</Link>
                            </Button>
                        }
                    />
                    {recent_applications.length === 0 ? (
                        <div className="mt-4">
                            <FintechEmpty title="No applications yet" description="Submitted student applications will appear here." />
                        </div>
                    ) : (
                        <div className="mt-4 grid gap-2">
                            {recent_applications.map((row) => (
                                <Link
                                    key={row.id}
                                    href={route('bank.applications.show', row.id)}
                                    className="cc-elev-2 flex flex-col gap-2 rounded-[var(--radius)] p-3 transition hover:bg-white/5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-white">{row.reference}</p>
                                            <LoanStatusBadge status={row.status} label={row.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-xs">
                                            {[row.student, row.product].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3 text-sm">
                                        <span className="tabular-nums">{formatMoney(row.requested_amount)}</span>
                                        <span className="cc-text-tertiary text-xs tabular-nums">{formatDate(row.submitted_at)}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </FintechPanel>
            </FintechPage>
        </BankLayout>
    );
}

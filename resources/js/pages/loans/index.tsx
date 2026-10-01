import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, FileText, HandCoins, Landmark } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Loans', href: '/loans' }];

type ApplicationRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    requested_amount: string;
    product: string | null;
    bank: string | null;
};

type LoanRow = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    outstanding_balance: string;
    principal_amount?: string;
    product: string | null;
    bank: string | null;
    completed_at?: string | null;
    next_installment?: {
        id: number;
        installment_number: number;
        due_date: string | null;
        remaining: string;
        status: string;
        status_label: string;
        can_pay: boolean;
    } | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    applications: ApplicationRow[];
    active_loans: LoanRow[];
    completed_loans?: LoanRow[];
};

function formatDate(value: string | null | undefined) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

export default function LoansIndex({
    eyebrow = 'Student loans',
    title = 'Loan center',
    description = 'Browse products, track applications, and manage active loans.',
    applications,
    active_loans,
    completed_loans = [],
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const spotlight = useMemo(() => {
        const withPay = active_loans.find((loan) => loan.next_installment?.can_pay);
        return withPay ?? active_loans[0] ?? null;
    }, [active_loans]);

    const totalOutstanding = useMemo(
        () => active_loans.reduce((sum, loan) => sum + Number(loan.outstanding_balance || 0), 0),
        [active_loans],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan center" />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <Button asChild className="cc-dash-btn-primary">
                            <Link href={route('loans.products.index')}>Browse products</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {spotlight ? (
                    <FintechPanel className="cc-card-hero border-[#E94F09]/20">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div className="min-w-0 space-y-3">
                                <p className="cc-label text-[#E94F09]">Focus loan</p>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-xl font-semibold tracking-tight text-white md:text-2xl">{spotlight.reference}</h2>
                                    <LoanStatusBadge status={spotlight.status} label={spotlight.status_label} />
                                </div>
                                <p className="cc-text-secondary text-sm">
                                    {[spotlight.product, spotlight.bank].filter(Boolean).join(' · ') || 'Active loan'}
                                </p>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div>
                                        <p className="cc-text-tertiary text-xs">Outstanding</p>
                                        <p className="mt-1 text-lg font-semibold tabular-nums text-white">
                                            {formatMoney(spotlight.outstanding_balance)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="cc-text-tertiary text-xs">Next installment</p>
                                        <p className="mt-1 text-lg font-semibold tabular-nums text-white">
                                            {spotlight.next_installment
                                                ? formatMoney(spotlight.next_installment.remaining)
                                                : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="cc-text-tertiary text-xs">Due date</p>
                                        <p className="mt-1 font-medium text-white">
                                            {formatDate(spotlight.next_installment?.due_date)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="cc-text-tertiary text-xs">Payment status</p>
                                        <div className="mt-1">
                                            {spotlight.next_installment ? (
                                                <LoanStatusBadge
                                                    status={spotlight.next_installment.status}
                                                    label={spotlight.next_installment.status_label}
                                                />
                                            ) : (
                                                <span className="cc-text-secondary text-sm">No installment yet</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {spotlight.next_installment?.can_pay ? (
                                    <Button asChild className="cc-dash-btn-primary min-h-11">
                                        <Link href={route('loans.active.show', spotlight.id)}>Pay installment</Link>
                                    </Button>
                                ) : null}
                                <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11">
                                    <Link href={route('loans.active.show', spotlight.id)}>View loan</Link>
                                </Button>
                            </div>
                        </div>
                        {active_loans.length > 1 ? (
                            <p className="cc-text-tertiary mt-4 text-xs">
                                Total outstanding across {active_loans.length} loans: {formatMoney(String(totalOutstanding))}
                            </p>
                        ) : null}
                    </FintechPanel>
                ) : null}

                <FintechPanel>
                    <FintechSectionHeader
                        title="Active loans"
                        meta={`${active_loans.length} loan${active_loans.length === 1 ? '' : 's'}`}
                        action={
                            <Button asChild variant="outline" size="sm" className="cc-dash-btn-secondary">
                                <Link href={route('loans.products.index')}>
                                    <HandCoins className="mr-2 size-4" aria-hidden />
                                    New loan
                                </Link>
                            </Button>
                        }
                    />
                    {active_loans.length === 0 ? (
                        <div className="mt-4">
                            <FintechEmpty
                                visual={<HandCoins aria-hidden />}
                                title="No active loans"
                                description="Browse partner bank products, apply, and track status here once approved."
                                action={
                                    <Button asChild className="cc-dash-btn-primary">
                                        <Link href={route('loans.products.index')}>Explore products</Link>
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <div className="mt-4 grid gap-3">
                            {active_loans.map((loan) => (
                                <article
                                    key={loan.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold text-white">{loan.reference}</h3>
                                            <LoanStatusBadge status={loan.status} label={loan.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">
                                            {[loan.product, loan.bank].filter(Boolean).join(' · ') || 'Loan'}
                                        </p>
                                        {loan.next_installment ? (
                                            <p className="cc-text-tertiary text-xs">
                                                Next · {formatMoney(loan.next_installment.remaining)} ·{' '}
                                                {formatDate(loan.next_installment.due_date)}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <div>
                                            <p className="cc-text-tertiary text-xs">Outstanding</p>
                                            <p className="font-medium text-white tabular-nums">{formatMoney(loan.outstanding_balance)}</p>
                                        </div>
                                        {loan.next_installment?.can_pay ? (
                                            <Button asChild size="sm" className="cc-dash-btn-primary">
                                                <Link href={route('loans.active.show', loan.id)}>Pay</Link>
                                            </Button>
                                        ) : (
                                            <Button asChild size="sm" className="cc-dash-btn-primary">
                                                <Link href={route('loans.active.show', loan.id)}>View loan</Link>
                                            </Button>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </FintechPanel>

                <FintechPanel>
                    <FintechSectionHeader
                        title="Applications"
                        meta={`${applications.length} recent`}
                        action={
                            <Button asChild variant="outline" size="sm" className="cc-dash-btn-secondary">
                                <Link href={route('loans.products.index')}>
                                    <FileText className="mr-2 size-4" aria-hidden />
                                    Apply
                                </Link>
                            </Button>
                        }
                    />
                    {applications.length === 0 ? (
                        <div className="mt-4">
                            <FintechEmpty
                                visual={<FileText aria-hidden />}
                                title="No applications yet"
                                description="Pick a product and start a draft application."
                                action={
                                    <Button asChild className="cc-dash-btn-primary">
                                        <Link href={route('loans.products.index')}>Browse products</Link>
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <div className="mt-4 grid gap-3">
                            {applications.map((application) => (
                                <article
                                    key={application.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold text-white">{application.reference}</h3>
                                            <LoanStatusBadge status={application.status} label={application.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">
                                            {[application.product, application.bank].filter(Boolean).join(' · ') || 'Application'}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-4">
                                        <div>
                                            <p className="cc-text-tertiary text-xs">Requested</p>
                                            <p className="font-medium text-white tabular-nums">{formatMoney(application.requested_amount)}</p>
                                        </div>
                                        <Button asChild size="sm" variant="outline" className="cc-dash-btn-secondary">
                                            <Link href={route('loans.applications.show', application.id)}>
                                                {application.status === 'draft' ? 'Resume draft' : 'Open'}
                                            </Link>
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </FintechPanel>

                {completed_loans.length > 0 ? (
                    <FintechPanel>
                        <FintechSectionHeader
                            title="Completed"
                            meta={`${completed_loans.length} loan${completed_loans.length === 1 ? '' : 's'}`}
                        />
                        <div className="mt-4 grid gap-3">
                            {completed_loans.map((loan) => (
                                <article
                                    key={loan.id}
                                    className="cc-elev-2 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <CheckCircle2 className="size-4 text-emerald-400" aria-hidden />
                                            <h3 className="font-semibold text-white">{loan.reference}</h3>
                                            <LoanStatusBadge status={loan.status} label={loan.status_label} />
                                        </div>
                                        <p className="cc-text-secondary text-sm">
                                            {[loan.product, loan.bank].filter(Boolean).join(' · ') || 'Loan'}
                                            {loan.completed_at ? ` · Completed ${formatDate(loan.completed_at)}` : ''}
                                        </p>
                                    </div>
                                    <Button asChild size="sm" variant="outline" className="cc-dash-btn-secondary">
                                        <Link href={route('loans.active.show', loan.id)}>View history</Link>
                                    </Button>
                                </article>
                            ))}
                        </div>
                    </FintechPanel>
                ) : null}
            </FintechPage>
        </AppLayout>
    );
}

import { FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

type Installment = {
    id: number;
    installment_number: number;
    due_date: string | null;
    amount_due: string;
    amount_paid: string;
    remaining: string;
    status: string;
    status_label: string;
};

type Loan = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    principal_amount: string;
    tenure_months: number;
    annual_rate_percent: string;
    installment_amount: string;
    total_repayable: string;
    outstanding_balance: string;
    agreement_accepted_at: string | null;
    disbursed_at: string | null;
    completed_at: string | null;
    product: { id: number; name: string; allows_partial_payments: boolean } | null;
    bank: { id: number; name: string } | null;
    disbursement: { status: string; reference: string | null; confirmed_at: string | null; amount?: string; transaction_id?: number | null } | null;
    installments: Installment[];
    payments: {
        id: number;
        reference: string;
        amount: string;
        status: string;
        status_label: string;
        paid_at: string | null;
        transaction_id?: number | null;
    }[];
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    loan: Loan;
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function canPayInstallment(loan: Loan, row: Installment) {
    return (
        ['due', 'partially_paid', 'overdue'].includes(row.status) &&
        Number(row.remaining) > 0 &&
        Boolean(loan.disbursed_at) &&
        !loan.completed_at
    );
}

export default function ActiveLoanShow({
    eyebrow = 'Active loan',
    title,
    description = 'Accept the agreement, review your schedule, and pay installments.',
    loan,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);
    const [paymentResult, setPaymentResult] = useState(flash?.payment_result ?? null);
    const [accepting, setAccepting] = useState(false);
    const [payingId, setPayingId] = useState<number | null>(null);
    const [payTarget, setPayTarget] = useState<Installment | null>(null);
    const [payAmount, setPayAmount] = useState('');

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
        setPaymentResult(flash?.payment_result ?? null);
    }, [flash?.success, flash?.error, flash?.payment_result]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: '/loans' },
        { title: loan.reference, href: `/loans/active/${loan.id}` },
    ];

    const needsAgreement = loan.status === 'agreement_pending' && !loan.agreement_accepted_at;
    const awaitingDisbursement =
        Boolean(loan.agreement_accepted_at) &&
        !loan.disbursed_at &&
        ['agreement_pending', 'ready_for_disbursement', 'approved'].includes(loan.status);

    const nextPayable = useMemo(
        () => loan.installments.find((row) => canPayInstallment(loan, row)) ?? null,
        [loan],
    );

    const timeline = useMemo(() => {
        const steps = [
            {
                key: 'approved',
                label: 'Approved',
                done: true,
                detail: loan.product?.name ?? 'Loan terms set',
            },
            {
                key: 'agreement',
                label: 'Agreement',
                done: Boolean(loan.agreement_accepted_at),
                detail: loan.agreement_accepted_at ? formatDate(loan.agreement_accepted_at) : 'Awaiting your acceptance',
            },
            {
                key: 'disbursed',
                label: 'Disbursed',
                done: Boolean(loan.disbursed_at),
                detail: loan.disbursed_at ? formatDate(loan.disbursed_at) : 'Waiting for bank confirmation',
            },
            {
                key: 'repayment',
                label: 'Active repayment',
                done: ['disbursed', 'active', 'completed'].includes(loan.status) && Boolean(loan.disbursed_at),
                detail: loan.completed_at ? `Completed ${formatDate(loan.completed_at)}` : 'Pay installments as they become due',
            },
            {
                key: 'completed',
                label: 'Completed',
                done: Boolean(loan.completed_at) || loan.status === 'completed',
                detail: loan.completed_at ? formatDate(loan.completed_at) : 'After all installments are paid',
            },
        ];
        return steps;
    }, [loan]);

    const progressPaid = useMemo(() => {
        const total = Number(loan.total_repayable || 0);
        const outstanding = Number(loan.outstanding_balance || 0);
        if (total <= 0) return 0;
        return Math.max(0, Math.min(100, Math.round(((total - outstanding) / total) * 100)));
    }, [loan]);

    const acceptAgreement = () => {
        if (accepting) return;
        if (!confirm('Accept the loan agreement? This confirms you agree to the terms.')) return;
        setAccepting(true);
        router.post(route('loans.active.accept-agreement', loan.id), {}, { onFinish: () => setAccepting(false) });
    };

    const openPay = (installment: Installment) => {
        setPayTarget(installment);
        setPayAmount(installment.remaining || installment.amount_due);
    };

    const submitPay = (e: FormEvent) => {
        e.preventDefault();
        if (!payTarget || payingId !== null) return;
        if (!payAmount || Number(payAmount) <= 0) return;

        setPayingId(payTarget.id);
        router.post(
            route('loans.active.pay', { loan: loan.id, installment: payTarget.id }),
            { amount: payAmount },
            {
                onFinish: () => {
                    setPayingId(null);
                    setPayTarget(null);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={loan.reference} />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? loan.reference}
                    description={description}
                    visual={<HandCoins className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {nextPayable ? (
                                <Button type="button" className="cc-dash-btn-primary" onClick={() => openPay(nextPayable)}>
                                    Pay installment
                                </Button>
                            ) : null}
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('loans.index')}>Loan center</Link>
                            </Button>
                        </div>
                    }
                />

                {successMessage && !paymentResult ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage && !paymentResult ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                {paymentResult ? (
                    <FintechPanel
                        className={
                            paymentResult.outcome === 'succeeded'
                                ? 'border-emerald-500/30 bg-emerald-500/10'
                                : 'border-red-500/30 bg-red-500/10'
                        }
                    >
                        <FintechSectionHeader
                            title={paymentResult.outcome === 'succeeded' ? 'Payment successful' : 'Payment failed'}
                        />
                        <dl className="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                            <div>
                                <dt className="cc-text-tertiary text-xs">Amount</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">
                                    {paymentResult.amount ? formatMoney(paymentResult.amount) : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Installment</dt>
                                <dd className="mt-1 font-medium text-white">
                                    {paymentResult.installment_number != null ? `#${paymentResult.installment_number}` : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Date</dt>
                                <dd className="mt-1 tabular-nums text-white">{formatDate(paymentResult.paid_at ?? null)}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Reference</dt>
                                <dd className="mt-1 font-medium text-white">{paymentResult.reference ?? '—'}</dd>
                            </div>
                            {paymentResult.outcome === 'succeeded' ? (
                                <div className="sm:col-span-2">
                                    <dt className="cc-text-tertiary text-xs">Remaining balance</dt>
                                    <dd className="mt-1 font-medium tabular-nums text-white">
                                        {paymentResult.remaining_balance
                                            ? formatMoney(paymentResult.remaining_balance)
                                            : formatMoney(loan.outstanding_balance)}
                                    </dd>
                                </div>
                            ) : (
                                <div className="sm:col-span-2">
                                    <dt className="cc-text-tertiary text-xs">Reason</dt>
                                    <dd className="mt-1 text-white">{paymentResult.reason ?? 'Payment could not be completed.'}</dd>
                                    <p className="cc-text-secondary mt-2 text-xs">No installment was marked as paid. You can retry when ready.</p>
                                    {nextPayable ? (
                                        <Button type="button" className="cc-dash-btn-primary mt-3 min-h-11" onClick={() => openPay(nextPayable)}>
                                            Retry payment
                                        </Button>
                                    ) : null}
                                </div>
                            )}
                        </dl>
                    </FintechPanel>
                ) : null}

                {needsAgreement ? (
                    <FintechPanel className="border-[#E94F09]/25">
                        <FintechSectionHeader title="Loan agreement" />
                        <p className="cc-text-secondary mt-3 text-sm">
                            Review and accept the agreement for {loan.product?.name ?? 'this loan'} from {loan.bank?.name ?? 'the bank'} before
                            disbursement can proceed.
                        </p>
                        <Button type="button" className="cc-dash-btn-primary mt-4 min-h-11" disabled={accepting} onClick={acceptAgreement}>
                            {accepting ? 'Accepting…' : 'Accept agreement'}
                        </Button>
                    </FintechPanel>
                ) : null}

                {awaitingDisbursement && !needsAgreement ? (
                    <FintechPanel>
                        <FintechSectionHeader title="Awaiting disbursement" />
                        <p className="cc-text-secondary mt-3 text-sm">
                            Your agreement is accepted. The bank still needs to confirm disbursement before repayment begins. Your schedule will
                            become payable after funds are marked as disbursed.
                        </p>
                    </FintechPanel>
                ) : null}

                {loan.disbursed_at && loan.disbursement ? (
                    <FintechPanel className="border-emerald-500/20 bg-emerald-500/5">
                        <FintechSectionHeader title="Loan disbursed" />
                        <p className="cc-text-secondary mt-3 text-sm">
                            The bank has sent the loan funds. This appears in your financial activity as a loan inflow — not earned income.
                        </p>
                        <dl className="mt-4 grid gap-3 sm:grid-cols-3 text-sm">
                            <div>
                                <dt className="cc-text-tertiary text-xs">Disbursed</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">
                                    {formatMoney(loan.disbursement.amount ?? loan.principal_amount)}
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Date</dt>
                                <dd className="mt-1 tabular-nums text-white">{formatDate(loan.disbursed_at)}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Reference</dt>
                                <dd className="mt-1 font-medium text-white">{loan.disbursement.reference ?? '—'}</dd>
                            </div>
                        </dl>
                        {loan.disbursement.transaction_id ? (
                            <Button asChild variant="outline" className="cc-dash-btn-secondary mt-4 min-h-11">
                                <Link href={route('transactions.show', loan.disbursement.transaction_id)}>View transaction</Link>
                            </Button>
                        ) : null}
                    </FintechPanel>
                ) : null}

                {loan.completed_at ? (
                    <FintechFlash>This loan is completed. Outstanding balance is {formatMoney(loan.outstanding_balance)}.</FintechFlash>
                ) : null}

                {nextPayable && !needsAgreement ? (
                    <FintechPanel className="border-[#E94F09]/20 bg-[rgba(233,79,9,0.06)]">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="cc-label text-[#E94F09]">Next payment</p>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <p className="text-2xl font-semibold tabular-nums text-white">{formatMoney(nextPayable.remaining)}</p>
                                    <LoanStatusBadge status={nextPayable.status} label={nextPayable.status_label} />
                                </div>
                                <p className="cc-text-secondary mt-2 text-sm">
                                    Installment #{nextPayable.installment_number} · Due {formatDate(nextPayable.due_date)}
                                </p>
                            </div>
                            <Button type="button" className="cc-dash-btn-primary min-h-11" onClick={() => openPay(nextPayable)}>
                                Pay installment
                            </Button>
                        </div>
                    </FintechPanel>
                ) : null}

                {payTarget ? (
                    <FintechPanel>
                        <FintechSectionHeader title={`Pay installment #${payTarget.installment_number}`} />
                        <form onSubmit={submitPay} className="mt-4 max-w-md space-y-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="pay-amount">Amount</Label>
                                <Input
                                    id="pay-amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={payTarget.remaining}
                                    value={payAmount}
                                    onChange={(e) => setPayAmount(e.target.value)}
                                    readOnly={!loan.product?.allows_partial_payments}
                                    required
                                    className="cc-field"
                                />
                                <p className="cc-text-tertiary text-xs">
                                    Remaining due: {formatMoney(payTarget.remaining)}
                                    {loan.product?.allows_partial_payments ? ' · Partial payments allowed' : ' · Full remaining amount required'}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" className="cc-dash-btn-primary" disabled={payingId !== null}>
                                    {payingId === payTarget.id ? 'Starting checkout…' : 'Continue to payment'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="cc-dash-btn-secondary"
                                    disabled={payingId !== null}
                                    onClick={() => setPayTarget(null)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </FintechPanel>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2">
                        <FintechSectionHeader
                            title="Loan summary"
                            meta={<LoanStatusBadge status={loan.status} label={loan.status_label} />}
                        />
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="cc-text-tertiary text-xs">Product</dt>
                                <dd className="mt-1 font-medium text-white">{loan.product?.name ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Bank</dt>
                                <dd className="mt-1 font-medium text-white">{loan.bank?.name ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Principal</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">{formatMoney(loan.principal_amount)}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Outstanding</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">{formatMoney(loan.outstanding_balance)}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Installment</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">{formatMoney(loan.installment_amount)}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Rate / tenure</dt>
                                <dd className="mt-1 font-medium text-white">
                                    {loan.annual_rate_percent}% · {loan.tenure_months} months
                                </dd>
                            </div>
                        </dl>
                        <div className="mt-5">
                            <div className="mb-2 flex items-center justify-between text-xs">
                                <span className="cc-text-tertiary">Repayment progress</span>
                                <span className="tabular-nums text-white">{progressPaid}%</span>
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Repayment progress" aria-valuenow={progressPaid} aria-valuemin={0} aria-valuemax={100}>
                                <div className="h-full rounded-full bg-[#E94F09]" style={{ width: `${progressPaid}%` }} />
                            </div>
                        </div>
                    </FintechPanel>

                    <FintechPanel>
                        <FintechSectionHeader title="Timeline" />
                        <ol className="mt-4 space-y-3">
                            {timeline.map((step, index) => (
                                <li key={step.key} className="flex gap-3">
                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold',
                                            step.done
                                                ? 'border-[#E94F09] bg-[#E94F09]/20 text-[#ffb089]'
                                                : 'border-white/15 text-[#737373]',
                                        )}
                                        aria-hidden
                                    >
                                        {index + 1}
                                    </span>
                                    <div>
                                        <p className={cn('text-sm font-medium', step.done ? 'text-white' : 'text-[#a3a3a3]')}>{step.label}</p>
                                        <p className="cc-text-tertiary text-xs">{step.detail}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </FintechPanel>
                </div>

                <FintechPanel>
                    <FintechSectionHeader title="Repayment schedule" />
                    {loan.installments.length === 0 ? (
                        <p className="cc-text-secondary mt-3 text-sm">Schedule will appear after disbursement.</p>
                    ) : (
                        <>
                            <div className="mt-4 grid gap-3 md:hidden">
                                {loan.installments.map((row) => {
                                    const payable = canPayInstallment(loan, row);
                                    return (
                                        <article key={row.id} className="cc-elev-2 space-y-3 p-4">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-semibold text-white">#{row.installment_number}</p>
                                                    <p className="cc-text-tertiary text-xs">Due {formatDate(row.due_date)}</p>
                                                </div>
                                                <LoanStatusBadge status={row.status} label={row.status_label} />
                                            </div>
                                            <dl className="grid grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <dt className="cc-text-tertiary text-xs">Due</dt>
                                                    <dd className="tabular-nums text-white">{formatMoney(row.amount_due)}</dd>
                                                </div>
                                                <div>
                                                    <dt className="cc-text-tertiary text-xs">Paid</dt>
                                                    <dd className="tabular-nums text-white">{formatMoney(row.amount_paid)}</dd>
                                                </div>
                                                <div className="col-span-2">
                                                    <dt className="cc-text-tertiary text-xs">Remaining</dt>
                                                    <dd className="tabular-nums text-white">{formatMoney(row.remaining)}</dd>
                                                </div>
                                            </dl>
                                            {payable ? (
                                                <Button
                                                    type="button"
                                                    className="cc-dash-btn-primary min-h-11 w-full"
                                                    disabled={payingId !== null}
                                                    onClick={() => openPay(row)}
                                                >
                                                    Pay
                                                </Button>
                                            ) : null}
                                        </article>
                                    );
                                })}
                            </div>

                            <div className="mt-4 hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[40rem] text-left text-sm">
                                    <thead>
                                        <tr className="cc-text-tertiary border-b border-white/10 text-xs">
                                            <th className="py-2 pr-3 font-medium">#</th>
                                            <th className="py-2 pr-3 font-medium">Due</th>
                                            <th className="py-2 pr-3 font-medium">Due amount</th>
                                            <th className="py-2 pr-3 font-medium">Paid</th>
                                            <th className="py-2 pr-3 font-medium">Remaining</th>
                                            <th className="py-2 pr-3 font-medium">Status</th>
                                            <th className="py-2 font-medium">
                                                <span className="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loan.installments.map((row) => {
                                            const payable = canPayInstallment(loan, row);
                                            return (
                                                <tr key={row.id} className="border-b border-white/5">
                                                    <td className="py-3 pr-3 tabular-nums text-white">{row.installment_number}</td>
                                                    <td className="py-3 pr-3 tabular-nums">{formatDate(row.due_date)}</td>
                                                    <td className="py-3 pr-3 tabular-nums">{formatMoney(row.amount_due)}</td>
                                                    <td className="py-3 pr-3 tabular-nums">{formatMoney(row.amount_paid)}</td>
                                                    <td className="py-3 pr-3 tabular-nums">{formatMoney(row.remaining)}</td>
                                                    <td className="py-3 pr-3">
                                                        <LoanStatusBadge status={row.status} label={row.status_label} />
                                                    </td>
                                                    <td className="py-3 text-right">
                                                        {payable ? (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="cc-dash-btn-primary"
                                                                disabled={payingId !== null}
                                                                onClick={() => openPay(row)}
                                                            >
                                                                Pay
                                                            </Button>
                                                        ) : null}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </FintechPanel>

                <FintechPanel>
                    <FintechSectionHeader title="Recent payments" />
                    {loan.payments.length === 0 ? (
                        <p className="cc-text-secondary mt-3 text-sm">No payments recorded yet.</p>
                    ) : (
                        <ul className="mt-4 space-y-2">
                            {loan.payments.map((payment) => (
                                <li key={payment.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span className="font-medium text-white">{payment.reference}</span>
                                    <span className="tabular-nums">{formatMoney(payment.amount)}</span>
                                    <LoanStatusBadge status={payment.status} label={payment.status_label} />
                                    <span className="cc-text-secondary text-xs">{payment.paid_at ? formatDate(payment.paid_at) : ''}</span>
                                    {payment.transaction_id ? (
                                        <Button asChild variant="outline" size="sm" className="cc-dash-btn-secondary">
                                            <Link href={route('transactions.show', payment.transaction_id)}>View transaction</Link>
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </FintechPanel>
            </FintechPage>
        </AppLayout>
    );
}

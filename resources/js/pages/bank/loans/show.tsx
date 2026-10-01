import { FintechEmpty, FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';
import { type FormEventHandler, useEffect, useMemo, useState } from 'react';

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
    student: { id: number | null; name: string | null; email: string | null };
    product: string | null;
    disbursement: {
        id: number;
        amount: string;
        status: string;
        reference: string | null;
        confirmed_at: string | null;
        finance_posted?: boolean;
    } | null;
    installments: {
        id: number;
        installment_number: number;
        due_date: string | null;
        amount_due: string;
        amount_paid: string;
        status: string;
        status_label: string;
    }[];
    payments: {
        id: number;
        reference: string;
        amount: string;
        status: string;
        status_label: string;
        paid_at: string | null;
    }[];
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    loan: Loan;
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function remaining(amountDue: string, amountPaid: string) {
    const due = Number(amountDue);
    const paid = Number(amountPaid);
    if (Number.isNaN(due) || Number.isNaN(paid)) return amountDue;
    return Math.max(0, due - paid).toFixed(2);
}

export default function BankLoanShow({
    eyebrow = 'Loans',
    title,
    description = 'Loan schedule, disbursement status, and recent payments.',
    bank,
    loan,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);
    const [confirmDisburse, setConfirmDisburse] = useState(false);

    const disburseForm = useForm({
        reference: '',
        destination: '',
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/bank' },
        { title: 'Loans', href: '/bank/loans' },
        { title: loan.reference, href: `/bank/loans/${loan.id}` },
    ];

    const canDisburse = loan.status === 'ready_for_disbursement' && Boolean(loan.agreement_accepted_at) && !loan.disbursed_at;

    const disbursementHint = useMemo(() => {
        if (loan.disbursed_at) {
            return `Funds marked disbursed on ${formatDate(loan.disbursed_at)}.`;
        }
        if (loan.status === 'agreement_pending' || !loan.agreement_accepted_at) {
            return 'Waiting for the student to accept the loan agreement before disbursement.';
        }
        if (loan.status === 'ready_for_disbursement') {
            return 'Agreement accepted. Confirm disbursement only after funds have actually been sent.';
        }
        if (loan.disbursement?.status === 'failed') {
            return 'A previous disbursement attempt failed. Review before retrying.';
        }
        return 'Disbursement is not available for the current loan status.';
    }, [loan]);

    const overdueCount = loan.installments.filter((row) => row.status === 'overdue').length;
    const failedPayments = loan.payments.filter((row) => row.status === 'failed').length;

    const confirmDisbursement: FormEventHandler = (e) => {
        e.preventDefault();
        if (disburseForm.processing) return;
        disburseForm.post(route('bank.loans.confirm-disbursement', loan.id), {
            onSuccess: () => setConfirmDisburse(false),
        });
    };

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`${loan.reference} · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? loan.reference}
                    description={description}
                    visual={<HandCoins className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <Button asChild variant="outline" className="cc-dash-btn-secondary">
                            <Link href={route('bank.loans.index')}>All loans</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                {(overdueCount > 0 || failedPayments > 0) && (
                    <FintechFlash tone="warning">
                        {overdueCount > 0 ? `${overdueCount} overdue installment${overdueCount === 1 ? '' : 's'}` : null}
                        {overdueCount > 0 && failedPayments > 0 ? ' · ' : null}
                        {failedPayments > 0 ? `${failedPayments} failed payment${failedPayments === 1 ? '' : 's'}` : null}
                    </FintechFlash>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2">
                        <FintechSectionHeader
                            title="Summary"
                            meta={<LoanStatusBadge status={loan.status} label={loan.status_label} />}
                        />
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="cc-text-tertiary text-xs">Student</dt>
                                <dd className="mt-1 font-medium text-white">{loan.student.name}</dd>
                                <dd className="cc-text-secondary text-xs">{loan.student.email}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Product</dt>
                                <dd className="mt-1 font-medium text-white">{loan.product ?? '—'}</dd>
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
                            <div>
                                <dt className="cc-text-tertiary text-xs">Total repayable</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">{formatMoney(loan.total_repayable)}</dd>
                            </div>
                        </dl>
                    </FintechPanel>

                    <FintechPanel>
                        <FintechSectionHeader title="Disbursement" />
                        <p className="cc-text-secondary mt-2 text-sm">{disbursementHint}</p>
                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between gap-2">
                                <dt className="cc-text-tertiary">Agreement</dt>
                                <dd>{loan.agreement_accepted_at ? formatDate(loan.agreement_accepted_at) : 'Pending'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="cc-text-tertiary">Disbursed</dt>
                                <dd>{loan.disbursed_at ? formatDate(loan.disbursed_at) : 'Not yet'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="cc-text-tertiary">Reference</dt>
                                <dd>{loan.disbursement?.reference ?? '—'}</dd>
                            </div>
                            {loan.disbursement?.status ? (
                                <div className="flex justify-between gap-2">
                                    <dt className="cc-text-tertiary">Status</dt>
                                    <dd>
                                        <LoanStatusBadge status={String(loan.disbursement.status)} label={String(loan.disbursement.status)} />
                                    </dd>
                                </div>
                            ) : null}
                            {loan.disbursement?.finance_posted ? (
                                <p className="cc-text-secondary pt-2 text-xs">
                                    Student finance ledger updated with a loan inflow transaction (amount only — no private student ledger details
                                    shown here).
                                </p>
                            ) : null}
                        </dl>

                        {canDisburse ? (
                            !confirmDisburse ? (
                                <Button
                                    type="button"
                                    className="cc-dash-btn-primary mt-4 w-full min-h-11"
                                    onClick={() => setConfirmDisburse(true)}
                                >
                                    Confirm disbursement
                                </Button>
                            ) : (
                                <FintechFormShell as="form" onSubmit={confirmDisbursement} className="mt-4 max-w-none p-0 md:p-0" noValidate>
                                    <FintechFlash tone="warning">
                                        Confirm only after {formatMoney(loan.principal_amount)} has actually been sent. This marks the loan as
                                        disbursed and posts a single loan inflow to the student&apos;s financial activity.
                                    </FintechFlash>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reference">Disbursement reference</Label>
                                        <Input
                                            id="reference"
                                            className="cc-field"
                                            value={disburseForm.data.reference}
                                            onChange={(e) => disburseForm.setData('reference', e.target.value)}
                                            disabled={disburseForm.processing}
                                        />
                                        <InputError message={disburseForm.errors.reference} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="destination">Destination note</Label>
                                        <Input
                                            id="destination"
                                            className="cc-field"
                                            value={disburseForm.data.destination}
                                            onChange={(e) => disburseForm.setData('destination', e.target.value)}
                                            disabled={disburseForm.processing}
                                        />
                                        <InputError message={disburseForm.errors.destination} />
                                    </div>
                                    <FintechFormActions className="border-0 pt-2">
                                        <Button type="submit" className="cc-dash-btn-primary min-h-11" disabled={disburseForm.processing}>
                                            {disburseForm.processing ? 'Confirming…' : 'Confirm funds sent'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="cc-dash-btn-secondary min-h-11"
                                            disabled={disburseForm.processing}
                                            onClick={() => setConfirmDisburse(false)}
                                        >
                                            Cancel
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            )
                        ) : null}
                    </FintechPanel>
                </div>

                <FintechPanel>
                    <FintechSectionHeader title="Repayment schedule" />
                    {loan.installments.length === 0 ? (
                        <p className="cc-text-secondary mt-3 text-sm">No installments yet.</p>
                    ) : (
                        <>
                            <div className="mt-4 grid gap-3 md:hidden">
                                {loan.installments.map((row) => (
                                    <article key={row.id} className="cc-elev-2 space-y-2 p-4">
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
                                                <dd className="tabular-nums text-white">
                                                    {formatMoney(remaining(row.amount_due, row.amount_paid))}
                                                </dd>
                                            </div>
                                        </dl>
                                    </article>
                                ))}
                            </div>

                            <div className="mt-4 hidden overflow-x-auto md:block">
                                <table className="w-full min-w-[32rem] text-left text-sm">
                                    <thead>
                                        <tr className="cc-text-tertiary border-b border-white/10 text-xs">
                                            <th className="py-2 pr-3 font-medium" scope="col">
                                                #
                                            </th>
                                            <th className="py-2 pr-3 font-medium" scope="col">
                                                Due
                                            </th>
                                            <th className="py-2 pr-3 font-medium" scope="col">
                                                Amount
                                            </th>
                                            <th className="py-2 pr-3 font-medium" scope="col">
                                                Paid
                                            </th>
                                            <th className="py-2 pr-3 font-medium" scope="col">
                                                Remaining
                                            </th>
                                            <th className="py-2 font-medium" scope="col">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loan.installments.map((row) => (
                                            <tr key={row.id} className="border-b border-white/5">
                                                <td className="py-3 pr-3 tabular-nums text-white">{row.installment_number}</td>
                                                <td className="py-3 pr-3 tabular-nums">{formatDate(row.due_date)}</td>
                                                <td className="py-3 pr-3 tabular-nums">{formatMoney(row.amount_due)}</td>
                                                <td className="py-3 pr-3 tabular-nums">{formatMoney(row.amount_paid)}</td>
                                                <td className="py-3 pr-3 tabular-nums">
                                                    {formatMoney(remaining(row.amount_due, row.amount_paid))}
                                                </td>
                                                <td className="py-3">
                                                    <LoanStatusBadge status={row.status} label={row.status_label} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </FintechPanel>

                <FintechPanel>
                    <FintechSectionHeader title="Payments" />
                    {loan.payments.length === 0 ? (
                        <div className="mt-3">
                            <FintechEmpty title="No payments yet" description="Successful and failed installment payments will appear here." />
                        </div>
                    ) : (
                        <ul className="mt-4 space-y-2 text-sm">
                            {loan.payments.map((payment) => (
                                <li key={payment.id} className="cc-elev-2 flex flex-wrap items-center justify-between gap-2 p-3">
                                    <span className="font-medium text-white">{payment.reference}</span>
                                    <span className="tabular-nums">{formatMoney(payment.amount)}</span>
                                    <LoanStatusBadge status={payment.status} label={payment.status_label} />
                                    <span className="cc-text-secondary text-xs">
                                        {payment.paid_at ? formatDate(payment.paid_at) : '—'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </FintechPanel>
            </FintechPage>
        </BankLayout>
    );
}

import { FintechEmpty, FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

type Application = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    requested_amount: string;
    requested_tenure_months: number;
    purpose: string | null;
    applicant_notes: string | null;
    rejection_reason: string | null;
    submitted_at: string | null;
    decided_at: string | null;
    student: { id: number | null; name: string | null; email: string | null };
    product: { id: number; name: string; annual_rate_percent: string } | null;
    documents: { id: number; document_type: string; original_name: string; status: string; created_at: string | null }[];
    events: {
        id: number;
        action: string;
        from_status: string | null;
        to_status: string | null;
        note: string | null;
        created_at: string | null;
    }[];
    loan_id: number | null;
    loan_reference: string | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    application: Application;
};

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function documentStatusLabel(status: string) {
    const map: Record<string, string> = {
        uploaded: 'Uploaded',
        under_review: 'Under review',
        accepted: 'Accepted',
        rejected: 'Reupload required',
        missing: 'Missing',
        required: 'Required',
    };
    return map[status] ?? status.replaceAll('_', ' ');
}

export default function BankApplicationShow({
    eyebrow = 'Applications',
    title,
    description = 'Review documents, notes, and take an underwriting action.',
    bank,
    application,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);
    const [busy, setBusy] = useState(false);
    const [confirmApprove, setConfirmApprove] = useState(false);

    const infoForm = useForm({ note: '' });
    const rejectForm = useForm({ reason: '' });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Overview', href: '/bank' },
        { title: 'Applications', href: '/bank/applications' },
        { title: application.reference, href: `/bank/applications/${application.id}` },
    ];

    const canDecide = ['submitted', 'under_review', 'more_information_required'].includes(application.status);

    const markUnderReview = () => {
        if (busy) return;
        setBusy(true);
        router.post(route('bank.applications.under-review', application.id), {}, { onFinish: () => setBusy(false) });
    };

    const approve = () => {
        if (busy) return;
        setBusy(true);
        router.post(route('bank.applications.approve', application.id), {}, {
            onFinish: () => {
                setBusy(false);
                setConfirmApprove(false);
            },
        });
    };

    const requestInfo: FormEventHandler = (e) => {
        e.preventDefault();
        if (infoForm.processing) return;
        infoForm.post(route('bank.applications.request-info', application.id), {
            onSuccess: () => infoForm.reset('note'),
        });
    };

    const reject: FormEventHandler = (e) => {
        e.preventDefault();
        if (rejectForm.processing) return;
        rejectForm.post(route('bank.applications.reject', application.id));
    };

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`${application.reference} · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? application.reference}
                    description={description}
                    visual={<FileText className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <>
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('bank.applications.index')}>Back</Link>
                            </Button>
                            {application.loan_id ? (
                                <Button asChild className="cc-dash-btn-primary">
                                    <Link href={route('bank.loans.show', application.loan_id)}>
                                        Loan {application.loan_reference}
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2 space-y-6">
                        <div>
                            <FintechSectionHeader
                                title="Applicant"
                                meta={<LoanStatusBadge status={application.status} label={application.status_label} />}
                            />
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Student</dt>
                                    <dd className="mt-1 font-medium text-white">{application.student.name}</dd>
                                    <dd className="cc-text-secondary text-xs">{application.student.email}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Submitted</dt>
                                    <dd className="mt-1 tabular-nums text-white">{formatDate(application.submitted_at)}</dd>
                                </div>
                                {application.decided_at ? (
                                    <div>
                                        <dt className="cc-text-tertiary text-xs">Decided</dt>
                                        <dd className="mt-1 tabular-nums text-white">{formatDate(application.decided_at)}</dd>
                                    </div>
                                ) : null}
                            </dl>
                        </div>

                        <div>
                            <FintechSectionHeader title="Loan request" />
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Product</dt>
                                    <dd className="mt-1 font-medium text-white">{application.product?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Annual rate</dt>
                                    <dd className="mt-1 font-medium tabular-nums text-white">
                                        {application.product?.annual_rate_percent
                                            ? `${application.product.annual_rate_percent}%`
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Amount / tenure</dt>
                                    <dd className="mt-1 font-medium text-white">
                                        {formatMoney(application.requested_amount)} · {application.requested_tenure_months} mo
                                    </dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Purpose</dt>
                                    <dd className="mt-1 font-medium text-white">{application.purpose || '—'}</dd>
                                </div>
                            </dl>
                            {application.applicant_notes ? (
                                <p className="cc-text-secondary mt-4 text-sm">{application.applicant_notes}</p>
                            ) : null}
                            {application.rejection_reason ? (
                                <FintechFlash tone="error">Rejection reason: {application.rejection_reason}</FintechFlash>
                            ) : null}
                        </div>

                        <div>
                            <FintechSectionHeader title="Documents" />
                            {application.documents.length === 0 ? (
                                <p className="cc-text-secondary mt-3 text-sm">No documents uploaded.</p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {application.documents.map((doc) => (
                                        <li key={doc.id} className="cc-elev-2 flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                                            <div>
                                                <p className="font-medium text-white">{doc.original_name}</p>
                                                <p className="cc-text-secondary text-xs">
                                                    {doc.document_type.replaceAll('_', ' ')} · {formatDate(doc.created_at)}
                                                </p>
                                            </div>
                                            <LoanStatusBadge status={doc.status} label={documentStatusLabel(doc.status)} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <FintechSectionHeader title="Review history" />
                            {application.events.length === 0 ? (
                                <div className="mt-3">
                                    <FintechEmpty title="No review events yet" description="Actions you take will appear in this timeline." />
                                </div>
                            ) : (
                                <ol className="mt-4 space-y-3">
                                    {application.events.map((event) => (
                                        <li key={event.id} className="border-l border-white/10 pl-4">
                                            <p className="text-sm font-medium text-white">{event.action}</p>
                                            <p className="cc-text-tertiary text-xs">{formatDate(event.created_at)}</p>
                                            {event.note ? <p className="cc-text-secondary mt-1 text-sm">{event.note}</p> : null}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </FintechPanel>

                    {canDecide ? (
                        <div className="grid gap-4 self-start">
                            <FintechPanel className="space-y-3">
                                <FintechSectionHeader title="Decision" />
                                {application.status === 'submitted' ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="cc-dash-btn-secondary w-full min-h-11"
                                        disabled={busy}
                                        onClick={markUnderReview}
                                    >
                                        {busy ? 'Updating…' : 'Mark under review'}
                                    </Button>
                                ) : null}

                                {!confirmApprove ? (
                                    <Button
                                        type="button"
                                        className="cc-dash-btn-primary w-full min-h-11"
                                        disabled={busy}
                                        onClick={() => setConfirmApprove(true)}
                                    >
                                        Approve
                                    </Button>
                                ) : (
                                    <div className="space-y-3 rounded-[0.85rem] border border-[#E94F09]/30 bg-[rgba(233,79,9,0.08)] p-3">
                                        <p className="text-sm font-medium text-white">Confirm approval terms</p>
                                        <dl className="space-y-1 text-sm">
                                            <div className="flex justify-between gap-2">
                                                <dt className="cc-text-tertiary">Amount</dt>
                                                <dd className="tabular-nums text-white">{formatMoney(application.requested_amount)}</dd>
                                            </div>
                                            <div className="flex justify-between gap-2">
                                                <dt className="cc-text-tertiary">Tenure</dt>
                                                <dd className="text-white">{application.requested_tenure_months} months</dd>
                                            </div>
                                            <div className="flex justify-between gap-2">
                                                <dt className="cc-text-tertiary">Rate</dt>
                                                <dd className="tabular-nums text-white">
                                                    {application.product?.annual_rate_percent
                                                        ? `${application.product.annual_rate_percent}%`
                                                        : '—'}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p className="cc-text-secondary text-xs">
                                            Approving creates a loan agreement for the student. This cannot be undone from the portal.
                                        </p>
                                        <div className="flex flex-col gap-2">
                                            <Button type="button" className="cc-dash-btn-primary min-h-11" disabled={busy} onClick={approve}>
                                                {busy ? 'Approving…' : 'Confirm approve'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="cc-dash-btn-secondary min-h-11"
                                                disabled={busy}
                                                onClick={() => setConfirmApprove(false)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </FintechPanel>

                            <FintechPanel>
                                <FintechSectionHeader title="Request information" />
                                <p className="cc-text-secondary mt-2 text-xs">Specify exactly what the student must provide.</p>
                                <FintechFormShell as="form" onSubmit={requestInfo} className="mt-3 max-w-none p-0 md:p-0" noValidate>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="note">What is needed</Label>
                                        <Textarea
                                            id="note"
                                            className="cc-field min-h-20"
                                            required
                                            value={infoForm.data.note}
                                            onChange={(e) => infoForm.setData('note', e.target.value)}
                                            disabled={infoForm.processing}
                                            placeholder="e.g. Upload a clearer student ID and latest fee challan"
                                        />
                                        <InputError message={infoForm.errors.note} />
                                    </div>
                                    <FintechFormActions className="border-0 pt-2">
                                        <Button type="submit" variant="outline" className="cc-dash-btn-secondary min-h-11" disabled={infoForm.processing}>
                                            {infoForm.processing ? 'Sending…' : 'Request info'}
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            </FintechPanel>

                            <FintechPanel>
                                <FintechSectionHeader title="Reject" />
                                <p className="cc-text-secondary mt-2 text-xs">A reason is required and recorded in the audit trail.</p>
                                <FintechFormShell as="form" onSubmit={reject} className="mt-3 max-w-none p-0 md:p-0" noValidate>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reason">Reason</Label>
                                        <Textarea
                                            id="reason"
                                            className="cc-field min-h-20"
                                            required
                                            value={rejectForm.data.reason}
                                            onChange={(e) => rejectForm.setData('reason', e.target.value)}
                                            disabled={rejectForm.processing}
                                        />
                                        <InputError message={rejectForm.errors.reason} />
                                    </div>
                                    <FintechFormActions className="border-0 pt-2">
                                        <Button type="submit" variant="destructive" className="min-h-11" disabled={rejectForm.processing}>
                                            {rejectForm.processing ? 'Rejecting…' : 'Reject application'}
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            </FintechPanel>
                        </div>
                    ) : (
                        <FintechPanel>
                            <FintechSectionHeader
                                title="Decision"
                                meta={<LoanStatusBadge status={application.status} label={application.status_label} />}
                            />
                            <p className="cc-text-secondary mt-3 text-sm">
                                This application is {application.status_label.toLowerCase()}. No further underwriting actions are available.
                            </p>
                        </FintechPanel>
                    )}
                </div>
            </FintechPage>
        </BankLayout>
    );
}

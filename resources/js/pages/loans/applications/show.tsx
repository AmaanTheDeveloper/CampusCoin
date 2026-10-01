import { FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FileText, Upload } from 'lucide-react';
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
    product: {
        id: number;
        name: string;
        required_documents: string[];
        bank: { id: number; name: string } | null;
    } | null;
    documents: {
        id: number;
        document_type: string;
        original_name: string;
        status: string;
        created_at: string | null;
    }[];
    events: {
        id: number;
        action: string;
        from_status: string | null;
        to_status: string | null;
        note: string | null;
        created_at: string | null;
    }[];
    loan_id: number | null;
    can_edit: boolean;
    can_submit: boolean;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
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
        required: 'Required',
        missing: 'Missing',
    };
    return map[status] ?? status.replaceAll('_', ' ');
}

export default function LoanApplicationShow({
    eyebrow = 'Application',
    title,
    description = 'Track status, upload documents, and submit when ready.',
    application,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [submitting, setSubmitting] = useState(false);

    const uploadForm = useForm<{ document_type: string; document: File | null }>({
        document_type: application.product?.required_documents?.[0] ?? 'other',
        document: null,
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: '/loans' },
        { title: application.reference, href: `/loans/applications/${application.id}` },
    ];

    const submitApplication = () => {
        if (submitting || !application.can_submit) return;
        if (!confirm('Submit this application to the bank? You may not be able to edit after submission.')) return;
        setSubmitting(true);
        router.post(route('loans.applications.submit', application.id), {}, { onFinish: () => setSubmitting(false) });
    };

    const uploadDocument: FormEventHandler = (e) => {
        e.preventDefault();
        if (uploadForm.processing || !uploadForm.data.document) return;
        uploadForm.post(route('loans.applications.documents.store', application.id), {
            forceFormData: true,
            onSuccess: () => uploadForm.setData('document', null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={application.reference} />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? application.reference}
                    description={description}
                    visual={<FileText className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <>
                            {application.loan_id ? (
                                <Button asChild className="cc-dash-btn-primary">
                                    <Link href={route('loans.active.show', application.loan_id)}>View loan</Link>
                                </Button>
                            ) : null}
                            {application.can_edit ? (
                                <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                    <Link href={route('loans.applications.edit', application.id)}>Edit draft</Link>
                                </Button>
                            ) : null}
                            {application.can_submit ? (
                                <Button
                                    type="button"
                                    className="cc-dash-btn-primary"
                                    disabled={submitting}
                                    aria-busy={submitting}
                                    onClick={submitApplication}
                                >
                                    {submitting ? 'Submitting…' : 'Submit application'}
                                </Button>
                            ) : null}
                        </>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {application.rejection_reason ? (
                    <FintechFlash tone="error">Rejected: {application.rejection_reason}</FintechFlash>
                ) : null}

                {application.status === 'more_information_required' ? (
                    <FintechFlash tone="warning">
                        More information is required. Review the latest note in the timeline below, upload any missing documents, and resubmit if
                        available.
                    </FintechFlash>
                ) : null}

                {application.status === 'submitted' || application.status === 'under_review' ? (
                    <FintechFlash>
                        Your application is with the bank. You will be notified when a decision is made or if more information is needed.
                    </FintechFlash>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2 space-y-6">
                        <div>
                            <FintechSectionHeader title="Details" />
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Status</dt>
                                    <dd className="mt-1">
                                        <LoanStatusBadge status={application.status} label={application.status_label} />
                                    </dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Product</dt>
                                    <dd className="mt-1 font-medium text-white">{application.product?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Bank</dt>
                                    <dd className="mt-1 font-medium text-white">{application.product?.bank?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Amount</dt>
                                    <dd className="mt-1 font-medium tabular-nums text-white">{formatMoney(application.requested_amount)}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Tenure</dt>
                                    <dd className="mt-1 font-medium text-white">{application.requested_tenure_months} months</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Purpose</dt>
                                    <dd className="mt-1 font-medium text-white">{application.purpose || '—'}</dd>
                                </div>
                            </dl>
                            {application.applicant_notes ? (
                                <p className="cc-text-secondary mt-4 text-sm leading-relaxed">{application.applicant_notes}</p>
                            ) : null}
                        </div>

                        <div>
                            <FintechSectionHeader title="Status timeline" />
                            {application.events.length === 0 ? (
                                <p className="cc-text-secondary mt-3 text-sm">No events yet.</p>
                            ) : (
                                <ol className="mt-4 space-y-3">
                                    {application.events.map((event) => (
                                        <li key={event.id} className="border-l border-white/10 pl-4">
                                            <p className="text-sm font-medium text-white">{event.action}</p>
                                            <p className="cc-text-tertiary text-xs tabular-nums">{formatDate(event.created_at)}</p>
                                            {(event.from_status || event.to_status) && (
                                                <p className="cc-text-secondary mt-0.5 text-xs">
                                                    {[event.from_status, event.to_status].filter(Boolean).join(' → ')}
                                                </p>
                                            )}
                                            {event.note ? <p className="cc-text-secondary mt-1 text-sm">{event.note}</p> : null}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </FintechPanel>

                    <div className="grid gap-4 self-start">
                        <FintechPanel>
                            <FintechSectionHeader title="Documents" meta={`${application.documents.length} uploaded`} />
                            {application.documents.length === 0 ? (
                                <p className="cc-text-secondary mt-3 text-sm">No documents uploaded yet.</p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {application.documents.map((doc) => (
                                        <li key={doc.id} className="cc-elev-2 flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius)] p-3 text-sm">
                                            <div>
                                                <p className="font-medium text-white">{doc.original_name}</p>
                                                <p className="cc-text-secondary text-xs">{doc.document_type.replaceAll('_', ' ')}</p>
                                            </div>
                                            <LoanStatusBadge status={doc.status} label={documentStatusLabel(doc.status)} />
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {application.can_edit ? (
                                <FintechFormShell as="form" onSubmit={uploadDocument} className="mt-4 max-w-none p-0 md:p-0" noValidate>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="document_type">Document type</Label>
                                        {application.product?.required_documents?.length ? (
                                            <select
                                                id="document_type"
                                                className="cc-field w-full"
                                                value={uploadForm.data.document_type}
                                                onChange={(e) => uploadForm.setData('document_type', e.target.value)}
                                                disabled={uploadForm.processing}
                                            >
                                                {application.product.required_documents.map((type) => (
                                                    <option key={type} value={type}>
                                                        {type}
                                                    </option>
                                                ))}
                                                <option value="other">Other</option>
                                            </select>
                                        ) : (
                                            <Input
                                                id="document_type"
                                                className="cc-field"
                                                value={uploadForm.data.document_type}
                                                onChange={(e) => uploadForm.setData('document_type', e.target.value)}
                                                disabled={uploadForm.processing}
                                            />
                                        )}
                                        <InputError message={uploadForm.errors.document_type} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="document">File (PDF, JPG, PNG)</Label>
                                        <Input
                                            id="document"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            className="cc-field"
                                            onChange={(e) => uploadForm.setData('document', e.target.files?.[0] ?? null)}
                                            disabled={uploadForm.processing}
                                        />
                                        <InputError message={uploadForm.errors.document} />
                                    </div>
                                    <FintechFormActions className="border-0 pt-0">
                                        <Button type="submit" className="cc-dash-btn-primary" disabled={uploadForm.processing || !uploadForm.data.document}>
                                            <Upload className="mr-2 size-4" aria-hidden />
                                            {uploadForm.processing ? 'Uploading…' : 'Upload'}
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            ) : null}
                        </FintechPanel>
                    </div>
                </div>
            </FintechPage>
        </AppLayout>
    );
}

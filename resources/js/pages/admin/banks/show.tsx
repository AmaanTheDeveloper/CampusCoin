import { FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { LoanStatusBadge } from '@/components/loans/loan-status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

type Bank = {
    id: number;
    name: string;
    slug: string;
    reference_code: string | null;
    official_email: string;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    registration_number: string | null;
    authorized_representative: string | null;
    representative_email: string | null;
    representative_phone: string | null;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    suspension_reason: string | null;
    approved_at: string | null;
    suspended_at: string | null;
    created_at: string | null;
    approved_by: { id: number; name: string; email: string } | null;
    products_count: number;
    applications_count: number;
    loans_count: number;
    memberships: {
        id: number;
        staff_role: string;
        is_active: boolean;
        user: { id: number; name: string; email: string } | null;
    }[];
    verification_documents: {
        id: number;
        document_type: string;
        original_name: string;
        mime_type: string | null;
        size_bytes: number | null;
        status: string;
        created_at: string | null;
    }[];
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: Bank;
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
        pending: 'Pending',
    };
    return map[status] ?? status.replaceAll('_', ' ');
}

export default function AdminBankShow({
    eyebrow = 'Admin',
    title,
    description = 'Review registration details and verification documents. Super admins manage bank onboarding only — not student loan underwriting.',
    bank,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);
    const [busy, setBusy] = useState(false);
    const [confirmApprove, setConfirmApprove] = useState(false);

    const rejectForm = useForm({ reason: '' });
    const suspendForm = useForm({ reason: '' });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
        setErrorMessage(flash?.error ?? null);
    }, [flash?.success, flash?.error]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Banks', href: '/admin/banks' },
        { title: bank.name, href: `/admin/banks/${bank.id}` },
    ];

    const approve = () => {
        if (busy) return;
        setBusy(true);
        router.post(route('admin.banks.approve', bank.id), {}, {
            onFinish: () => {
                setBusy(false);
                setConfirmApprove(false);
            },
        });
    };

    const reactivate = () => {
        if (busy) return;
        if (!confirm(`Reactivate ${bank.name}?`)) return;
        setBusy(true);
        router.post(route('admin.banks.reactivate', bank.id), {}, { onFinish: () => setBusy(false) });
    };

    const reject: FormEventHandler = (e) => {
        e.preventDefault();
        if (rejectForm.processing) return;
        rejectForm.post(route('admin.banks.reject', bank.id));
    };

    const suspend: FormEventHandler = (e) => {
        e.preventDefault();
        if (suspendForm.processing) return;
        suspendForm.post(route('admin.banks.suspend', bank.id));
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${bank.name} · Banks`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title ?? bank.name}
                    description={description}
                    visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <Button asChild variant="outline" className="cc-dash-btn-secondary">
                            <Link href={route('admin.banks.index')}>Back to banks</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2 space-y-6">
                        <div>
                            <FintechSectionHeader
                                title="Registration"
                                meta={<LoanStatusBadge status={bank.status} label={bank.status_label} />}
                            />
                            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Request ID</dt>
                                    <dd className="mt-1 font-mono text-sm font-medium text-white">{bank.reference_code || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Submitted</dt>
                                    <dd className="mt-1 font-medium text-white">{formatDate(bank.created_at)}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Official email</dt>
                                    <dd className="mt-1 font-medium text-white">{bank.official_email}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Phone</dt>
                                    <dd className="mt-1 font-medium text-white">{bank.phone || '—'}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Registration #</dt>
                                    <dd className="mt-1 font-medium text-white">{bank.registration_number || '—'}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="cc-text-tertiary text-xs">Address</dt>
                                    <dd className="mt-1 font-medium text-white">
                                        {[bank.address, bank.city, bank.country].filter(Boolean).join(', ') || '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Representative</dt>
                                    <dd className="mt-1 font-medium text-white">{bank.authorized_representative || '—'}</dd>
                                    <dd className="cc-text-secondary text-xs">{bank.representative_email}</dd>
                                    {bank.representative_phone ? (
                                        <dd className="cc-text-secondary text-xs">{bank.representative_phone}</dd>
                                    ) : null}
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary text-xs">Activity</dt>
                                    <dd className="mt-1 text-sm text-white">
                                        {bank.products_count} products · {bank.applications_count} apps · {bank.loans_count} loans
                                    </dd>
                                </div>
                            </dl>
                            {bank.rejection_reason ? (
                                <FintechFlash tone="error">Rejection: {bank.rejection_reason}</FintechFlash>
                            ) : null}
                            {bank.suspension_reason ? (
                                <FintechFlash tone="warning">Suspension: {bank.suspension_reason}</FintechFlash>
                            ) : null}
                        </div>

                        <div>
                            <FintechSectionHeader title="Staff" />
                            {bank.memberships.length === 0 ? (
                                <p className="cc-text-secondary mt-3 text-sm">No memberships.</p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {bank.memberships.map((m) => (
                                        <li key={m.id} className="cc-elev-2 p-3 text-sm">
                                            <p className="font-medium text-white">{m.user?.name ?? '—'}</p>
                                            <p className="cc-text-secondary text-xs">
                                                {m.user?.email} · {m.staff_role}
                                                {m.is_active ? '' : ' · inactive'}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <FintechSectionHeader title="Verification documents" />
                            {bank.verification_documents.length === 0 ? (
                                <p className="cc-text-secondary mt-3 text-sm">No documents uploaded.</p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {bank.verification_documents.map((doc) => (
                                        <li key={doc.id} className="cc-elev-2 flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                                            <div>
                                                <p className="font-medium text-white">{doc.original_name}</p>
                                                <p className="cc-text-secondary text-xs">
                                                    {doc.document_type.replaceAll('_', ' ')} · {formatDate(doc.created_at)}
                                                    {doc.size_bytes ? ` · ${Math.round(doc.size_bytes / 1024)} KB` : ''}
                                                </p>
                                            </div>
                                            <LoanStatusBadge status={doc.status} label={documentStatusLabel(doc.status)} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </FintechPanel>

                    <div className="grid gap-4 self-start">
                        {bank.status === 'pending' || bank.status === 'rejected' ? (
                            <FintechPanel className="space-y-3">
                                <FintechSectionHeader title="Approve" />
                                {!confirmApprove ? (
                                    <Button
                                        type="button"
                                        className="cc-dash-btn-primary w-full min-h-11"
                                        disabled={busy}
                                        onClick={() => setConfirmApprove(true)}
                                    >
                                        Approve bank
                                    </Button>
                                ) : (
                                    <div className="space-y-3 rounded-[0.85rem] border border-[#E94F09]/30 bg-[rgba(233,79,9,0.08)] p-3">
                                        <p className="text-sm font-medium text-white">Confirm partner approval</p>
                                        <p className="cc-text-secondary text-xs">
                                            Approving {bank.name} activates the bank and allows its authorized users to access the Bank Portal and
                                            create/publish loan products. This does not approve any student loan.
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
                        ) : null}

                        {bank.status === 'suspended' ? (
                            <FintechPanel>
                                <FintechSectionHeader title="Reactivate" />
                                <Button type="button" className="cc-dash-btn-primary mt-3 w-full min-h-11" disabled={busy} onClick={reactivate}>
                                    Reactivate bank
                                </Button>
                            </FintechPanel>
                        ) : null}

                        {(bank.status === 'pending' || bank.status === 'approved') && (
                            <FintechPanel>
                                <FintechSectionHeader title="Reject" />
                                <p className="cc-text-secondary mt-2 text-xs">A reason is required and recorded in the audit trail.</p>
                                <FintechFormShell as="form" onSubmit={reject} className="mt-3 max-w-none p-0 md:p-0" noValidate>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reject-reason">Reason</Label>
                                        <Textarea
                                            id="reject-reason"
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
                                            Reject
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            </FintechPanel>
                        )}

                        {bank.status === 'approved' ? (
                            <FintechPanel>
                                <FintechSectionHeader title="Suspend" />
                                <p className="cc-text-secondary mt-2 text-xs">Published products will become unavailable to students.</p>
                                <FintechFormShell as="form" onSubmit={suspend} className="mt-3 max-w-none p-0 md:p-0" noValidate>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="suspend-reason">Reason</Label>
                                        <Textarea
                                            id="suspend-reason"
                                            className="cc-field min-h-20"
                                            required
                                            value={suspendForm.data.reason}
                                            onChange={(e) => suspendForm.setData('reason', e.target.value)}
                                            disabled={suspendForm.processing}
                                        />
                                        <InputError message={suspendForm.errors.reason} />
                                    </div>
                                    <FintechFormActions className="border-0 pt-2">
                                        <Button type="submit" variant="destructive" className="min-h-11" disabled={suspendForm.processing}>
                                            Suspend
                                        </Button>
                                    </FintechFormActions>
                                </FintechFormShell>
                            </FintechPanel>
                        ) : null}

                        <FintechPanel>
                            <FintechSectionHeader title="Approval history" />
                            <dl className="mt-3 space-y-2 text-sm">
                                <div className="flex justify-between gap-2">
                                    <dt className="cc-text-tertiary">Created</dt>
                                    <dd className="tabular-nums">{formatDate(bank.created_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="cc-text-tertiary">Approved</dt>
                                    <dd className="tabular-nums">{formatDate(bank.approved_at)}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="cc-text-tertiary">Suspended</dt>
                                    <dd className="tabular-nums">{formatDate(bank.suspended_at)}</dd>
                                </div>
                                <div>
                                    <dt className="cc-text-tertiary">Reviewed by</dt>
                                    <dd className="mt-1">{bank.approved_by?.name ?? '—'}</dd>
                                    {bank.approved_by?.email ? (
                                        <dd className="cc-text-secondary text-xs">{bank.approved_by.email}</dd>
                                    ) : null}
                                </div>
                            </dl>
                        </FintechPanel>
                    </div>
                </div>
            </FintechPage>
        </AdminLayout>
    );
}

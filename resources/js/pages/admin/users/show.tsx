import { AdminStatusBadge } from '@/components/admin/admin-status-badge';
import { AdminUsersVisual } from '@/components/admin/visuals/AdminVisuals';
import { FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AdminLayout from '@/layouts/admin-layout';
import type { AcademicYearValue, BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Student = {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'disabled';
    joined_at: string | null;
    disabled_at: string | null;
    last_activity_at: string | null;
    transactions_count: number;
    income_count: number;
    expense_count: number;
    academic_year: AcademicYearValue | null;
};

type Props = {
    student: Student;
};

type ConfirmAction = 'disable' | 'reset' | null;

function formatDate(value: string | null) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

function academicYearLabel(value: AcademicYearValue | null) {
    if (!value) return '—';
    const labels: Record<AcademicYearValue, string> = {
        year_1: 'Year 1',
        year_2: 'Year 2',
        year_3: 'Year 3',
        year_4: 'Year 4',
        graduate: 'Graduate',
        other: 'Other',
    };
    return labels[value] ?? value;
}

export default function AdminUserShow({ student }: Props) {
    const { flash, errors } = usePage<SharedData & { errors?: Record<string, string> }>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Users', href: '/admin/users' },
        { title: student.name, href: `/admin/users/${student.id}` },
    ];

    const runConfirm = () => {
        if (!confirmAction) return;
        setProcessing(true);

        const finish = () => {
            setProcessing(false);
            setConfirmAction(null);
        };

        if (confirmAction === 'disable') {
            router.post(route('admin.users.disable', student.id), { confirm: true }, { onFinish: finish });
            return;
        }

        router.post(route('admin.users.reset-access', student.id), { confirm: true }, { onFinish: finish });
    };

    const enableAccount = () => {
        setProcessing(true);
        router.post(route('admin.users.enable', student.id), {}, { onFinish: () => setProcessing(false) });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${student.name} · Admin`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow="Management"
                    title={student.name}
                    description={student.email}
                    visual={<AdminUsersVisual />}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={route('admin.users.index')}>Back to users</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}
                <InputError message={errors?.reset} />
                <InputError message={errors?.confirm} />

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2">
                        <FintechSectionHeader title="Account details" />
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground text-xs tracking-wide uppercase">Status</dt>
                                <dd className="mt-1">
                                    <AdminStatusBadge status={student.status === 'active' ? 'active' : 'disabled'} />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs tracking-wide uppercase">Academic year</dt>
                                <dd className="mt-1 text-sm font-medium">{academicYearLabel(student.academic_year)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs tracking-wide uppercase">Joined</dt>
                                <dd className="mt-1 text-sm tabular-nums">{formatDate(student.joined_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs tracking-wide uppercase">Disabled at</dt>
                                <dd className="mt-1 text-sm tabular-nums">{formatDate(student.disabled_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground text-xs tracking-wide uppercase">Last activity</dt>
                                <dd className="mt-1 text-sm tabular-nums">{formatDate(student.last_activity_at)}</dd>
                            </div>
                        </dl>
                    </FintechPanel>

                    <FintechPanel>
                        <FintechSectionHeader title="Transaction summary" />
                        <dl className="mt-4 space-y-3 text-sm">
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Total</dt>
                                <dd className="font-semibold tabular-nums">{student.transactions_count}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Income</dt>
                                <dd className="font-semibold tabular-nums">{student.income_count}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Expense</dt>
                                <dd className="font-semibold tabular-nums">{student.expense_count}</dd>
                            </div>
                        </dl>
                    </FintechPanel>
                </div>

                <FintechPanel>
                    <FintechSectionHeader title="Access controls" meta="Financial history is always preserved" />
                    <div className="mt-4 flex flex-wrap gap-2">
                        {student.status === 'active' ? (
                            <Button variant="destructive" disabled={processing} onClick={() => setConfirmAction('disable')}>
                                Disable account
                            </Button>
                        ) : (
                            <Button disabled={processing} onClick={enableAccount}>
                                Enable account
                            </Button>
                        )}
                        <Button variant="outline" disabled={processing} onClick={() => setConfirmAction('reset')}>
                            Reset access
                        </Button>
                    </div>
                    <p className="text-muted-foreground mt-3 max-w-2xl text-xs leading-relaxed">
                        Reset access sends a password reset email. It does not wipe transactions, budgets, or other student data.
                    </p>
                </FintechPanel>
            </FintechPage>

            <Dialog open={confirmAction !== null} onOpenChange={(open) => !open && setConfirmAction(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{confirmAction === 'disable' ? 'Disable student account?' : 'Send password reset email?'}</DialogTitle>
                        <DialogDescription>
                            {confirmAction === 'disable'
                                ? `Disable access for ${student.name}. Their financial history will be preserved and can be re-enabled later.`
                                : `Send a password reset email to ${student.email}. This does not modify or delete any financial data.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => setConfirmAction(null)} disabled={processing}>
                            Cancel
                        </Button>
                        <Button variant={confirmAction === 'disable' ? 'destructive' : 'default'} onClick={runConfirm} disabled={processing}>
                            {confirmAction === 'disable' ? 'Disable account' : 'Send reset email'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

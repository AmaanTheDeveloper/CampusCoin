import { FintechEmpty, FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import BankLayout from '@/layouts/bank/bank-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/bank' },
    { title: 'Staff', href: '/bank/staff' },
];

type StaffRow = {
    id: number;
    staff_role: string;
    is_active: boolean;
    user: { id: number; name: string; email: string } | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    staff: StaffRow[];
    can_manage?: boolean;
};

export default function BankStaffIndex({
    eyebrow = 'Staff',
    title = 'Bank staff',
    description = 'Invite reviewers and admins for your bank only.',
    bank,
    staff,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        staff_role: 'officer',
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) return;
        form.post(route('bank.staff.store'), {
            onSuccess: () => form.reset('name', 'email', 'password', 'password_confirmation'),
        });
    };

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`${bank.name} · Staff`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Users className="size-14 text-[#E94F09]" aria-hidden />}
                />
                <FintechFlash message={successMessage} onDismiss={() => setSuccessMessage(null)} />

                <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <div className="space-y-3">
                        <FintechSectionHeader title="Team" description="People who can review applications for this bank." />
                        {staff.length === 0 ? (
                            <FintechEmpty title="No staff yet" description="Invite an officer or admin to help review applications." />
                        ) : (
                            staff.map((row) => (
                                <FintechPanel key={row.id} className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="font-medium text-white">{row.user?.name ?? '—'}</p>
                                        <p className="cc-text-secondary text-sm">{row.user?.email}</p>
                                    </div>
                                    <div className="text-right text-sm">
                                        <p className="capitalize text-white">{row.staff_role}</p>
                                        <p className="cc-text-tertiary">{row.is_active ? 'Active' : 'Inactive'}</p>
                                    </div>
                                </FintechPanel>
                            ))
                        )}
                    </div>

                    <FintechFormShell onSubmit={submit}>
                        <FintechSectionHeader title="Invite staff" description="Creates a bank-scoped account for your institution only." />
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Work email</Label>
                                <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                                <InputError message={form.errors.email} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="staff_role">Role</Label>
                                <select
                                    id="staff_role"
                                    className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                    value={form.data.staff_role}
                                    onChange={(e) => form.setData('staff_role', e.target.value)}
                                >
                                    <option value="officer">Loan officer</option>
                                    <option value="admin">Bank admin</option>
                                </select>
                                <InputError message={form.errors.staff_role} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="password">Temporary password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.password} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">Confirm password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                        <FintechFormActions>
                            <Button type="submit" className="cc-dash-btn-primary" disabled={form.processing}>
                                Invite staff member
                            </Button>
                        </FintechFormActions>
                    </FintechFormShell>
                </div>
            </FintechPage>
        </BankLayout>
    );
}

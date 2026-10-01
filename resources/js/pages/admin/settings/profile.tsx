import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import AdminSettingsLayout from '@/layouts/admin/settings-layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
    { title: 'Profile', href: '/admin/settings/profile' },
];

type ProfileProps = {
    mustVerifyEmail: boolean;
    status?: string;
    profile: {
        name: string;
        email: string;
        email_verified_at: string | null;
    };
};

export default function AdminProfileSettings({ mustVerifyEmail, status, profile }: ProfileProps) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: profile.name,
        email: profile.email,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Profile" />
            <AdminSettingsLayout>
                <form onSubmit={submit} className="space-y-6">
                    <HeadingSmall title="Profile" description="Update the administrator name and email used for this account." />

                    <div className="grid gap-2">
                        <Label htmlFor="admin-name">Name</Label>
                        <Input
                            id="admin-name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                            disabled={processing}
                        />
                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="admin-email">Email address</Label>
                        <Input
                            id="admin-email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                            disabled={processing}
                        />
                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    {mustVerifyEmail && profile.email_verified_at === null ? (
                        <div>
                            <p className="text-sm">
                                Your email address is unverified.{' '}
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="text-primary rounded-md text-sm underline focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>
                            {status === 'verification-link-sent' ? (
                                <div className="mt-2 text-sm font-medium text-green-600">
                                    A new verification link has been sent to your email address.
                                </div>
                            ) : null}
                        </div>
                    ) : null}

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>{processing ? 'Saving…' : 'Save'}</Button>
                        <Transition
                            show={recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-neutral-600 dark:text-neutral-300">Saved</p>
                        </Transition>
                    </div>
                </form>
            </AdminSettingsLayout>
        </AdminLayout>
    );
}

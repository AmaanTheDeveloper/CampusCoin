import { type AcademicYearValue, type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

type AcademicYearOption = {
    value: string;
    label: string;
};

type ProfileProps = {
    mustVerifyEmail: boolean;
    status?: string;
    academicYearOptions: AcademicYearOption[];
    profile: {
        name: string;
        email: string;
        academic_year: AcademicYearValue | null;
        monthly_allowance: string | number | null;
        savings_goal: string | number | null;
        email_verified_at: string | null;
    };
};

function moneyToInput(value: string | number | null): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(value);
}

export default function Profile({ mustVerifyEmail, status, academicYearOptions, profile }: ProfileProps) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: profile.name,
        email: profile.email,
        academic_year: profile.academic_year ?? '',
        monthly_allowance: moneyToInput(profile.monthly_allowance),
        savings_goal: moneyToInput(profile.savings_goal),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-10">
                    <div className="space-y-6">
                        <HeadingSmall title="Personal information" description="Your name, email, and academic year for Campus Coin" />

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                                disabled={processing}
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Email address"
                                disabled={processing}
                            />
                            <InputError className="mt-2" message={errors.email} />
                            <p className="text-muted-foreground text-xs">
                                Changing your email requires verification again before you can use protected pages.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="academic_year">Academic year</Label>
                            <select
                                id="academic_year"
                                name="academic_year"
                                className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring mt-1 flex h-10 w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                                value={data.academic_year}
                                onChange={(e) => setData('academic_year', e.target.value)}
                                disabled={processing}
                            >
                                <option value="">Select academic year (optional)</option>
                                {academicYearOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError className="mt-2" message={errors.academic_year} />
                        </div>

                        {mustVerifyEmail && profile.email_verified_at === null && (
                            <div>
                                <p className="mt-2 text-sm text-neutral-800 dark:text-neutral-200">
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

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <HeadingSmall
                            title="Financial baseline"
                            description="Optional monthly allowance and savings goal used later for budgeting insights"
                        />

                        <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="monthly_allowance">Monthly allowance baseline</Label>
                                <Input
                                    id="monthly_allowance"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.monthly_allowance}
                                    onChange={(e) => setData('monthly_allowance', e.target.value)}
                                    placeholder="0.00"
                                    disabled={processing}
                                />
                                <p className="text-muted-foreground text-xs">Non-negative amount with up to 2 decimals.</p>
                                <InputError className="mt-2" message={errors.monthly_allowance} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="savings_goal">Savings goal</Label>
                                <Input
                                    id="savings_goal"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.savings_goal}
                                    onChange={(e) => setData('savings_goal', e.target.value)}
                                    placeholder="0.00"
                                    disabled={processing}
                                />
                                <p className="text-muted-foreground text-xs">Non-negative amount with up to 2 decimals.</p>
                                <InputError className="mt-2" message={errors.savings_goal} />
                            </div>
                        </div>
                    </div>

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

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}

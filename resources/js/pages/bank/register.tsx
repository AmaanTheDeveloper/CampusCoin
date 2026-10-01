import { FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
};

export default function BankRegister({
    eyebrow = 'For banks',
    title = 'Register your bank',
    description = 'Join Campus Coin and offer student loan products after platform verification.',
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [errorMessage, setErrorMessage] = useState<string | null>(flash?.error ?? null);

    const form = useForm({
        name: '',
        official_email: '',
        phone: '',
        address: '',
        city: '',
        country: '',
        registration_number: '',
        authorized_representative: '',
        representative_email: '',
        representative_phone: '',
        password: '',
        password_confirmation: '',
        documents: [] as File[],
        authorization_confirmed: false as boolean,
    });

    useEffect(() => {
        setErrorMessage(flash?.error ?? null);
    }, [flash?.error]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) return;
        form.post(route('bank.register.store'), { forceFormData: true });
    };

    return (
        <PublicLayout title="Register your bank" description={description}>
            <Head title="Register your bank" />
            <div className="mx-auto w-full max-w-2xl px-4 py-10 md:py-14">
                <FintechPage stagger={false} className="p-0 md:p-0">
                    <FintechPageHeader
                        eyebrow={eyebrow}
                        title={title}
                        description={description}
                        visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                        actions={
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('login')}>Already approved? Log in</Link>
                            </Button>
                        }
                    />

                    <FintechFlash tone="warning">
                        Submitting this form creates a registration request with status Pending Verification. A Super Admin must approve your bank before
                        Bank Portal access is available.
                    </FintechFlash>

                    <ol className="cc-elev-2 mb-2 grid gap-3 rounded-[0.85rem] p-4 sm:grid-cols-2 lg:grid-cols-4">
                        {['Register', 'Pending review', 'Get approved', 'Publish products'].map((step, index) => (
                            <li key={step} className="text-sm">
                                <p className="text-xs uppercase tracking-wide text-[#ff9b6b]">Step {index + 1}</p>
                                <p className="mt-1 font-medium text-white">{step}</p>
                            </li>
                        ))}
                    </ol>

                    {errorMessage ? <FintechFlash tone="error">{errorMessage}</FintechFlash> : null}

                    <FintechFormShell as="form" onSubmit={submit} className="max-w-none" noValidate>
                        <section className="grid gap-4" aria-labelledby="bank-details-heading">
                            <h2 id="bank-details-heading" className="text-sm font-semibold tracking-tight text-white">
                                Bank details
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5 sm:col-span-2">
                                    <Label htmlFor="name">Bank name</Label>
                                    <Input
                                        id="name"
                                        className="cc-field"
                                        required
                                        autoComplete="organization"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="official_email">Official email</Label>
                                    <Input
                                        id="official_email"
                                        type="email"
                                        className="cc-field"
                                        required
                                        autoComplete="email"
                                        value={form.data.official_email}
                                        onChange={(e) => form.setData('official_email', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.official_email} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="phone">Official phone</Label>
                                    <Input
                                        id="phone"
                                        className="cc-field"
                                        autoComplete="tel"
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.phone} />
                                </div>
                                <div className="grid gap-1.5 sm:col-span-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Input
                                        id="address"
                                        className="cc-field"
                                        autoComplete="street-address"
                                        value={form.data.address}
                                        onChange={(e) => form.setData('address', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.address} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        className="cc-field"
                                        autoComplete="address-level2"
                                        value={form.data.city}
                                        onChange={(e) => form.setData('city', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.city} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="country">Country</Label>
                                    <Input
                                        id="country"
                                        className="cc-field"
                                        autoComplete="country-name"
                                        value={form.data.country}
                                        onChange={(e) => form.setData('country', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.country} />
                                </div>
                            </div>
                        </section>

                        <section className="border-t border-white/8 pt-4" aria-labelledby="representative-heading">
                            <h2 id="representative-heading" className="mb-3 text-sm font-semibold tracking-tight text-white">
                                Authorized representative
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5 sm:col-span-2">
                                    <Label htmlFor="authorized_representative">Full name</Label>
                                    <Input
                                        id="authorized_representative"
                                        className="cc-field"
                                        required
                                        autoComplete="name"
                                        value={form.data.authorized_representative}
                                        onChange={(e) => form.setData('authorized_representative', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.authorized_representative} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="representative_email">Login email</Label>
                                    <Input
                                        id="representative_email"
                                        type="email"
                                        className="cc-field"
                                        required
                                        autoComplete="username"
                                        value={form.data.representative_email}
                                        onChange={(e) => form.setData('representative_email', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.representative_email} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="representative_phone">Phone</Label>
                                    <Input
                                        id="representative_phone"
                                        className="cc-field"
                                        autoComplete="tel"
                                        value={form.data.representative_phone}
                                        onChange={(e) => form.setData('representative_phone', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.representative_phone} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        className="cc-field"
                                        required
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.password} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="password_confirmation">Confirm password</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        className="cc-field"
                                        required
                                        autoComplete="new-password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                        disabled={form.processing}
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="border-t border-white/8 pt-4" aria-labelledby="verification-heading">
                            <h2 id="verification-heading" className="mb-3 text-sm font-semibold tracking-tight text-white">
                                Verification
                            </h2>
                            <div className="grid gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="registration_number">Registration number</Label>
                                    <Input
                                        id="registration_number"
                                        className="cc-field"
                                        value={form.data.registration_number}
                                        onChange={(e) => form.setData('registration_number', e.target.value)}
                                        disabled={form.processing}
                                    />
                                    <InputError message={form.errors.registration_number} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="documents">Supporting documents (optional)</Label>
                                    <Input
                                        id="documents"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        className="cc-field"
                                        onChange={(e) => form.setData('documents', Array.from(e.target.files ?? []))}
                                        disabled={form.processing}
                                    />
                                    <p className="cc-text-tertiary text-xs">PDF or image, up to 10 files, 10MB each.</p>
                                    <InputError message={form.errors.documents} />
                                </div>
                            </div>
                        </section>

                        <div className="border-t border-white/8 pt-4">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="authorization_confirmed"
                                    checked={form.data.authorization_confirmed}
                                    onCheckedChange={(checked) => form.setData('authorization_confirmed', checked === true)}
                                    disabled={form.processing}
                                />
                                <Label htmlFor="authorization_confirmed" className="leading-snug font-normal">
                                    I confirm that I am authorized to submit this registration request on behalf of this bank.
                                </Label>
                            </div>
                            <InputError message={form.errors.authorization_confirmed} className="mt-2" />
                        </div>

                        <FintechFormActions>
                            <Button type="submit" className="cc-dash-btn-primary min-h-11" disabled={form.processing} aria-busy={form.processing}>
                                {form.processing ? 'Submitting…' : 'Submit registration request'}
                            </Button>
                        </FintechFormActions>
                    </FintechFormShell>
                </FintechPage>
            </div>
        </PublicLayout>
    );
}

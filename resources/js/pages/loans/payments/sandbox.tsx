import { FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

type Payment = {
    id: number;
    reference: string;
    amount: string;
    currency: string;
    status: string;
    provider_transaction_id: string | null;
    loan: { id: number; reference: string } | null;
    installment: {
        id: number;
        installment_number: number;
        due_date: string | null;
    } | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    payment: Payment;
};

/**
 * Sandbox checkout UI only. Full PAN / CVV fields are display-only and never submitted.
 * Only outcome + optional card_last_four are POSTed to completeSandbox.
 */
export default function LoanPaymentSandbox({
    eyebrow = 'Sandbox checkout',
    title = 'Complete test payment',
    description = 'Simulate a card payment for local development. No real money is moved.',
    payment,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    // Display-only — never included in form submission
    const [displayPan, setDisplayPan] = useState('4242 4242 4242 4242');
    const [displayExpiry, setDisplayExpiry] = useState('12/30');
    const [displayCvv, setDisplayCvv] = useState('123');

    const form = useForm({
        outcome: 'succeeded' as 'succeeded' | 'failed',
        card_last_four: '4242',
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: '/loans' },
        { title: 'Sandbox payment', href: `/loans/payments/${payment.id}/sandbox` },
    ];

    const syncLastFour = (panDisplay: string) => {
        const digits = panDisplay.replace(/\D/g, '');
        const last4 = digits.slice(-4);
        if (last4.length === 4) {
            form.setData('card_last_four', last4);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) return;
        if (!confirm(`Confirm sandbox ${form.data.outcome} for ${formatMoney(payment.amount)}?`)) return;
        form.post(route('loans.payments.sandbox.complete', payment.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sandbox payment" />
            <FintechPage stagger={false}>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<CreditCard className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        payment.loan ? (
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('loans.active.show', payment.loan.id)}>Back to loan</Link>
                            </Button>
                        ) : null
                    }
                />

                <FintechFlash tone="warning">
                    Sandbox checkout only — no real money moves. Full card numbers and CVV are never submitted; only outcome and last four digits are
                    sent to the server.
                </FintechFlash>

                <FintechPanel className="mx-auto mb-2 w-full max-w-xl">
                    <dl className="grid gap-3 sm:grid-cols-2 text-sm">
                        <div>
                            <dt className="cc-text-tertiary text-xs">Payment</dt>
                            <dd className="mt-1 font-medium text-white">{payment.reference}</dd>
                        </div>
                        <div>
                            <dt className="cc-text-tertiary text-xs">Amount</dt>
                            <dd className="mt-1 font-medium tabular-nums text-white">
                                {formatMoney(payment.amount)} {payment.currency}
                            </dd>
                        </div>
                        {payment.loan ? (
                            <div>
                                <dt className="cc-text-tertiary text-xs">Loan</dt>
                                <dd className="mt-1 font-medium text-white">{payment.loan.reference}</dd>
                            </div>
                        ) : null}
                        {payment.installment ? (
                            <div>
                                <dt className="cc-text-tertiary text-xs">Installment</dt>
                                <dd className="mt-1 font-medium text-white">#{payment.installment.installment_number}</dd>
                            </div>
                        ) : null}
                    </dl>
                </FintechPanel>

                <FintechFormShell as="form" onSubmit={submit} noValidate>
                    <p className="cc-text-secondary text-sm">
                        Card fields below are for UI rehearsal only. They are not stored or submitted as full card numbers.
                    </p>

                    <div className="grid gap-1.5">
                        <Label htmlFor="display_pan">Card number (display only)</Label>
                        <Input
                            id="display_pan"
                            className="cc-field"
                            autoComplete="off"
                            value={displayPan}
                            onChange={(e) => {
                                setDisplayPan(e.target.value);
                                syncLastFour(e.target.value);
                            }}
                            disabled={form.processing}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="display_expiry">Expiry (display only)</Label>
                            <Input
                                id="display_expiry"
                                className="cc-field"
                                autoComplete="off"
                                value={displayExpiry}
                                onChange={(e) => setDisplayExpiry(e.target.value)}
                                disabled={form.processing}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="display_cvv">CVV (display only)</Label>
                            <Input
                                id="display_cvv"
                                className="cc-field"
                                autoComplete="off"
                                value={displayCvv}
                                onChange={(e) => setDisplayCvv(e.target.value)}
                                disabled={form.processing}
                            />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="card_last_four">Last 4 (submitted)</Label>
                        <Input
                            id="card_last_four"
                            className="cc-field"
                            maxLength={4}
                            pattern="[0-9]{4}"
                            value={form.data.card_last_four}
                            onChange={(e) => form.setData('card_last_four', e.target.value.replace(/\D/g, '').slice(0, 4))}
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.card_last_four)}
                        />
                        <InputError message={form.errors.card_last_four} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="outcome">Outcome</Label>
                        <select
                            id="outcome"
                            className="cc-field w-full"
                            value={form.data.outcome}
                            onChange={(e) => form.setData('outcome', e.target.value as 'succeeded' | 'failed')}
                            disabled={form.processing}
                        >
                            <option value="succeeded">Succeeded</option>
                            <option value="failed">Failed</option>
                        </select>
                        <InputError message={form.errors.outcome} />
                    </div>

                    <FintechFormActions>
                        {payment.loan ? (
                            <Button type="button" variant="outline" className="cc-dash-btn-secondary" asChild>
                                <Link href={route('loans.active.show', payment.loan.id)}>Cancel</Link>
                            </Button>
                        ) : null}
                        <Button type="submit" className="cc-dash-btn-primary" disabled={form.processing} aria-busy={form.processing}>
                            {form.processing ? 'Processing…' : `Confirm ${formatMoney(payment.amount)}`}
                        </Button>
                    </FintechFormActions>
                </FintechFormShell>
            </FintechPage>
        </AppLayout>
    );
}

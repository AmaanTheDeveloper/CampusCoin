import { FintechFlash, FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';

type Product = {
    id: number;
    name: string;
    min_amount: string;
    max_amount: string;
    min_tenure_months: number;
    max_tenure_months: number;
    annual_rate_percent: string;
    required_documents: string[];
    bank: { id: number; name: string } | null;
};

type Application = {
    id: number;
    reference: string;
    requested_amount: string;
    requested_tenure_months: number;
    purpose: string | null;
    applicant_notes: string | null;
} | null;

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    product: Product;
    application: Application;
};

export default function LoanApplicationCreate({
    eyebrow = 'Apply',
    title = 'New loan application',
    description,
    product,
    application,
}: Props) {
    const isEdit = Boolean(application);
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    const form = useForm({
        loan_product_id: product.id,
        requested_amount: application?.requested_amount ?? '',
        requested_tenure_months: application?.requested_tenure_months?.toString() ?? '',
        purpose: application?.purpose ?? '',
        applicant_notes: application?.applicant_notes ?? '',
    });

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: '/loans' },
        { title: 'Products', href: '/loans/products' },
        { title: isEdit ? application!.reference : 'Apply', href: isEdit ? `/loans/applications/${application!.id}/edit` : '/loans/applications/create' },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) return;

        if (isEdit && application) {
            form.put(route('loans.applications.update', application.id));
            return;
        }

        form.post(route('loans.applications.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? `Edit ${application!.reference}` : 'New loan application'} />
            <FintechPage stagger={false}>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description ?? `Draft your request for ${product.name}.`}
                    visual={<FileText className="size-14 text-[#E94F09]" aria-hidden />}
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <FintechPanel className="mb-2 max-w-xl mx-auto w-full">
                    <p className="font-medium text-white">{product.name}</p>
                    <p className="cc-text-secondary mt-1 text-sm">
                        {product.bank?.name ?? 'Bank'} · {formatMoney(product.min_amount)}–{formatMoney(product.max_amount)} ·{' '}
                        {product.min_tenure_months}–{product.max_tenure_months} mo · {product.annual_rate_percent}% APR
                    </p>
                </FintechPanel>

                <FintechFormShell as="form" onSubmit={submit} noValidate>
                    {!isEdit ? (
                        <input type="hidden" name="loan_product_id" value={form.data.loan_product_id} />
                    ) : null}

                    <div className="grid gap-1.5">
                        <Label htmlFor="requested_amount">Requested amount</Label>
                        <Input
                            id="requested_amount"
                            type="number"
                            min={product.min_amount}
                            max={product.max_amount}
                            step="0.01"
                            required
                            inputMode="decimal"
                            className="cc-field"
                            value={form.data.requested_amount}
                            onChange={(e) => form.setData('requested_amount', e.target.value)}
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.requested_amount)}
                        />
                        <p className="cc-text-tertiary text-xs">
                            Between {formatMoney(product.min_amount)} and {formatMoney(product.max_amount)}
                        </p>
                        <InputError message={form.errors.requested_amount} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="requested_tenure_months">Tenure (months)</Label>
                        <Input
                            id="requested_tenure_months"
                            type="number"
                            min={product.min_tenure_months}
                            max={product.max_tenure_months}
                            required
                            className="cc-field"
                            value={form.data.requested_tenure_months}
                            onChange={(e) => form.setData('requested_tenure_months', e.target.value)}
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.requested_tenure_months)}
                        />
                        <p className="cc-text-tertiary text-xs">
                            {product.min_tenure_months}–{product.max_tenure_months} months
                        </p>
                        <InputError message={form.errors.requested_tenure_months} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="purpose">Purpose</Label>
                        <Input
                            id="purpose"
                            className="cc-field"
                            maxLength={255}
                            value={form.data.purpose}
                            onChange={(e) => form.setData('purpose', e.target.value)}
                            disabled={form.processing}
                            placeholder="e.g. Tuition, housing, laptop"
                            aria-invalid={Boolean(form.errors.purpose)}
                        />
                        <InputError message={form.errors.purpose} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="applicant_notes">Notes for the bank</Label>
                        <Textarea
                            id="applicant_notes"
                            className="cc-field min-h-24"
                            maxLength={2000}
                            value={form.data.applicant_notes}
                            onChange={(e) => form.setData('applicant_notes', e.target.value)}
                            disabled={form.processing}
                            aria-invalid={Boolean(form.errors.applicant_notes)}
                        />
                        <InputError message={form.errors.applicant_notes} />
                    </div>

                    {product.required_documents.length > 0 ? (
                        <p className="cc-text-secondary text-sm">
                            You can upload required documents ({product.required_documents.join(', ')}) after saving this draft.
                        </p>
                    ) : null}

                    <InputError message={form.errors.loan_product_id} />

                    <FintechFormActions>
                        <Button type="button" variant="outline" className="cc-dash-btn-secondary" asChild>
                            <Link href={isEdit && application ? route('loans.applications.show', application.id) : route('loans.products.show', product.id)}>
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" className="cc-dash-btn-primary" disabled={form.processing} aria-busy={form.processing}>
                            {form.processing ? 'Saving…' : isEdit ? 'Update draft' : 'Save draft'}
                        </Button>
                    </FintechFormActions>
                </FintechFormShell>
            </FintechPage>
        </AppLayout>
    );
}

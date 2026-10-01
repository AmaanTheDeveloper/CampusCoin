import { FintechFormActions, FintechFormShell, FintechPage, FintechPageHeader } from '@/components/fintech-page';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import BankLayout from '@/layouts/bank/bank-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { type FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/bank' },
    { title: 'Products', href: '/bank/products' },
    { title: 'New', href: '/bank/products/create' },
];

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    product: null;
};

export default function BankProductsCreate({
    eyebrow = 'Products',
    title = 'New loan product',
    description = 'Define amounts, tenure, rates, and eligibility for a student product.',
    bank,
}: Props) {
    const form = useForm({
        name: '',
        description: '',
        min_amount: '',
        max_amount: '',
        min_tenure_months: '6',
        max_tenure_months: '36',
        repayment_frequency: 'monthly',
        annual_rate_percent: '',
        processing_fee_percent: '0',
        processing_fee_fixed: '0',
        allows_partial_payments: false,
        required_documents: '' as string,
        eligibility_notes: '' as string,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (form.processing) return;

        form.transform((data) => ({
            ...data,
            required_documents: data.required_documents
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
            eligibility_notes: data.eligibility_notes
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
        }));

        form.post(route('bank.products.store'));
    };

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`New product · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Package className="size-14 text-[#E94F09]" aria-hidden />}
                />

                <FintechFormShell as="form" onSubmit={submit} noValidate>
                    <div className="grid gap-1.5">
                        <Label htmlFor="name">Product name</Label>
                        <Input
                            id="name"
                            className="cc-field"
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="description">Description</Label>
                        <Textarea
                            id="description"
                            className="cc-field min-h-24"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="min_amount">Min amount</Label>
                            <Input
                                id="min_amount"
                                type="number"
                                min="1"
                                step="0.01"
                                required
                                className="cc-field"
                                value={form.data.min_amount}
                                onChange={(e) => form.setData('min_amount', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.min_amount} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="max_amount">Max amount</Label>
                            <Input
                                id="max_amount"
                                type="number"
                                min="1"
                                step="0.01"
                                required
                                className="cc-field"
                                value={form.data.max_amount}
                                onChange={(e) => form.setData('max_amount', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.max_amount} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="min_tenure_months">Min tenure (months)</Label>
                            <Input
                                id="min_tenure_months"
                                type="number"
                                min="1"
                                max="120"
                                required
                                className="cc-field"
                                value={form.data.min_tenure_months}
                                onChange={(e) => form.setData('min_tenure_months', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.min_tenure_months} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="max_tenure_months">Max tenure (months)</Label>
                            <Input
                                id="max_tenure_months"
                                type="number"
                                min="1"
                                max="120"
                                required
                                className="cc-field"
                                value={form.data.max_tenure_months}
                                onChange={(e) => form.setData('max_tenure_months', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.max_tenure_months} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="annual_rate_percent">Annual rate %</Label>
                            <Input
                                id="annual_rate_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                                className="cc-field"
                                value={form.data.annual_rate_percent}
                                onChange={(e) => form.setData('annual_rate_percent', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.annual_rate_percent} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="repayment_frequency">Repayment frequency</Label>
                            <select
                                id="repayment_frequency"
                                className="cc-field w-full"
                                value={form.data.repayment_frequency}
                                onChange={(e) => form.setData('repayment_frequency', e.target.value)}
                                disabled={form.processing}
                            >
                                <option value="monthly">Monthly</option>
                            </select>
                            <InputError message={form.errors.repayment_frequency} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="processing_fee_percent">Processing fee %</Label>
                            <Input
                                id="processing_fee_percent"
                                type="number"
                                min="0"
                                step="0.01"
                                className="cc-field"
                                value={form.data.processing_fee_percent}
                                onChange={(e) => form.setData('processing_fee_percent', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.processing_fee_percent} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="processing_fee_fixed">Processing fee fixed</Label>
                            <Input
                                id="processing_fee_fixed"
                                type="number"
                                min="0"
                                step="0.01"
                                className="cc-field"
                                value={form.data.processing_fee_fixed}
                                onChange={(e) => form.setData('processing_fee_fixed', e.target.value)}
                                disabled={form.processing}
                            />
                            <InputError message={form.errors.processing_fee_fixed} />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-white">
                        <input
                            type="checkbox"
                            checked={form.data.allows_partial_payments}
                            onChange={(e) => form.setData('allows_partial_payments', e.target.checked)}
                            disabled={form.processing}
                        />
                        Allow partial payments
                    </label>

                    <div className="grid gap-1.5">
                        <Label htmlFor="required_documents">Required documents (one per line)</Label>
                        <Textarea
                            id="required_documents"
                            className="cc-field min-h-20"
                            value={form.data.required_documents}
                            onChange={(e) => form.setData('required_documents', e.target.value)}
                            disabled={form.processing}
                            placeholder="Student ID&#10;Enrollment letter"
                        />
                        <InputError message={form.errors.required_documents} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="eligibility_notes">Eligibility notes (one per line)</Label>
                        <Textarea
                            id="eligibility_notes"
                            className="cc-field min-h-20"
                            value={form.data.eligibility_notes}
                            onChange={(e) => form.setData('eligibility_notes', e.target.value)}
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.eligibility_notes} />
                    </div>

                    <FintechFormActions>
                        <Button type="button" variant="outline" className="cc-dash-btn-secondary" asChild>
                            <Link href={route('bank.products.index')}>Cancel</Link>
                        </Button>
                        <Button type="submit" className="cc-dash-btn-primary" disabled={form.processing} aria-busy={form.processing}>
                            {form.processing ? 'Creating…' : 'Create product'}
                        </Button>
                    </FintechFormActions>
                </FintechFormShell>
            </FintechPage>
        </BankLayout>
    );
}

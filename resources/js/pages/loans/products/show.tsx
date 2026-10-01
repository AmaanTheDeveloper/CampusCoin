import { FintechFlash, FintechPage, FintechPageHeader, FintechPanel, FintechSectionHeader } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { useEffect, useState } from 'react';

type Product = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    min_amount: string;
    max_amount: string;
    min_tenure_months: number;
    max_tenure_months: number;
    annual_rate_percent: string;
    repayment_frequency: string;
    processing_fee_percent: string;
    processing_fee_fixed: string;
    allows_partial_payments: boolean;
    required_documents: string[];
    eligibility_notes: string[];
    bank: {
        id: number | null;
        name: string | null;
        city: string | null;
        country: string | null;
    };
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    product: Product;
};

export default function LoanProductShow({
    eyebrow,
    title,
    description,
    product,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: '/loans' },
        { title: 'Products', href: '/loans/products' },
        { title: product.name, href: `/loans/products/${product.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={product.name} />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow={eyebrow ?? product.bank.name ?? 'Loan product'}
                    title={title ?? product.name}
                    description={description ?? product.description ?? 'Review terms and start an application.'}
                    visual={<Landmark className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <>
                            <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                <Link href={route('loans.products.index')}>All products</Link>
                            </Button>
                            <Button asChild className="cc-dash-btn-primary">
                                <Link href={route('loans.applications.create', { product: product.id })}>Apply now</Link>
                            </Button>
                        </>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <FintechPanel className="lg:col-span-2">
                        <FintechSectionHeader title="Terms" />
                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="cc-text-tertiary text-xs">Amount range</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">
                                    {formatMoney(product.min_amount)} – {formatMoney(product.max_amount)}
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Annual rate</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">{product.annual_rate_percent}%</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Tenure</dt>
                                <dd className="mt-1 font-medium text-white">
                                    {product.min_tenure_months}–{product.max_tenure_months} months
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Repayment</dt>
                                <dd className="mt-1 font-medium capitalize text-white">{product.repayment_frequency}</dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Processing fee</dt>
                                <dd className="mt-1 font-medium tabular-nums text-white">
                                    {product.processing_fee_percent}% + {formatMoney(product.processing_fee_fixed)}
                                </dd>
                            </div>
                            <div>
                                <dt className="cc-text-tertiary text-xs">Partial payments</dt>
                                <dd className="mt-1 font-medium text-white">{product.allows_partial_payments ? 'Allowed' : 'Not allowed'}</dd>
                            </div>
                        </dl>
                        {product.description ? <p className="cc-text-secondary mt-5 text-sm leading-relaxed">{product.description}</p> : null}
                    </FintechPanel>

                    <div className="grid gap-4">
                        <FintechPanel>
                            <FintechSectionHeader title="Bank" />
                            <p className="mt-3 font-medium text-white">{product.bank.name ?? '—'}</p>
                            <p className="cc-text-secondary mt-1 text-sm">
                                {[product.bank.city, product.bank.country].filter(Boolean).join(', ') || '—'}
                            </p>
                        </FintechPanel>

                        {product.required_documents.length > 0 ? (
                            <FintechPanel>
                                <FintechSectionHeader title="Required documents" />
                                <ul className="cc-text-secondary mt-3 list-inside list-disc space-y-1 text-sm">
                                    {product.required_documents.map((doc) => (
                                        <li key={doc}>{doc}</li>
                                    ))}
                                </ul>
                            </FintechPanel>
                        ) : null}

                        {product.eligibility_notes.length > 0 ? (
                            <FintechPanel>
                                <FintechSectionHeader title="Eligibility" />
                                <ul className="cc-text-secondary mt-3 list-inside list-disc space-y-1 text-sm">
                                    {product.eligibility_notes.map((note) => (
                                        <li key={note}>{note}</li>
                                    ))}
                                </ul>
                            </FintechPanel>
                        ) : null}
                    </div>
                </div>
            </FintechPage>
        </AppLayout>
    );
}

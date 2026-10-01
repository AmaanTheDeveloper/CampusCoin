import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Landmark, Package } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loans', href: '/loans' },
    { title: 'Products', href: '/loans/products' },
];

type ProductCard = {
    id: number;
    name: string;
    slug: string;
    min_amount: string;
    max_amount: string;
    min_tenure_months: number;
    max_tenure_months: number;
    annual_rate_percent: string;
    bank: {
        id: number | null;
        name: string | null;
        city: string | null;
        country: string | null;
    };
};

type PaginatedProducts = {
    data: ProductCard[];
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    products: PaginatedProducts;
};

export default function LoanProductsIndex({
    eyebrow = 'Catalog',
    title = 'Loan products',
    description = 'Compare published student loan products from approved partner banks.',
    products,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan products" />
            <FintechPage stagger={false} className="min-w-0">
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Package className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <Button asChild variant="outline" className="cc-dash-btn-secondary">
                            <Link href={route('loans.index')}>Loan center</Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {products.data.length === 0 ? (
                    <FintechEmpty
                        visual={<Landmark aria-hidden />}
                        title="No products available"
                        description="Partner banks have not published any student loan products yet."
                        action={
                            <Button asChild className="cc-dash-btn-primary">
                                <Link href={route('loans.index')}>Back to loan center</Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="grid gap-3 md:grid-cols-2">
                            {products.data.map((product) => (
                                <FintechPanel key={product.id} interactive className="flex flex-col gap-4">
                                    <div className="min-w-0 space-y-1">
                                        <h2 className="text-lg font-semibold tracking-tight text-white">{product.name}</h2>
                                        <p className="cc-text-secondary text-sm">
                                            {product.bank.name}
                                            {product.bank.city || product.bank.country
                                                ? ` · ${[product.bank.city, product.bank.country].filter(Boolean).join(', ')}`
                                                : ''}
                                        </p>
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="cc-text-tertiary text-xs">Amount</dt>
                                            <dd className="mt-0.5 font-medium tabular-nums text-white">
                                                {formatMoney(product.min_amount)} – {formatMoney(product.max_amount)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="cc-text-tertiary text-xs">Rate</dt>
                                            <dd className="mt-0.5 font-medium tabular-nums text-white">{product.annual_rate_percent}% APR</dd>
                                        </div>
                                        <div className="col-span-2">
                                            <dt className="cc-text-tertiary text-xs">Tenure</dt>
                                            <dd className="mt-0.5 font-medium text-white">
                                                {product.min_tenure_months}–{product.max_tenure_months} months
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="mt-auto">
                                        <Button asChild className="cc-dash-btn-primary w-full sm:w-auto">
                                            <Link href={route('loans.products.show', product.id)}>View details</Link>
                                        </Button>
                                    </div>
                                </FintechPanel>
                            ))}
                        </div>
                        <PaginationNav links={products.links} />
                    </>
                )}
            </FintechPage>
        </AppLayout>
    );
}

import { FintechEmpty, FintechFlash, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import BankLayout from '@/layouts/bank/bank-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: '/bank' },
    { title: 'Products', href: '/bank/products' },
];

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
    is_published: boolean;
    status: string;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    bank: { id: number; name: string };
    products: Product[];
};

export default function BankProductsIndex({
    eyebrow = 'Products',
    title = 'Loan products',
    description = 'Create and publish student loan products for your bank.',
    bank,
    products,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const [successMessage, setSuccessMessage] = useState<string | null>(flash?.success ?? null);
    const [publishingId, setPublishingId] = useState<number | null>(null);

    useEffect(() => {
        setSuccessMessage(flash?.success ?? null);
    }, [flash?.success]);

    const togglePublish = (product: Product) => {
        if (publishingId !== null) return;
        const next = !product.is_published;
        if (!confirm(next ? `Publish ${product.name}?` : `Unpublish ${product.name}?`)) return;
        setPublishingId(product.id);
        router.post(
            route('bank.products.publish', product.id),
            { publish: next },
            { onFinish: () => setPublishingId(null) },
        );
    };

    return (
        <BankLayout breadcrumbs={breadcrumbs}>
            <Head title={`Products · ${bank.name}`} />
            <FintechPage>
                <FintechPageHeader
                    eyebrow={eyebrow}
                    title={title}
                    description={description}
                    visual={<Package className="size-14 text-[#E94F09]" aria-hidden />}
                    actions={
                        <Button asChild className="cc-dash-btn-primary">
                            <Link href={route('bank.products.create')}>
                                <Plus className="mr-2 size-4" aria-hidden />
                                New product
                            </Link>
                        </Button>
                    }
                />

                {successMessage ? <FintechFlash>{successMessage}</FintechFlash> : null}

                {products.length === 0 ? (
                    <FintechEmpty
                        title="No products yet"
                        description="Create a loan product to offer to students."
                        action={
                            <Button asChild className="cc-dash-btn-primary">
                                <Link href={route('bank.products.create')}>Create product</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-3">
                        {products.map((product) => (
                            <FintechPanel key={product.id} className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold text-white">{product.name}</h2>
                                        <span className="cc-text-tertiary text-xs">
                                            {product.is_published ? 'Published' : 'Draft'} · {product.status}
                                        </span>
                                    </div>
                                    <p className="cc-text-secondary mt-1 text-sm">
                                        {formatMoney(product.min_amount)}–{formatMoney(product.max_amount)} · {product.annual_rate_percent}% ·{' '}
                                        {product.min_tenure_months}–{product.max_tenure_months} mo
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={product.is_published ? 'outline' : 'default'}
                                    className={product.is_published ? 'cc-dash-btn-secondary' : 'cc-dash-btn-primary'}
                                    disabled={publishingId !== null}
                                    onClick={() => togglePublish(product)}
                                >
                                    {publishingId === product.id ? 'Updating…' : product.is_published ? 'Unpublish' : 'Publish'}
                                </Button>
                            </FintechPanel>
                        ))}
                    </div>
                )}
            </FintechPage>
        </BankLayout>
    );
}

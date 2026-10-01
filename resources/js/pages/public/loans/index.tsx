import { FintechEmpty, FintechPage, FintechPageHeader, FintechPanel } from '@/components/fintech-page';
import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { formatMoney } from '@/lib/money';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, FileCheck2, Landmark, WalletCards } from 'lucide-react';

type Product = {
    id: number;
    name: string;
    description: string | null;
    min_amount: string;
    max_amount: string;
    min_tenure_months: number;
    max_tenure_months: number;
    annual_rate_percent: string;
    bank: {
        id: number;
        name: string;
        slug: string;
        city: string | null;
        country: string | null;
    } | null;
};

type Props = {
    eyebrow?: string;
    title?: string;
    description?: string;
    products: Product[];
};

const steps = [
    {
        title: 'Choose a product',
        body: 'Compare published offers from verified partner banks.',
        icon: Building2,
    },
    {
        title: 'Apply in Loan Center',
        body: 'Complete your application, upload documents, and track status.',
        icon: FileCheck2,
    },
    {
        title: 'Bank decides',
        body: 'The bank reviews and manually approves or requests more information.',
        icon: Landmark,
    },
    {
        title: 'Repay installments',
        body: 'After disbursement, pay on schedule with secure card checkout.',
        icon: WalletCards,
    },
] as const;

export default function PublicStudentLoans({
    eyebrow = 'Student loans',
    title = 'Borrow with a verified partner bank',
    description = 'CampusCoin connects students with approved banks. Applications are reviewed by the bank — not by an automated credit score.',
    products,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAuthed = Boolean(auth?.user);
    const primaryHref = isAuthed ? route('loans.index') : route('register');
    const primaryLabel = isAuthed ? 'Open loan center' : 'Apply for a student loan';

    return (
        <PublicLayout title="Student loans" description={description} className="cc-public-loans">
            <section className="border-border/60 border-b bg-[color-mix(in_oklab,var(--muted)_35%,var(--background))]">
                <PublicContainer className="py-12 md:py-16">
                    <p className="cc-public-label mb-3">{eyebrow}</p>
                    <h1 className="cc-public-h1 max-w-3xl">{title}</h1>
                    <p className="cc-public-body mt-4 max-w-2xl">{description}</p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                            <Link href={primaryHref}>
                                {primaryLabel}
                                <ArrowRight className="size-4" aria-hidden />
                            </Link>
                        </Button>
                        {isAuthed ? (
                            <Button asChild size="lg" variant="outline" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('loans.products.index')}>Browse in dashboard</Link>
                            </Button>
                        ) : (
                            <Button asChild size="lg" variant="outline" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('login')}>Log in</Link>
                            </Button>
                        )}
                    </div>
                </PublicContainer>
            </section>

            <PublicContainer className="py-12 md:py-14">
                <p className="cc-public-label mb-2">How it works</p>
                <h2 className="cc-public-h2">From discovery to repayment</h2>
                <ol className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        return (
                            <li key={step.title} className="cc-public-card p-5">
                                <span className="cc-public-label">0{index + 1}</span>
                                <span className="mt-3 flex size-10 items-center justify-center rounded-[calc(var(--radius)-2px)] bg-[var(--cc-orange-soft)] text-[var(--primary)]">
                                    <Icon className="size-5" aria-hidden />
                                </span>
                                <h3 className="mt-3 text-base font-semibold tracking-tight">{step.title}</h3>
                                <p className="text-muted-foreground mt-1.5 text-sm leading-relaxed">{step.body}</p>
                            </li>
                        );
                    })}
                </ol>
            </PublicContainer>

            <PublicContainer className="pb-14 md:pb-16">
                <FintechPage stagger={false} className="bg-transparent p-0 md:p-0">
                    <FintechPageHeader
                        eyebrow="Available now"
                        title="Published loan products"
                        description="Only approved, active partner banks appear here. Amounts and rates come from bank configuration."
                    />

                    {products.length === 0 ? (
                        <FintechEmpty
                            title="No products published yet"
                            description="Partner banks will list student loan products here when available."
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {products.map((product) => (
                                <FintechPanel key={product.id} className="flex flex-col gap-4 !bg-[var(--card)] !text-[var(--card-foreground)]">
                                    <div>
                                        <h2 className="text-lg font-semibold tracking-tight">{product.name}</h2>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {product.bank?.name}
                                            {product.bank?.city || product.bank?.country
                                                ? ` · ${[product.bank.city, product.bank.country].filter(Boolean).join(', ')}`
                                                : ''}
                                        </p>
                                        {product.description ? (
                                            <p className="text-muted-foreground mt-3 line-clamp-3 text-sm leading-relaxed">{product.description}</p>
                                        ) : null}
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-muted-foreground text-xs uppercase tracking-wide">Amount</dt>
                                            <dd className="mt-0.5 font-medium tabular-nums">
                                                {formatMoney(product.min_amount)} – {formatMoney(product.max_amount)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted-foreground text-xs uppercase tracking-wide">Rate</dt>
                                            <dd className="mt-0.5 font-medium tabular-nums">{product.annual_rate_percent}%</dd>
                                        </div>
                                        <div className="col-span-2">
                                            <dt className="text-muted-foreground text-xs uppercase tracking-wide">Tenure</dt>
                                            <dd className="mt-0.5 font-medium">
                                                {product.min_tenure_months}–{product.max_tenure_months} months
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="mt-auto flex flex-wrap gap-2">
                                        {isAuthed ? (
                                            <>
                                                <Button asChild className="cc-dash-btn-primary">
                                                    <Link href={route('loans.applications.create', { product: product.id })}>Apply</Link>
                                                </Button>
                                                <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                                    <Link href={route('loans.products.show', product.id)}>Details</Link>
                                                </Button>
                                            </>
                                        ) : (
                                            <>
                                                <Button asChild className="cc-dash-btn-primary">
                                                    <Link href={route('register')}>Get started to apply</Link>
                                                </Button>
                                                <Button asChild variant="outline" className="cc-dash-btn-secondary">
                                                    <Link href={route('login')}>Log in</Link>
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </FintechPanel>
                            ))}
                        </div>
                    )}
                </FintechPage>
            </PublicContainer>

            <section className="border-border/60 border-t bg-[color-mix(in_oklab,var(--muted)_28%,var(--background))]" aria-labelledby="bank-partner-heading">
                <PublicContainer className="py-12 md:py-14">
                    <p className="cc-public-label mb-2">For banks</p>
                    <h2 id="bank-partner-heading" className="cc-public-h2 max-w-2xl">
                        Offer student loan products on Campus Coin
                    </h2>
                    <p className="cc-public-body mt-3 max-w-2xl">
                        Register your institution, wait for platform review, then publish products students can discover and apply for.
                    </p>
                    <ol className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                        {[
                            'Register',
                            'Submit bank details',
                            'Platform review',
                            'Get approved',
                            'Create loan product',
                            'Publish',
                            'Receive applications',
                        ].map((step, index) => (
                            <li key={step} className="cc-public-card p-4">
                                <span className="cc-public-label">0{index + 1}</span>
                                <p className="mt-2 text-sm font-medium tracking-tight">{step}</p>
                            </li>
                        ))}
                    </ol>
                    <div className="mt-8">
                        <Button asChild className="cc-dash-btn-primary min-h-11">
                            <Link href={route('bank.register')}>
                                Register Your Bank
                                <ArrowRight className="size-4" aria-hidden />
                            </Link>
                        </Button>
                    </div>
                </PublicContainer>
            </section>
        </PublicLayout>
    );
}

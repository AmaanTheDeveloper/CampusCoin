import HomeIntelligenceShowcase from '@/components/public/home/home-intelligence-showcase';
import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Lock, Sparkles } from 'lucide-react';

const DESCRIPTION =
    'Explore Campus Coin Intelligence — Money Pulse, Month Forecast, Reports, Spending Alerts, and Monthly Review. Personal insights require an account.';

export default function PublicIntelligencePreview() {
    const { auth } = usePage<SharedData>().props;
    const isAuthed = Boolean(auth?.user);

    return (
        <PublicLayout title="Intelligence" description={DESCRIPTION} className="cc-public-intelligence">
            <section className="border-border/60 border-b bg-[color-mix(in_oklab,var(--muted)_35%,var(--background))]">
                <PublicContainer className="py-12 md:py-16">
                    <p className="cc-public-label mb-3 inline-flex items-center gap-1.5">
                        <Sparkles className="size-3.5 text-[var(--primary)]" aria-hidden />
                        Intelligence
                    </p>
                    <h1 className="cc-public-h1 max-w-3xl">Turn activity into awareness</h1>
                    <p className="cc-public-body mt-4 max-w-2xl">
                        Preview how Campus Coin surfaces Money Pulse, forecasts, reports, alerts, and monthly review. Your personal Intelligence stays
                        private until you sign in.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        {isAuthed ? (
                            <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                                <Link href={route('intelligence.index')}>
                                    Open Intelligence
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                                    <Link href={route('register')}>
                                        Get Started
                                        <ArrowRight className="size-4" aria-hidden />
                                    </Link>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="cc-dash-btn-secondary min-h-11">
                                    <Link href={route('login')}>Log In</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </PublicContainer>
            </section>

            <HomeIntelligenceShowcase compact guestPrimaryHref={route('register')} guestPrimaryLabel="Get Started" />

            {!isAuthed ? (
                <section className="border-border/60 border-t" aria-labelledby="intel-gate-heading">
                    <PublicContainer className="py-12 md:py-14">
                        <div className="cc-public-card mx-auto max-w-xl p-6 text-center sm:p-8">
                            <span className="mx-auto flex size-12 items-center justify-center rounded-[0.85rem] bg-[var(--cc-orange-soft)] text-[var(--primary)]">
                                <Lock className="size-5" aria-hidden />
                            </span>
                            <h2 id="intel-gate-heading" className="mt-4 text-xl font-semibold tracking-tight">
                                Personal insights require an account
                            </h2>
                            <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                Preview shows what Intelligence can do. Money Pulse, forecasts, reports, and alerts for your own activity unlock after
                                you log in.
                            </p>
                            <div className="mt-6 flex flex-wrap justify-center gap-3">
                                <Button asChild className="cc-dash-btn-primary min-h-11">
                                    <Link href={route('login')}>Log In</Link>
                                </Button>
                                <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11">
                                    <Link href={route('register')}>Get Started</Link>
                                </Button>
                            </div>
                        </div>
                    </PublicContainer>
                </section>
            ) : null}
        </PublicLayout>
    );
}

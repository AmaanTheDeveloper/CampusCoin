import HomeHeroMockup from '@/components/public/home/home-hero-mockup';
import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function HomeHero() {
    const { auth } = usePage<SharedData>().props;
    const isAuthed = Boolean(auth.user);
    const primaryHref = isAuthed ? route('dashboard') : route('register');
    const primaryLabel = isAuthed ? 'Open Dashboard' : 'Get Started';
    const secondaryHref = isAuthed ? route('features') : route('login');
    const secondaryLabel = isAuthed ? 'Explore Features' : 'Log In';

    return (
        <section className="cc-home-hero" aria-labelledby="home-hero-heading">
            <PublicContainer>
                <div className="cc-home-hero__grid">
                    <div>
                        <p className="cc-home-hero__eyebrow cc-home-enter cc-home-enter--1">Student finance app</p>
                        <h1 id="home-hero-heading" className="cc-home-hero__title cc-home-enter cc-home-enter--1">
                            <span>Track campus money</span>
                            <span>with clarity.</span>
                        </h1>
                        <p className="cc-home-hero__lede cc-home-enter cc-home-enter--2">
                            Log spending, scan receipts, set monthly budgets, and use forecasts and alerts — the same tools you get inside the Campus
                            Coin dashboard.
                        </p>
                        <div className="cc-home-hero__actions cc-home-enter cc-home-enter--3">
                            <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                                <Link href={primaryHref}>
                                    {primaryLabel}
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                            <Button asChild variant="outline" size="lg" className="cc-dash-btn-secondary min-h-11">
                                <Link href={secondaryHref}>{secondaryLabel}</Link>
                            </Button>
                        </div>
                        <p className="cc-home-hero__note cc-home-enter cc-home-enter--3">No bank connection — built for student spending.</p>
                    </div>

                    <div>
                        <HomeHeroMockup />
                        <p className="cc-public-caption mt-3 text-center lg:text-left">Illustrative product preview — sample figures only.</p>
                    </div>
                </div>
            </PublicContainer>
        </section>
    );
}

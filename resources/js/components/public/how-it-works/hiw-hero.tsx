import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const pathNodes = ['Account', 'Add money', 'Budgets', 'Dashboard', 'Act'] as const;

export default function HiwHero() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Open Dashboard' : 'Create free account';

    return (
        <section className="cc-hiw-hero" aria-labelledby="hiw-hero-heading">
            <PublicContainer>
                <div className="cc-hiw-hero__grid">
                    <div className="cc-hiw-enter">
                        <p className="cc-hiw-kicker">How it works</p>
                        <h1 id="hiw-hero-heading" className="cc-hiw-hero__title">
                            <span>The real student journey</span>
                            <span>inside Campus Coin.</span>
                        </h1>
                        <p className="cc-hiw-hero__lede">
                            From an empty dashboard to tracking, monthly budgets, forecasts, and alerts — the same path you follow after you sign up.
                        </p>
                        <div className="cc-hiw-hero__actions">
                            <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                                <Link href={primaryHref}>
                                    {primaryLabel}
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                            <Button asChild variant="outline" size="lg" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('features')}>Explore Features</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="cc-hiw-path" aria-hidden="true">
                        <p className="cc-hiw-path__label">Product journey</p>
                        <div className="cc-hiw-path__nodes">
                            {pathNodes.map((node) => (
                                <div key={node} className="cc-hiw-path__node">
                                    <span className="cc-hiw-path__dot" />
                                    <span className="cc-hiw-path__name">{node}</span>
                                </div>
                            ))}
                        </div>
                        <p className="cc-hiw-path__foot">Matches the authenticated student app</p>
                        <p className="cc-hiw-note text-center">Illustrative path — not live account data.</p>
                    </div>
                </div>
            </PublicContainer>
        </section>
    );
}

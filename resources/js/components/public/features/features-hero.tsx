import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const nodes = ['Transactions', 'Smart Scan', 'Budgets', 'Forecast', 'Alerts', 'Reports'] as const;

export default function FeaturesHero() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Open Dashboard' : 'Start Managing Money';

    return (
        <section className="cc-feat-hero" aria-labelledby="features-hero-heading">
            <PublicContainer>
                <div className="cc-feat-hero__grid">
                    <div className="cc-feat-enter">
                        <p className="cc-feat-kicker">Product capabilities</p>
                        <h1 id="features-hero-heading" className="cc-feat-hero__title">
                            <span>Everything you need</span>
                            <span>to understand your money.</span>
                        </h1>
                        <p className="cc-feat-hero__lede">
                            Track spending, Smart Scan receipts, set monthly budgets, read insights, and stay ahead with forecasts and alerts — the same
                            tools as the student app.
                        </p>
                        <div className="cc-feat-hero__actions">
                            <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                                <Link href={primaryHref}>
                                    {primaryLabel}
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                            <Button asChild variant="outline" size="lg" className="cc-dash-btn-secondary min-h-11">
                                <Link href={route('how-it-works')}>How it works</Link>
                            </Button>
                        </div>
                    </div>

                    <div className="cc-feat-eco" aria-hidden="true">
                        <div className="cc-feat-eco__core">
                            <p className="cc-feat-eco__core-label">Ecosystem</p>
                            <p className="cc-feat-eco__core-title">Campus Coin</p>
                        </div>
                        <span className="cc-feat-eco__line" />
                        <div className="cc-feat-eco__nodes">
                            {nodes.map((node) => (
                                <div key={node} className="cc-feat-eco__node">
                                    {node}
                                </div>
                            ))}
                        </div>
                        <span className="cc-feat-eco__line" />
                        <p className="cc-feat-eco__foot">Better decisions</p>
                        <p className="cc-feat-note text-center">Illustrative product map — not live account data.</p>
                    </div>
                </div>
            </PublicContainer>
        </section>
    );
}

import PublicContainer from '@/components/public/public-container';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function AboutHero() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Open Dashboard' : 'Start Managing Money';

    return (
        <section className="cc-about-hero" aria-labelledby="about-hero-heading">
            <PublicContainer>
                <div className="cc-ac-split">
                    <div className="cc-ac-enter">
                        <p className="cc-ac-kicker">About Campus Coin</p>
                        <h1 id="about-hero-heading" className="cc-about-hero__title">
                            <span>Built for students</span>
                            <span>who want to understand their money.</span>
                        </h1>
                        <p className="cc-ac-lede">
                            Campus Coin is designed to make everyday student financial tracking clearer, more understandable, and easier to manage.
                        </p>
                        <div className="cc-about-hero__actions">
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

                    <div className="cc-about-mosaic cc-ac-enter" aria-hidden="true">
                        <div className="cc-about-mosaic__row">
                            <div className="cc-about-chip">
                                Scholarship
                                <span className="cc-about-chip__meta">+ Rs. 25,000 · Example</span>
                            </div>
                            <div className="cc-about-chip cc-about-chip--soft">Food · Budget</div>
                        </div>
                        <div className="cc-about-chip">
                            Food budget
                            <span className="cc-about-chip__meta">Rs. 11,400 / 15,000</span>
                            <div className="cc-about-bar">
                                <span style={{ width: '76%' }} />
                            </div>
                        </div>
                        <div className="cc-about-mosaic__row">
                            <div className="cc-about-chip cc-about-chip--soft">
                                Insight
                                <span className="cc-about-chip__meta">Pattern noticed · Example</span>
                            </div>
                            <div className="cc-about-chip">
                                Laptop goal
                                <span className="cc-about-chip__meta">42% complete</span>
                            </div>
                        </div>
                        <p className="cc-ac-note">Illustrative product fragments — sample figures only.</p>
                    </div>
                </div>
            </PublicContainer>
        </section>
    );
}

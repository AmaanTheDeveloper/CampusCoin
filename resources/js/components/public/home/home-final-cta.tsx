import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function HomeFinalCta() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Go to your dashboard' : 'Create your Campus Coin account';

    return (
        <PublicSection className="cc-home-cta" aria-labelledby="home-cta-heading">
            <PublicContainer>
                <div className="cc-home-cta__panel">
                    <h2 id="home-cta-heading" className="cc-home-section-title">
                        Ready to take control
                        <br className="hidden sm:block" /> of your money?
                    </h2>
                    <p className="cc-home-section-lede mx-auto">
                        Start tracking, budgeting, and building better student money habits in one calm place.
                    </p>
                    <div className="cc-home-cta__actions">
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
            </PublicContainer>
        </PublicSection>
    );
}

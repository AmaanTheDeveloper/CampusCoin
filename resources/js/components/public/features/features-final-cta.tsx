import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function FeaturesFinalCta() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Go to your dashboard' : 'Start managing money';

    return (
        <PublicSection className="cc-feat-cta" aria-labelledby="feat-cta-heading">
            <PublicContainer>
                <div className="cc-feat-cta__panel">
                    <h2 id="feat-cta-heading" className="cc-feat-title">
                        Ready to put these tools
                        <br className="hidden sm:block" /> to work?
                    </h2>
                    <p className="cc-feat-lede mx-auto">
                        Track spending, set budgets, read insights, and build better student money habits in one place.
                    </p>
                    <div className="cc-feat-cta__actions">
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
            </PublicContainer>
        </PublicSection>
    );
}

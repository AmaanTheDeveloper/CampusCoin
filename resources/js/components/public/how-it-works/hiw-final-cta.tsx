import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function HiwFinalCta() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Go to your dashboard' : 'Create your Campus Coin account';

    return (
        <PublicSection className="cc-hiw-cta" aria-labelledby="hiw-cta-heading">
            <PublicContainer>
                <div className="cc-hiw-cta__panel">
                    <h2 id="hiw-cta-heading" className="cc-hiw-title">
                        Your financial journey
                        <br className="hidden sm:block" /> starts with one transaction.
                    </h2>
                    <p className="cc-hiw-lede mx-auto">Start tracking, organizing, and understanding student money — one clear step at a time.</p>
                    <div className="cc-hiw-cta__actions">
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

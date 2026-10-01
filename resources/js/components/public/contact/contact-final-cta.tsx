import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function ContactFinalCta() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('dashboard') : route('register');
    const primaryLabel = auth.user ? 'Go to your dashboard' : 'Create your account';

    return (
        <PublicSection className="cc-contact-cta" aria-labelledby="contact-cta-heading">
            <PublicContainer>
                <h2 id="contact-cta-heading" className="cc-ac-title">
                    Start your Campus Coin journey.
                </h2>
                <p className="cc-ac-lede mx-auto">Track spending, set budgets, and understand student money habits in one place.</p>
                <div className="cc-contact-cta__actions">
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
            </PublicContainer>
        </PublicSection>
    );
}

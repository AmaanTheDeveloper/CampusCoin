import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export default function AboutVision() {
    const { auth } = usePage<SharedData>().props;
    const secondaryHref = auth.user ? route('dashboard') : route('register');
    const secondaryLabel = auth.user ? 'Open Dashboard' : 'Get started';

    return (
        <PublicSection className="cc-about-vision" aria-labelledby="about-vision-heading">
            <PublicContainer>
                <h2 id="about-vision-heading" className="cc-about-vision__title">
                    Make financial awareness a normal part of student life.
                </h2>
                <p className="cc-ac-lede mx-auto text-center">
                    Clear tracking today. Better habits tomorrow. No drama — just a calmer way to understand campus money.
                </p>
                <div className="cc-about-vision__actions">
                    <Button asChild size="lg" className="cc-dash-btn-primary min-h-11">
                        <Link href={route('home')}>
                            Explore Campus Coin
                            <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    </Button>
                    <Button asChild variant="outline" size="lg" className="cc-dash-btn-secondary min-h-11">
                        <Link href={secondaryHref}>{secondaryLabel}</Link>
                    </Button>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, CalendarRange, ScanLine, Target, Wallet } from 'lucide-react';

type Feature = {
    title: string;
    body: string;
    icon: LucideIcon;
};

const features: Feature[] = [
    {
        title: 'Track every rupee',
        body: 'Log income and expenses from allowance to campus cafes — or import a CSV when you already have history.',
        icon: Wallet,
    },
    {
        title: 'Smart Scan',
        body: 'Snap a receipt or card statement and turn it into a reviewable transaction — no bank link required.',
        icon: ScanLine,
    },
    {
        title: 'Monthly budgets',
        body: 'Set category limits for the month and watch progress before spending gets away from you.',
        icon: Target,
    },
    {
        title: 'Alerts & forecasts',
        body: 'See unusual spending, end-of-month pace, and savings progress from one student dashboard.',
        icon: CalendarRange,
    },
];

export default function HomeFeatures() {
    return (
        <PublicSection variant="muted" aria-labelledby="home-features-heading">
            <PublicContainer>
                <p className="cc-home-kicker">Capabilities</p>
                <h2 id="home-features-heading" className="cc-home-section-title">
                    What you can actually do
                </h2>
                <p className="cc-home-section-lede">
                    Real tools from the Campus Coin student app — explained here, used after you sign up.
                </p>

                <div className="cc-home-features cc-home-features--four">
                    {features.map((feature) => {
                        const Icon = feature.icon;
                        return (
                            <article key={feature.title} className="cc-home-feature cc-home-feature--half">
                                <span className="cc-home-feature__icon" aria-hidden="true">
                                    <Icon className="size-5" />
                                </span>
                                <h3>{feature.title}</h3>
                                <p>{feature.body}</p>
                                <Link href={route('features')} className="cc-home-feature__link">
                                    Explore {feature.title}
                                    <ArrowRight className="size-3.5" aria-hidden />
                                </Link>
                            </article>
                        );
                    })}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

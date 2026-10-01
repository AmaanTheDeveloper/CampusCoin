import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const steps = [
    {
        title: 'Create your account',
        body: 'Sign up and land on an empty dashboard ready for your first entries.',
        tags: ['Register', 'Dashboard'],
    },
    {
        title: 'Add your money',
        body: 'Log income and expenses manually, scan a receipt, or import a CSV.',
        tags: ['Manual', 'Smart Scan', 'CSV'],
    },
    {
        title: 'Set monthly limits',
        body: 'Organize with categories, monthly budgets, and an optional savings goal.',
        tags: ['Categories', 'Budgets', 'Goals'],
    },
    {
        title: 'See and adjust',
        body: 'Use pulse, forecasts, reports, and alerts to change course before the month ends.',
        tags: ['Pulse', 'Forecast', 'Alerts'],
    },
] as const;

export default function HomeHowItWorks() {
    return (
        <PublicSection aria-labelledby="home-how-heading">
            <PublicContainer>
                <p className="cc-home-kicker">How it works</p>
                <h2 id="home-how-heading" className="cc-home-section-title">
                    From empty dashboard to clearer spending
                </h2>
                <p className="cc-home-section-lede">
                    The same journey you follow inside Campus Coin — not a generic marketing funnel.
                </p>

                <ol className="cc-home-steps">
                    {steps.map((step, index) => (
                        <li key={step.title} className="cc-home-step">
                            <span className="cc-home-step__num">0{index + 1}</span>
                            <h3>{step.title}</h3>
                            <p>{step.body}</p>
                            <div className="cc-home-step__tags" aria-hidden="true">
                                {step.tags.map((tag) => (
                                    <span key={tag} className="cc-home-step__tag">
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        </li>
                    ))}
                </ol>

                <div className="mt-8">
                    <Button asChild variant="outline" className="cc-dash-btn-secondary">
                        <Link href={route('how-it-works')}>
                            See the full journey
                            <ArrowRight className="size-4" aria-hidden />
                        </Link>
                    </Button>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

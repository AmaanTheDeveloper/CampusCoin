import HiwFinalCta from '@/components/public/how-it-works/hiw-final-cta';
import HiwHero from '@/components/public/how-it-works/hiw-hero';
import HiwScenario from '@/components/public/how-it-works/hiw-scenario';
import HiwStepAction from '@/components/public/how-it-works/hiw-step-action';
import HiwStepAddMoney from '@/components/public/how-it-works/hiw-step-add-money';
import HiwStepBudgets from '@/components/public/how-it-works/hiw-step-budgets';
import HiwStepPatterns from '@/components/public/how-it-works/hiw-step-patterns';
import HiwStepTrack from '@/components/public/how-it-works/hiw-step-track';
import PublicLayout from '@/layouts/public-layout';

const HIW_DESCRIPTION =
    'See how Campus Coin works: create an account, add money, set monthly budgets, understand your dashboard, and act on alerts.';

export default function HowItWorks() {
    return (
        <PublicLayout title="How It Works" description={HIW_DESCRIPTION} className="cc-public-hiw">
            <HiwHero />
            <div className="cc-hiw-rail">
                <HiwStepAddMoney />
                <HiwStepTrack />
                <HiwStepBudgets />
                <HiwStepPatterns />
                <HiwStepAction />
            </div>
            <HiwScenario />
            <HiwFinalCta />
        </PublicLayout>
    );
}

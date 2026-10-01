import HomeHero from '@/components/public/home/home-hero';
import PublicLayout from '@/layouts/public-layout';
import { lazy, Suspense } from 'react';

const HomeProblem = lazy(() => import('@/components/public/home/home-problem'));
const HomeFeatures = lazy(() => import('@/components/public/home/home-features'));
const HomeDashboardPreview = lazy(() => import('@/components/public/home/home-dashboard-preview'));
const HomeIntelligenceShowcase = lazy(() => import('@/components/public/home/home-intelligence-showcase'));
const HomeHowItWorks = lazy(() => import('@/components/public/home/home-how-it-works'));
const HomeFinalCta = lazy(() => import('@/components/public/home/home-final-cta'));

const HOME_DESCRIPTION =
    'Campus Coin is a student-first budgeting and expense-tracking platform — track spending, set monthly budgets, and build smarter money habits.';

export default function Welcome() {
    return (
        <PublicLayout title="Smart Spending, Student Style" description={HOME_DESCRIPTION} className="cc-public-home">
            <HomeHero />
            <Suspense fallback={null}>
                <HomeProblem />
                <HomeFeatures />
                <HomeDashboardPreview />
                <HomeIntelligenceShowcase />
                <HomeHowItWorks />
                <HomeFinalCta />
            </Suspense>
        </PublicLayout>
    );
}

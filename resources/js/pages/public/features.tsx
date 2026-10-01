import FeaturesAi from '@/components/public/features/features-ai';
import FeaturesBudgets from '@/components/public/features/features-budgets';
import FeaturesCategories from '@/components/public/features/features-categories';
import FeaturesFinalCta from '@/components/public/features/features-final-cta';
import FeaturesHero from '@/components/public/features/features-hero';
import FeaturesImport from '@/components/public/features/features-import';
import FeaturesNav from '@/components/public/features/features-nav';
import FeaturesNotifications from '@/components/public/features/features-notifications';
import FeaturesReports from '@/components/public/features/features-reports';
import FeaturesSaving from '@/components/public/features/features-saving';
import FeaturesSmartScan from '@/components/public/features/features-smart-scan';
import FeaturesStayAhead from '@/components/public/features/features-stay-ahead';
import FeaturesTransactions from '@/components/public/features/features-transactions';
import PublicLayout from '@/layouts/public-layout';

const FEATURES_DESCRIPTION =
    'Explore Campus Coin features: tracking, Smart Scan, monthly budgets, AI insights, reports, forecasts, alerts, and CSV import.';

export default function Features() {
    return (
        <PublicLayout title="Features — Smart Student Finance Tools" description={FEATURES_DESCRIPTION} className="cc-public-features">
            <FeaturesHero />
            <FeaturesNav />
            <FeaturesTransactions />
            <FeaturesSmartScan />
            <FeaturesImport />
            <FeaturesCategories />
            <FeaturesBudgets />
            <FeaturesReports />
            <FeaturesAi />
            <FeaturesStayAhead />
            <FeaturesSaving />
            <FeaturesNotifications />
            <FeaturesFinalCta />
        </PublicLayout>
    );
}

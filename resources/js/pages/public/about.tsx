import AboutHero from '@/components/public/about/about-hero';
import AboutPhilosophy from '@/components/public/about/about-philosophy';
import AboutTech from '@/components/public/about/about-tech';
import AboutVision from '@/components/public/about/about-vision';
import PublicLayout from '@/layouts/public-layout';

const ABOUT_DESCRIPTION =
    'Campus Coin is a student-first budgeting platform — track spending, set monthly budgets, and understand patterns with responsible technology.';

export default function About() {
    return (
        <PublicLayout title="About" description={ABOUT_DESCRIPTION} className="cc-public-about">
            <AboutHero />
            <AboutPhilosophy />
            <AboutTech />
            <AboutVision />
        </PublicLayout>
    );
}

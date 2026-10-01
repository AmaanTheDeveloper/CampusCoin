import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Eye, MonitorSmartphone, MousePointerClick, Type } from 'lucide-react';

const points = [
    {
        title: 'Responsive',
        body: 'Works across desktop, tablet and mobile.',
        icon: MonitorSmartphone,
    },
    {
        title: 'Accessible',
        body: 'Clear typography, navigation and interactive controls.',
        icon: Eye,
    },
    {
        title: 'Readable',
        body: 'Financial information stays easy to understand.',
        icon: Type,
    },
    {
        title: 'Smooth',
        body: 'Transitions and loading states keep the experience clear.',
        icon: MousePointerClick,
    },
] as const;

export default function FeaturesUx() {
    return (
        <PublicSection id="experience" variant="muted" aria-labelledby="feat-ux-heading">
            <PublicContainer>
                <p className="cc-feat-kicker">Student-first experience</p>
                <h2 id="feat-ux-heading" className="cc-feat-title">
                    Designed for real student life.
                </h2>
                <p className="cc-feat-lede">Clear, legible, responsive, and user-friendly — built for how students actually move through the day.</p>

                <div className="cc-feat-ux mt-8">
                    {points.map((point) => {
                        const Icon = point.icon;
                        return (
                            <article key={point.title} className="cc-feat-ux__card">
                                <span className="cc-feat-notice__icon" aria-hidden="true">
                                    <Icon className="size-4" />
                                </span>
                                <h3>{point.title}</h3>
                                <p>{point.body}</p>
                            </article>
                        );
                    })}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

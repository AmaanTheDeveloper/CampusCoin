import PublicContainer from '@/components/public/public-container';
import { useEffect, useState } from 'react';

const items = [
    { id: 'tracking', label: 'Tracking' },
    { id: 'smart-scan', label: 'Smart Scan' },
    { id: 'import', label: 'Import' },
    { id: 'categories', label: 'Categories' },
    { id: 'budgets', label: 'Budgets' },
    { id: 'reports', label: 'Reports' },
    { id: 'ai', label: 'AI' },
    { id: 'stay-ahead', label: 'Stay ahead' },
    { id: 'saving', label: 'Saving' },
    { id: 'alerts', label: 'Alerts' },
] as const;

export default function FeaturesNav() {
    const [active, setActive] = useState<string>(items[0].id);

    useEffect(() => {
        const sections = items.map((item) => document.getElementById(item.id)).filter((el): el is HTMLElement => Boolean(el));

        if (!sections.length) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                if (visible[0]?.target?.id) {
                    setActive(visible[0].target.id);
                }
            },
            { rootMargin: '-20% 0px -55% 0px', threshold: [0.15, 0.35, 0.55] },
        );

        sections.forEach((section) => observer.observe(section));
        return () => observer.disconnect();
    }, []);

    return (
        <div className="cc-feat-nav-wrap">
            <PublicContainer>
                <nav className="cc-feat-nav" aria-label="Features on this page">
                    {items.map((item) => (
                        <a
                            key={item.id}
                            href={`#${item.id}`}
                            className="cc-feat-nav__btn"
                            aria-current={active === item.id ? 'true' : undefined}
                            onClick={() => setActive(item.id)}
                        >
                            {item.label}
                        </a>
                    ))}
                </nav>
            </PublicContainer>
        </div>
    );
}

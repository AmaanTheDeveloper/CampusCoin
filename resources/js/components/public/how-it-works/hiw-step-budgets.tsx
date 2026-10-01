import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { useEffect, useRef, useState } from 'react';

const budgets = [
    { name: 'Food', spent: 'Rs. 11,400', limit: 'Rs. 15,000', pct: 76, state: 'healthy' as const },
    { name: 'Transport', spent: 'Rs. 4,200', limit: 'Rs. 8,000', pct: 52, state: 'healthy' as const },
    { name: 'Academics', spent: 'Rs. 6,800', limit: 'Rs. 7,000', pct: 97, state: 'near' as const },
] as const;

export default function HiwStepBudgets() {
    const [animated, setAnimated] = useState(false);
    const [reducedMotion, setReducedMotion] = useState(false);
    const ref = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        setReducedMotion(media.matches);
        const onChange = () => setReducedMotion(media.matches);
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, []);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;

        if (reducedMotion) {
            setAnimated(true);
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    setAnimated(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.3 },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [reducedMotion]);

    return (
        <PublicSection id="step-budgets" variant="muted" aria-labelledby="hiw-step-03">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-hiw-kicker">03 — Organize</p>
                    <h2 id="hiw-step-03" className="cc-hiw-title">
                        Categories, monthly budgets, and a savings goal
                    </h2>
                    <p className="cc-hiw-lede">
                        Group spending into categories, set monthly category limits, and optionally track a long-term savings goal — the same tools
                        inside the student app.
                    </p>
                </div>

                <div ref={ref} className="cc-hiw-budgets" aria-label="Example monthly budgets">
                    {budgets.map((budget) => (
                        <div
                            key={budget.name}
                            className={`cc-hiw-budget ${budget.state === 'near' ? 'cc-hiw-budget--warn' : ''} ${animated ? 'is-animated' : ''}`}
                        >
                            <div className="cc-hiw-budget__top">
                                <span>{budget.name}</span>
                                <span>{budget.pct}%</span>
                            </div>
                            <div className="cc-hiw-budget__bar" aria-hidden="true">
                                <span style={{ width: animated || reducedMotion ? `${budget.pct}%` : '0%' }} />
                            </div>
                            <p className="cc-hiw-budget__meta">
                                {budget.spent} / {budget.limit}
                                {budget.state === 'near' ? ' · Near monthly limit' : ' · On track'}
                            </p>
                        </div>
                    ))}
                </div>
                <p className="cc-hiw-note">Illustrative monthly budgets — not live account data.</p>
            </PublicContainer>
        </PublicSection>
    );
}

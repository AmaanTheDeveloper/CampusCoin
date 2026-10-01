import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const steps = [
    { label: 'Income', hint: 'Allowance, jobs, gifts' },
    { label: 'Transactions', hint: 'Every rupee recorded' },
    { label: 'Budgets', hint: 'Category limits that stick' },
    { label: 'Insights', hint: 'Patterns made readable' },
    { label: 'Better decisions', hint: 'Spend with intention' },
] as const;

export default function HomeSolution() {
    return (
        <PublicSection aria-labelledby="home-solution-heading">
            <PublicContainer>
                <p className="cc-home-kicker">The solution</p>
                <h2 id="home-solution-heading" className="cc-home-section-title">
                    Meet Campus Coin
                </h2>
                <p className="cc-home-section-lede">Campus Coin brings everyday student finances into one clear experience.</p>

                <ol className="cc-home-flow">
                    {steps.map((step, index) => (
                        <li key={step.label} className="cc-home-flow__step">
                            <span className="cc-home-flow__num">{String(index + 1).padStart(2, '0')}</span>
                            <span className="cc-home-flow__label">{step.label}</span>
                            <span className="cc-home-flow__hint">{step.hint}</span>
                        </li>
                    ))}
                </ol>
            </PublicContainer>
        </PublicSection>
    );
}

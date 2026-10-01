import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const chips = ['Income', 'Expenses', 'Categories', 'Budgets', 'Reports', 'Goals', 'Insights'] as const;

export default function HiwEcosystem() {
    return (
        <PublicSection className="cc-hiw-ecosystem" aria-labelledby="hiw-ecosystem-heading">
            <PublicContainer>
                <h2 id="hiw-ecosystem-heading" className="cc-hiw-ecosystem__title">
                    Your money should not feel scattered across different places.
                </h2>
                <p className="cc-hiw-lede mx-auto mt-4 text-center">One Campus Coin ecosystem connects the pieces into a single student journey.</p>
                <div className="cc-hiw-chips" aria-hidden="true">
                    <span className="cc-hiw-chip cc-hiw-chip--core">Campus Coin</span>
                    {chips.map((chip) => (
                        <span key={chip} className="cc-hiw-chip">
                            {chip}
                        </span>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

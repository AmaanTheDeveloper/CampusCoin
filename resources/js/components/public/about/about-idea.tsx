import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const pieces = ['Campus Coin', 'Income', 'Expenses', 'Categories', 'Budgets', 'Reports', 'Insights', 'Saving goals'] as const;

export default function AboutIdea() {
    return (
        <PublicSection id="idea" aria-labelledby="about-idea-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">The idea</p>
                <h2 id="about-idea-heading" className="cc-ac-title">
                    One place
                    <br className="hidden sm:block" /> to see the bigger picture.
                </h2>
                <p className="cc-ac-lede">
                    Campus Coin brings student income, spending, categories, budgets, reports, insights and saving goals into one focused experience —
                    so the full picture is easier to hold.
                </p>
                <div className="cc-about-eco" aria-hidden="true">
                    {pieces.map((piece) => (
                        <span key={piece}>{piece}</span>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

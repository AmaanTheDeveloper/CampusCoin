import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

export default function AboutAi() {
    return (
        <PublicSection id="responsible-ai" className="cc-about-ai !py-16 sm:!py-20" aria-labelledby="about-ai-heading">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-ac-kicker">Responsible technology</p>
                    <h2 id="about-ai-heading" className="cc-ac-title">
                        Technology should assist,
                        <br className="hidden sm:block" /> not replace judgment.
                    </h2>
                    <p className="cc-ac-lede">
                        Campus Coin can use AI-assisted categorization and insights to help students review their financial patterns. The student
                        remains in control — suggestions can be reviewed or overridden.
                    </p>
                </div>

                <div className="cc-about-ai-card" aria-label="Example AI insight">
                    <p className="cc-about-ai-card__label">Insight · Example</p>
                    <p>Food spending is trending above your recent average.</p>
                    <p className="cc-about-ai-card__label mt-4">Why</p>
                    <p>Most of the increase came later in the month.</p>
                    <p className="cc-about-ai-card__label mt-4">Suggested action</p>
                    <p>Consider setting a monthly food budget.</p>
                </div>
                <p className="cc-about-ai-disclaimer">Illustrative example — advisory only, not financial certainty or automated decisions.</p>
            </PublicContainer>
        </PublicSection>
    );
}

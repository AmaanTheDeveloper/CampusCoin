import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const stack = ['Laravel', 'React', 'Inertia', 'MySQL', 'AI-assisted services'] as const;

export default function AboutTech() {
    return (
        <PublicSection id="technology" variant="muted" aria-labelledby="about-tech-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">Technology</p>
                <h2 id="about-tech-heading" className="cc-ac-title">
                    Built as a real
                    <br className="hidden sm:block" /> full-stack product.
                </h2>
                <p className="cc-ac-lede">
                    Campus Coin is engineered as a modern web application — a product students can actually use, not a concept deck.
                </p>
                <div className="cc-about-tech" aria-label="Technology stack">
                    {stack.map((item) => (
                        <span key={item}>{item}</span>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

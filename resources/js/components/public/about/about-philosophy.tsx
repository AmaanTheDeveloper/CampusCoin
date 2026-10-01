import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const principles = [
    {
        title: 'Simple',
        body: 'Financial tracking should feel understandable, not intimidating.',
    },
    {
        title: 'Transparent',
        body: 'Students should be able to see where their money goes and understand the information shown to them.',
    },
    {
        title: 'Intelligent',
        body: 'Technology should help students identify patterns and possibilities without making financial decisions for them.',
    },
] as const;

export default function AboutPhilosophy() {
    return (
        <PublicSection id="philosophy" variant="muted" aria-labelledby="about-philosophy-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">Our philosophy</p>
                <h2 id="about-philosophy-heading" className="cc-ac-title">
                    Principles that keep Campus Coin student-first
                </h2>
                <div className="cc-about-principles">
                    {principles.map((item, index) => (
                        <article key={item.title} className="cc-about-principle">
                            <p className="cc-ac-kicker !mb-2">0{index + 1}</p>
                            <h3>{item.title}</h3>
                            <p>{item.body}</p>
                        </article>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

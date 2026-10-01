import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const problems = [
    {
        title: 'Scattered spending',
        body: 'Allowance, part-time income, gifts, scholarships and everyday expenses can become difficult to track.',
        chips: [
            { label: 'Allowance', style: { top: '18%', left: '10%', transform: 'rotate(-6deg)' } },
            { label: 'Food', style: { top: '42%', left: '48%', transform: 'rotate(4deg)' } },
            { label: 'Transport', style: { top: '62%', left: '18%', transform: 'rotate(8deg)' } },
        ],
    },
    {
        title: 'No clear picture',
        body: 'Without a complete view, students may not know which categories consume most of their money.',
        chips: [
            { label: '??', style: { top: '22%', left: '22%', opacity: 0.45 } },
            { label: 'Blur', style: { top: '48%', left: '40%', opacity: 0.55 } },
            { label: '?', style: { top: '30%', left: '62%', opacity: 0.4 } },
        ],
    },
    {
        title: 'Difficult to save',
        body: 'Without tracking, budgets and goals, saving can become guesswork.',
        chips: [
            { label: 'Goal 18%', style: { top: '28%', left: '24%' } },
            { label: 'Gap', style: { top: '55%', left: '48%' } },
        ],
    },
] as const;

export default function HomeProblem() {
    return (
        <PublicSection variant="muted" aria-labelledby="home-problem-heading">
            <PublicContainer>
                <p className="cc-home-kicker">The problem</p>
                <h2 id="home-problem-heading" className="cc-home-section-title">
                    Where does your money
                    <br className="hidden sm:block" /> actually go?
                </h2>
                <p className="cc-home-section-lede">
                    Student money moves fast across many sources and categories. Without a clear system, patterns stay invisible until the month runs
                    out.
                </p>

                <div className="cc-home-problem-grid">
                    {problems.map((item, index) => (
                        <article key={item.title} className="cc-home-problem-card">
                            <div className="cc-home-problem-card__visual" aria-hidden="true">
                                {item.chips.map((chip) => (
                                    <span key={chip.label} className="cc-home-chip" style={chip.style}>
                                        {chip.label}
                                    </span>
                                ))}
                                <span className="text-muted-foreground absolute right-3 bottom-2 text-[10px] font-semibold tracking-wider uppercase">
                                    0{index + 1}
                                </span>
                            </div>
                            <h3>{item.title}</h3>
                            <p>{item.body}</p>
                        </article>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

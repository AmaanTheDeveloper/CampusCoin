import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const concepts = [
    {
        num: '01',
        title: 'Many sources',
        body: 'Allowance, part-time income, scholarships and gifts often arrive from different places — and can be hard to see together.',
    },
    {
        num: '02',
        title: 'Everyday spending',
        body: 'Food, transport, academics and subscriptions move quickly. Without a clear record, patterns stay invisible.',
    },
    {
        num: '03',
        title: 'Different goals',
        body: 'Students save for exams, devices, travel or next semester — goals that need measurable progress, not guesswork.',
    },
] as const;

export default function AboutProblem() {
    return (
        <PublicSection id="student-context" variant="muted" aria-labelledby="about-problem-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">The context</p>
                <h2 id="about-problem-heading" className="cc-ac-title">
                    Student money is
                    <br className="hidden sm:block" /> different.
                </h2>
                <p className="cc-ac-lede">
                    Campus life mixes irregular income with constant small expenses. Understanding where money goes should be simple enough to fit a
                    student schedule.
                </p>

                <div className="cc-about-triad">
                    {concepts.map((item) => (
                        <article key={item.num} className="cc-about-triad__card">
                            <p className="cc-about-triad__num">{item.num}</p>
                            <h3>{item.title}</h3>
                            <p>{item.body}</p>
                        </article>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

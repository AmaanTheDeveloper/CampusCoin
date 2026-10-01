import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const values = [
    { title: 'Student-first', body: 'Built around campus income, spending and goals — not corporate finance workflows.' },
    { title: 'Clear', body: 'Numbers, categories and progress should stay readable without jargon.' },
    { title: 'Practical', body: 'Tracking, budgets and reports should help with real week-to-week decisions.' },
    {
        title: 'Private-minded',
        body: 'Designed with responsible handling of student financial information in mind.',
    },
    { title: 'Accessible', body: 'Clear typography, navigation and controls across desktop and mobile.' },
    { title: 'Action-oriented', body: 'Insights and tips point toward next steps — without making decisions for you.' },
] as const;

export default function AboutValues() {
    return (
        <PublicSection id="values" aria-labelledby="about-values-heading">
            <PublicContainer>
                <p className="cc-ac-kicker">Product values</p>
                <h2 id="about-values-heading" className="cc-ac-title">
                    What we optimize for
                </h2>
                <div className="cc-about-values">
                    {values.map((value) => (
                        <article key={value.title} className="cc-about-value">
                            <h3>{value.title}</h3>
                            <p>{value.body}</p>
                        </article>
                    ))}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

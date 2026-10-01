import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const outcomes = [
    {
        title: 'Spending alerts',
        body: 'Review unusual spends against your category averages before they stack up.',
    },
    {
        title: 'Recurring & review',
        body: 'Approve repeating expenses and walk through a monthly review of what changed.',
    },
    {
        title: 'Savings progress',
        body: 'Watch a long-term goal move forward as you stay within monthly limits.',
    },
] as const;

export default function HiwStepAction() {
    return (
        <PublicSection id="step-action" aria-labelledby="hiw-step-05">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-hiw-kicker">05 — Act</p>
                    <h2 id="hiw-step-05" className="cc-hiw-title">
                        Respond to alerts, recurring items, and reviews
                    </h2>
                    <p className="cc-hiw-lede">
                        Campus Coin surfaces the next useful action — not a fake scoreboard. Alerts, recurring approvals, and monthly reviews stay
                        inside your account.
                    </p>
                </div>

                <div className="cc-hiw-actions">
                    {outcomes.map((item) => (
                        <article key={item.title} className="cc-hiw-action">
                            <h3>{item.title}</h3>
                            <p>{item.body}</p>
                        </article>
                    ))}
                </div>

                <aside className="cc-hiw-goal" aria-label="Example saving goal">
                    <p className="cc-hiw-kicker !mb-2">Laptop goal · Example</p>
                    <p className="text-lg font-semibold tracking-tight">New laptop</p>
                    <p className="cc-hiw-goal__amount mt-2">Rs. 12,500 saved</p>
                    <p className="mt-1 text-sm font-bold text-[var(--cc-accent-text,#d14508)]">42% complete</p>
                    <div className="cc-hiw-progress" aria-hidden="true">
                        <span />
                    </div>
                    <p className="cc-hiw-note">Illustrative example — not linked to any account.</p>
                </aside>
            </PublicContainer>
        </PublicSection>
    );
}

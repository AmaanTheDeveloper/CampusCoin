import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Bell, Brain, CalendarClock } from 'lucide-react';

const notices = [
    {
        kind: 'Budget alert',
        text: 'Food budget is nearly reached.',
        icon: Bell,
    },
    {
        kind: 'Insight',
        text: 'Your monthly insight is ready.',
        icon: Brain,
    },
    {
        kind: 'Recurring',
        text: 'An upcoming recurring payment needs your approval.',
        icon: CalendarClock,
    },
] as const;

export default function FeaturesNotifications() {
    return (
        <PublicSection id="alerts" aria-labelledby="feat-alerts-heading">
            <PublicContainer>
                <div className="cc-feat-split">
                    <div>
                        <p className="cc-feat-kicker">Notifications & alerts</p>
                        <h2 id="feat-alerts-heading" className="cc-feat-title">
                            Stay aware
                            <br className="hidden sm:block" /> without constantly checking.
                        </h2>
                        <p className="cc-feat-lede">
                            Budget awareness, monthly insights, and recurring approval prompts help students stay informed when it matters — without
                            living inside the app.
                        </p>
                    </div>

                    <div className="cc-feat-notices" aria-label="Example notifications">
                        {notices.map((notice) => {
                            const Icon = notice.icon;
                            return (
                                <article key={notice.kind} className="cc-feat-notice">
                                    <span className="cc-feat-notice__icon" aria-hidden="true">
                                        <Icon className="size-4" />
                                    </span>
                                    <div>
                                        <p className="cc-feat-notice__kind">{notice.kind}</p>
                                        <p className="cc-feat-notice__text">{notice.text}</p>
                                    </div>
                                </article>
                            );
                        })}
                        <p className="cc-feat-note">Illustrative examples of in-product awareness — not live notifications.</p>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

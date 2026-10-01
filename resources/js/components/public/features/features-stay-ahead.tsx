import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { AlertTriangle, CalendarRange, ClipboardList, RefreshCw } from 'lucide-react';

const items = [
    {
        title: 'Spending alerts',
        body: 'Flag unusual spends against category averages so you can review before the month slips.',
        icon: AlertTriangle,
    },
    {
        title: 'Month forecast',
        body: 'See end-of-month pace from current spending — the same forecast card on your dashboard.',
        icon: CalendarRange,
    },
    {
        title: 'Monthly review',
        body: 'Walk through what changed month over month when you are ready to look back.',
        icon: ClipboardList,
    },
    {
        title: 'Recurring',
        body: 'Schedule repeating income or expenses and approve upcoming occurrences when prompted.',
        icon: RefreshCw,
    },
] as const;

export default function FeaturesStayAhead() {
    return (
        <PublicSection id="stay-ahead" variant="muted" aria-labelledby="feat-ahead-heading">
            <PublicContainer>
                <p className="cc-feat-kicker">Stay ahead</p>
                <h2 id="feat-ahead-heading" className="cc-feat-title">
                    Alerts, forecasts, reviews, and recurring
                </h2>
                <p className="cc-feat-lede">
                    Intelligence tools from the student sidebar — explained publicly as capabilities. Personal alerts and approvals stay inside your
                    account.
                </p>

                <div className="mt-8 grid gap-4 sm:grid-cols-2">
                    {items.map((item) => {
                        const Icon = item.icon;
                        return (
                            <article key={item.title} className="cc-feat-panel">
                                <div className="cc-feat-panel__body flex gap-4">
                                    <span className="cc-feat-notice__icon shrink-0" aria-hidden="true">
                                        <Icon className="size-5" />
                                    </span>
                                    <div>
                                        <h3 className="text-base font-semibold tracking-tight">{item.title}</h3>
                                        <p className="cc-feat-lede !mt-1.5 !text-sm">{item.body}</p>
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

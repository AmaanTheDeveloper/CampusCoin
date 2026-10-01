import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { useState } from 'react';

const hotspots = [
    { id: 'balance', label: 'Balance' },
    { id: 'budget', label: 'Budget' },
    { id: 'ai', label: 'AI Insight' },
    { id: 'goal', label: 'Saving Goal' },
    { id: 'activity', label: 'Activity' },
] as const;

type HotspotId = (typeof hotspots)[number]['id'];

export default function HomeDashboardPreview() {
    const [active, setActive] = useState<HotspotId>('balance');

    return (
        <PublicSection variant="muted" aria-labelledby="home-dash-heading">
            <PublicContainer>
                <p className="cc-home-kicker">Product experience</p>
                <h2 id="home-dash-heading" className="cc-home-section-title">
                    Your financial command center
                </h2>
                <p className="cc-home-section-lede">
                    Balance, monthly budgets, insights, savings goals, and recent activity — the same building blocks as the real student dashboard
                    (illustrative preview, not live account data).
                </p>

                <div className="cc-home-dash">
                    <div className="cc-home-dash__hotspots" role="group" aria-label="Preview highlights">
                        {hotspots.map((spot) => (
                            <button
                                key={spot.id}
                                type="button"
                                className="cc-home-dash__hotspot"
                                aria-pressed={active === spot.id}
                                onMouseEnter={() => setActive(spot.id)}
                                onFocus={() => setActive(spot.id)}
                                onClick={() => setActive(spot.id)}
                            >
                                {spot.label}
                            </button>
                        ))}
                    </div>

                    <div className="cc-home-dash__frame" aria-hidden="true">
                        <div className="cc-home-dash__chrome">
                            <span className="cc-home-dash__dot" />
                            <span className="cc-home-dash__dot" />
                            <span className="cc-home-dash__dot" />
                            <span className="cc-public-caption ml-2">Campus Coin · Example dashboard</span>
                        </div>

                        <div className="cc-home-dash__grid">
                            <div className="cc-home-dash__tile cc-home-dash__tile--balance" data-active={active === 'balance'}>
                                <p className="cc-home-dash__label">Balance</p>
                                <p className="cc-home-dash__value">Rs. 17,500</p>
                            </div>
                            <div className="cc-home-dash__tile cc-home-dash__tile--income" data-active={active === 'balance'}>
                                <p className="cc-home-dash__label">Income</p>
                                <p className="cc-home-dash__value text-[1.1rem]">Rs. 60,000</p>
                            </div>
                            <div className="cc-home-dash__tile cc-home-dash__tile--expense" data-active={active === 'balance'}>
                                <p className="cc-home-dash__label">Expenses</p>
                                <p className="cc-home-dash__value text-[1.1rem]">Rs. 42,500</p>
                            </div>

                            <div className="cc-home-dash__tile cc-home-dash__tile--budget" data-active={active === 'budget'}>
                                <p className="cc-home-dash__label">Budget · Food</p>
                                <p className="mt-2 text-sm font-semibold">Rs. 9,200 / 12,000</p>
                                <div className="bg-muted mt-3 h-2 overflow-hidden rounded-full">
                                    <div className="bg-primary h-full w-[76%] rounded-full" />
                                </div>
                            </div>

                            <div className="cc-home-dash__tile cc-home-dash__tile--goal" data-active={active === 'goal'}>
                                <p className="cc-home-dash__label">Saving goal</p>
                                <p className="mt-2 text-sm font-semibold">New laptop · 42%</p>
                                <div className="bg-muted mt-3 h-2 overflow-hidden rounded-full">
                                    <div className="h-full w-[42%] rounded-full bg-[var(--chart-5)]" />
                                </div>
                            </div>

                            <div className="cc-home-dash__tile cc-home-dash__tile--ai" data-active={active === 'ai'}>
                                <p className="cc-home-dash__label">AI insight</p>
                                <p className="mt-2 text-sm leading-snug">Food spending is trending above your recent average.</p>
                            </div>

                            <div className="cc-home-dash__tile cc-home-dash__tile--activity" data-active={active === 'activity'}>
                                <p className="cc-home-dash__label">Recent activity</p>
                                <ul className="mt-2 space-y-1.5 text-sm">
                                    <li className="flex justify-between gap-3">
                                        <span className="text-muted-foreground">Campus cafe</span>
                                        <span className="font-medium">− Rs. 450</span>
                                    </li>
                                    <li className="flex justify-between gap-3">
                                        <span className="text-muted-foreground">Part-time shift</span>
                                        <span className="font-medium text-[var(--success)]">+ Rs. 3,200</span>
                                    </li>
                                    <li className="flex justify-between gap-3">
                                        <span className="text-muted-foreground">Metro pass</span>
                                        <span className="font-medium">− Rs. 1,100</span>
                                    </li>
                                </ul>
                            </div>

                            <div className="cc-home-dash__tile cc-home-dash__tile--cats" data-active={active === 'budget'}>
                                <p className="cc-home-dash__label">Category spend</p>
                                <div className="mt-3 space-y-2">
                                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                                        <div className="bg-primary h-full w-[70%]" />
                                    </div>
                                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                                        <div className="h-full w-[45%] bg-[var(--chart-2)]" />
                                    </div>
                                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                                        <div className="h-full w-[32%] bg-[var(--chart-3)]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p className="cc-home-dash__badge">Marketing mockup — sample figures, not live account data.</p>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

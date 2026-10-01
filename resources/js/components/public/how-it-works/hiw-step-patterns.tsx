import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { useState } from 'react';

const bars = [48, 56, 52, 64, 61, 72, 68, 80];
const flow = ['Transactions', 'Dashboard', 'Forecast', 'Reports', 'Insight'] as const;

export default function HiwStepPatterns() {
    const [whyOpen, setWhyOpen] = useState(true);

    return (
        <PublicSection id="step-patterns" className="cc-hiw-patterns !py-16 sm:!py-20" aria-labelledby="hiw-step-04">
            <PublicContainer>
                <div className="max-w-2xl">
                    <p className="cc-hiw-kicker">04 — See the picture</p>
                    <h2 id="hiw-step-04" className="cc-hiw-title">
                        Money Pulse, forecasts, and reports
                    </h2>
                    <p className="cc-hiw-lede">
                        Your dashboard unlocks a health score, month-end pace, charts, and readable insights so you understand the month before it ends.
                    </p>
                </div>

                <div className="cc-hiw-mini-flow" aria-hidden="true">
                    {flow.map((item, index) => (
                        <span key={item}>
                            {item}
                            {index < flow.length - 1 ? <i aria-hidden> → </i> : null}
                        </span>
                    ))}
                </div>

                <div className="cc-hiw-patterns-grid">
                    <div className="cc-hiw-dark-panel" aria-label="Example reports preview">
                        <p className="cc-hiw-dark-panel__label">Reports · Example</p>
                        <p className="mt-2 text-sm font-semibold">Monthly spending · Income vs expense</p>
                        <div className="cc-hiw-chart" aria-hidden="true">
                            {bars.map((height, index) => (
                                <span key={index} style={{ height: `${height}%`, animationDelay: `${index * 40}ms` }} />
                            ))}
                        </div>
                        <p className="mt-3 text-sm">
                            Income <strong>Rs. 60,000</strong> · Expense <strong>Rs. 42,500</strong>
                        </p>
                        <p className="mt-1 text-sm opacity-80">Category breakdown + multi-month trend</p>
                    </div>

                    <div className="cc-hiw-dark-panel" aria-label="Example AI insight">
                        <p className="cc-hiw-dark-panel__label">Campus Coin AI · Example</p>
                        <div className="cc-hiw-ai-stack">
                            <div className="cc-hiw-ai-card">
                                <p className="cc-hiw-dark-panel__label">Insight</p>
                                <p>You spent 18% more on food than your recent average.</p>
                            </div>
                            <div className="cc-hiw-ai-card">
                                <button
                                    type="button"
                                    className="cc-hiw-dark-panel__label rounded-sm focus-visible:ring-2 focus-visible:ring-[#e94f09] focus-visible:outline-none"
                                    aria-expanded={whyOpen}
                                    onClick={() => setWhyOpen((open) => !open)}
                                >
                                    Why?
                                </button>
                                {whyOpen && <p>Most of the increase came from higher food spending later in the month.</p>}
                            </div>
                            <div className="cc-hiw-ai-card">
                                <p className="cc-hiw-dark-panel__label">Suggested action</p>
                                <p>Consider tightening your monthly food budget.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <p className="cc-hiw-disclaimer">Illustrative example only — advisory, not financial certainty or automated decisions.</p>
            </PublicContainer>
        </PublicSection>
    );
}

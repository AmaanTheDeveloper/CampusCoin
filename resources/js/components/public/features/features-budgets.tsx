import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const budgets = [
    { name: 'Food', spent: 'Rs. 11,400', limit: 'Rs. 15,000', pct: 76, warn: false },
    { name: 'Transport', spent: 'Rs. 4,200', limit: 'Rs. 8,000', pct: 52, warn: false },
    { name: 'Academics', spent: 'Rs. 6,800', limit: 'Rs. 7,000', pct: 97, warn: true },
] as const;

export default function FeaturesBudgets() {
    return (
        <PublicSection id="budgets" aria-labelledby="feat-budgets-heading">
            <PublicContainer>
                <div className="cc-feat-split">
                    <div>
                        <p className="cc-feat-kicker">Budget management</p>
                        <h2 id="feat-budgets-heading" className="cc-feat-title">
                            Set a limit.
                            <br className="hidden sm:block" /> Know where you stand.
                        </h2>
                        <p className="cc-feat-lede">
                            Students can monitor category budgets and receive appropriate alerts as spending approaches or exceeds limits — so course
                            corrections happen earlier.
                        </p>
                    </div>

                    <div className="cc-feat-panel" aria-label="Example budget progress">
                        <div className="cc-feat-panel__body">
                            <p className="cc-feat-kicker !mb-4">Category budgets · Example</p>
                            <div className="cc-feat-budgets">
                                {budgets.map((budget) => (
                                    <div key={budget.name} className={`cc-feat-budget ${budget.warn ? 'cc-feat-budget--warn' : ''}`}>
                                        <div className="cc-feat-budget__top">
                                            <span>{budget.name}</span>
                                            <span>{budget.pct}%</span>
                                        </div>
                                        <div className="cc-feat-budget__bar" aria-hidden="true">
                                            <span style={{ width: `${budget.pct}%` }} />
                                        </div>
                                        <p className="cc-feat-budget__meta">
                                            {budget.spent} / {budget.limit}
                                            {budget.warn ? ' · Near limit' : ' · On track'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <p className="cc-feat-note">Illustrative example — not live budget data.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

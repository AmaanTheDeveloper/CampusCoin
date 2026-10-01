import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';

const bars = [42, 58, 51, 67, 61, 74, 69, 82, 77, 71, 88, 80];
const categories = [
    { name: 'Food', amount: 'Rs. 14,200', trend: '+8%', color: 'var(--primary)' },
    { name: 'Transport', amount: 'Rs. 6,100', trend: '−3%', color: 'var(--chart-2)' },
    { name: 'Academics', amount: 'Rs. 4,800', trend: '+1%', color: 'var(--chart-3)' },
    { name: 'Other', amount: 'Rs. 3,400', trend: 'flat', color: 'var(--chart-4)' },
];

export default function FeaturesReports() {
    return (
        <PublicSection id="reports" variant="muted" aria-labelledby="feat-reports-heading">
            <PublicContainer>
                <div className="cc-feat-split cc-feat-split--flip">
                    <div>
                        <p className="cc-feat-kicker">Reports & analytics</p>
                        <h2 id="feat-reports-heading" className="cc-feat-title">
                            Turn transactions
                            <br className="hidden sm:block" /> into a clearer picture.
                        </h2>
                        <p className="cc-feat-lede">
                            Monthly spending, income versus expense, category breakdowns, and multi-month trends — presented so students can read the
                            story quickly.
                        </p>
                    </div>

                    <div className="cc-feat-panel" aria-label="Example reports preview">
                        <div className="cc-feat-panel__body">
                            <p className="cc-feat-kicker !mb-3">Spending overview · Example</p>
                            <div className="cc-feat-report-grid">
                                <div>
                                    <p className="cc-public-caption mb-2">Six-month trend</p>
                                    <div className="cc-feat-chart-bars" aria-hidden="true">
                                        {bars.map((height, index) => (
                                            <span key={index} style={{ height: `${height}%`, animationDelay: `${index * 40}ms` }} />
                                        ))}
                                    </div>
                                    <div className="text-muted-foreground mt-2 flex justify-between text-[0.65rem]">
                                        <span>Apr</span>
                                        <span>Sep</span>
                                    </div>
                                </div>
                                <div>
                                    <p className="cc-public-caption mb-2">Income vs expense</p>
                                    <p className="text-sm font-semibold">Income Rs. 60,000</p>
                                    <p className="text-sm font-semibold">Expense Rs. 42,500</p>
                                    <p className="text-primary mt-2 text-sm font-bold">Net + Rs. 17,500</p>
                                    <ul className="cc-feat-donut-legend mt-4">
                                        {categories.map((cat) => (
                                            <li key={cat.name}>
                                                <span>
                                                    <span className="cc-feat-dot" style={{ background: cat.color }} aria-hidden />
                                                    {cat.name}
                                                </span>
                                                <span className="font-medium">
                                                    {cat.amount} · {cat.trend}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                            <p className="cc-feat-note">Illustrative showcase — not connected to live reports.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

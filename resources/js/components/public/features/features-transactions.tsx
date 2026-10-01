import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const transactions = [
    { cat: 'Scholarship', date: '28 Sep 2026', amount: '+ Rs. 25,000', kind: 'in' as const },
    { cat: 'Food', date: '27 Sep 2026', amount: '− Rs. 850', kind: 'out' as const },
    { cat: 'Transport', date: '26 Sep 2026', amount: '− Rs. 420', kind: 'out' as const },
];

export default function FeaturesTransactions() {
    return (
        <PublicSection id="tracking" aria-labelledby="feat-tracking-heading">
            <PublicContainer>
                <div className="cc-feat-split">
                    <div>
                        <p className="cc-feat-kicker">Smart transactions</p>
                        <h2 id="feat-tracking-heading" className="cc-feat-title">
                            Track every rupee.
                            <br className="hidden sm:block" /> Without the friction.
                        </h2>
                        <p className="cc-feat-lede">
                            Campus Coin lets students record income and expenses in one place — from scholarships and allowances to everyday campus
                            spending.
                        </p>
                        <Link href={route('how-it-works')} className="cc-feat-link">
                            See how it works
                            <ArrowRight className="size-3.5" aria-hidden />
                        </Link>
                    </div>

                    <div className="cc-feat-panel" aria-label="Example transactions preview">
                        <div className="cc-feat-panel__body">
                            <p className="cc-feat-kicker !mb-3">Recent activity · Example</p>
                            {transactions.map((txn) => (
                                <div key={`${txn.cat}-${txn.date}`} className="cc-feat-txn">
                                    <div className="cc-feat-txn__meta">
                                        <p className="cc-feat-txn__cat">{txn.cat}</p>
                                        <p className="cc-feat-txn__date">{txn.date}</p>
                                    </div>
                                    <p className={`cc-feat-txn__amt ${txn.kind === 'in' ? 'cc-feat-txn__amt--in' : 'cc-feat-txn__amt--out'}`}>
                                        {txn.amount}
                                    </p>
                                </div>
                            ))}
                            <p className="cc-feat-note">Illustrative example — sample figures only.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

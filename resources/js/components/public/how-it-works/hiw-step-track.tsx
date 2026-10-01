import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { FileSpreadsheet, PenLine, ScanLine } from 'lucide-react';

const expenses = [
    { cat: 'Food', date: '27 Sep 2026', amount: '− Rs. 850' },
    { cat: 'Transport', date: '26 Sep 2026', amount: '− Rs. 420' },
    { cat: 'Academics', date: '24 Sep 2026', amount: '− Rs. 1,200' },
    { cat: 'Part-time shift', date: '22 Sep 2026', amount: '+ Rs. 3,200' },
] as const;

const captureMethods = [
    { label: 'Manual entry', icon: PenLine },
    { label: 'Smart Scan', icon: ScanLine },
    { label: 'CSV import', icon: FileSpreadsheet },
] as const;

export default function HiwStepTrack() {
    return (
        <PublicSection id="step-track" variant="muted" aria-labelledby="hiw-step-02">
            <PublicContainer>
                <div className="cc-hiw-split cc-hiw-split--flip">
                    <div>
                        <p className="cc-hiw-kicker">02 — Add your money</p>
                        <h2 id="hiw-step-02" className="cc-hiw-title">
                            Log income and expenses your way
                        </h2>
                        <p className="cc-hiw-lede">
                            Enter transactions manually, scan a receipt or statement with Smart Scan, or import a CSV — no bank connection required.
                        </p>
                        <ul className="mt-5 flex flex-wrap gap-2" aria-label="Capture methods">
                            {captureMethods.map(({ label, icon: Icon }) => (
                                <li
                                    key={label}
                                    className="inline-flex items-center gap-2 rounded-[var(--radius)] border border-border bg-card px-3 py-2 text-sm font-medium"
                                >
                                    <Icon className="text-primary size-4" aria-hidden />
                                    {label}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="cc-hiw-panel" aria-label="Example expenses">
                        <div className="cc-hiw-panel__body">
                            <p className="cc-hiw-kicker !mb-3">Activity · Example</p>
                            <div className="cc-hiw-txns">
                                {expenses.map((txn) => (
                                    <div key={`${txn.cat}-${txn.date}`} className="cc-hiw-txn">
                                        <div>
                                            <p className="cc-hiw-txn__cat">{txn.cat}</p>
                                            <p className="cc-hiw-txn__date">{txn.date}</p>
                                        </div>
                                        <p className="cc-hiw-txn__amt">{txn.amount}</p>
                                    </div>
                                ))}
                            </div>
                            <p className="cc-hiw-note">Illustrative example — not live transactions.</p>
                        </div>
                    </div>
                </div>
            </PublicContainer>
        </PublicSection>
    );
}

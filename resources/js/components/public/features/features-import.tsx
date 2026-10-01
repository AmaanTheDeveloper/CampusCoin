import PublicContainer from '@/components/public/public-container';
import PublicSection from '@/components/public/public-section';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const steps = ['Upload', 'Map', 'Preview', 'Categorize', 'Import'] as const;

const rows = [
    { date: '12 Sep', desc: 'Bookstore', amount: '−1,250', category: 'Academics' },
    { date: '14 Sep', desc: 'Metro pass', amount: '−1,100', category: 'Transport' },
    { date: '15 Sep', desc: 'Part-time', amount: '+3,200', category: 'Income' },
] as const;

export default function FeaturesImport() {
    const { auth } = usePage<SharedData>().props;
    const href = auth.user ? route('dashboard') : route('register');

    return (
        <PublicSection id="import" variant="muted" aria-labelledby="feat-import-heading">
            <PublicContainer>
                <p className="cc-feat-kicker">CSV import</p>
                <h2 id="feat-import-heading" className="cc-feat-title">
                    Bring your history
                    <br className="hidden sm:block" /> with you.
                </h2>
                <p className="cc-feat-lede">
                    Upload supported history, map columns, preview rows, categorize, then import — a clear path into Campus Coin without starting from
                    zero.
                </p>

                <ol className="cc-feat-flow mt-8">
                    {steps.map((step, index) => (
                        <li key={step} className="cc-feat-flow__step">
                            <span className="cc-feat-flow__num">0{index + 1}</span>
                            <span className="cc-feat-flow__label">{step}</span>
                        </li>
                    ))}
                </ol>

                <div className="cc-feat-panel mt-6" aria-label="Example CSV preview">
                    <div className="cc-feat-panel__body overflow-x-auto">
                        <p className="cc-feat-kicker !mb-3">Preview rows · Example</p>
                        <table className="cc-feat-csv">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={`${row.date}-${row.desc}`}>
                                        <td>{row.date}</td>
                                        <td>{row.desc}</td>
                                        <td>{row.amount}</td>
                                        <td>{row.category}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="cc-feat-note">Static showcase — this page does not upload or import files.</p>
                    </div>
                </div>

                <Link href={href} className="cc-feat-link">
                    Create an account to import
                    <ArrowRight className="size-3.5" aria-hidden />
                </Link>
            </PublicContainer>
        </PublicSection>
    );
}

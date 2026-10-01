import CampusCoinBrand from '@/components/campus-coin-brand';
import PublicContainer from '@/components/public/public-container';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const productLinks = [
    { label: 'Features', routeName: 'features' },
    { label: 'How It Works', routeName: 'how-it-works' },
    { label: 'Intelligence', routeName: 'intelligence.preview' },
    { label: 'Student Loans', routeName: 'student-loans' },
    { label: 'Home', routeName: 'home' },
] as const;

const companyLinks = [
    { label: 'About', routeName: 'about' },
    { label: 'Contact', routeName: 'contact' },
    { label: 'Register Your Bank', routeName: 'bank.register' },
] as const;

export default function PublicFooter() {
    const { auth } = usePage<SharedData>().props;
    const isAuthed = Boolean(auth?.user);

    return (
        <footer className="cc-public-footer border-border border-t">
            <PublicContainer className="grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-3 sm:col-span-2 lg:col-span-1">
                    <CampusCoinBrand showTagline />
                    <p className="cc-public-caption max-w-sm">
                        Student budgeting that matches the real Campus Coin app — track spending, set monthly budgets, and stay ahead of campus costs.
                    </p>
                </div>

                <div>
                    <h2 className="cc-public-footer__heading">Product</h2>
                    <ul className="space-y-2">
                        {productLinks.map((item) => (
                            <li key={item.routeName}>
                                <Link href={route(item.routeName)} className="cc-public-footer__link">
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                        {isAuthed ? (
                            <li>
                                <Link href={route('dashboard')} className="cc-public-footer__link">
                                    Dashboard
                                </Link>
                            </li>
                        ) : null}
                    </ul>
                </div>

                <div>
                    <h2 className="cc-public-footer__heading">Account</h2>
                    <ul className="space-y-2">
                        {isAuthed ? (
                            <li>
                                <Link href={route('dashboard')} className="cc-public-footer__link">
                                    Open Dashboard
                                </Link>
                            </li>
                        ) : (
                            <>
                                <li>
                                    <Link href={route('login')} className="cc-public-footer__link">
                                        Log In
                                    </Link>
                                </li>
                                <li>
                                    <Link href={route('register')} className="cc-public-footer__link">
                                        Get Started
                                    </Link>
                                </li>
                            </>
                        )}
                    </ul>
                </div>

                <div>
                    <h2 className="cc-public-footer__heading">Company</h2>
                    <ul className="space-y-2">
                        {companyLinks.map((item) => (
                            <li key={item.routeName}>
                                <Link href={route(item.routeName)} className="cc-public-footer__link">
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </PublicContainer>

            <div className="border-border border-t">
                <PublicContainer className="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="cc-public-caption">© 2026 Campus Coin. All rights reserved.</p>
                    <p className="cc-public-caption">No bank connection required.</p>
                </PublicContainer>
            </div>
        </footer>
    );
}

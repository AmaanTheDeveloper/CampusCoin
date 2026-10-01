import CampusCoinBrand from '@/components/campus-coin-brand';
import PublicContainer from '@/components/public/public-container';
import PublicThemeToggle from '@/components/public/public-theme-toggle';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useId, useState } from 'react';

const navLinks = [
    { label: 'Home', href: '/', routeName: 'home' },
    { label: 'Features', href: '/features', routeName: 'features' },
    { label: 'How It Works', href: '/how-it-works', routeName: 'how-it-works' },
    { label: 'Student Loans', href: '/student-loans', routeName: 'student-loans' },
    { label: 'About', href: '/about', routeName: 'about' },
    { label: 'Contact', href: '/contact', routeName: 'contact' },
] as const;

function isActiveRoute(currentUrl: string, href: string): boolean {
    if (href === '/') {
        return currentUrl === '/' || currentUrl === '';
    }

    return currentUrl === href || currentUrl.startsWith(`${href}/`);
}

export default function PublicNavbar() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const menuId = useId();
    const currentUrl = page.url.split('?')[0] || '/';
    const isAuthed = Boolean(auth?.user);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        if (!mobileOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [mobileOpen]);

    return (
        <header className={cn('cc-public-nav', scrolled && 'cc-public-nav--scrolled')}>
            <PublicContainer className="flex h-14 items-center justify-between gap-4 sm:h-16">
                <Link
                    href={route('home')}
                    className="focus-visible:ring-ring shrink-0 rounded-[calc(var(--radius)-4px)] focus-visible:ring-2 focus-visible:outline-none"
                >
                    <CampusCoinBrand />
                </Link>

                <nav className="hidden items-center gap-1 lg:flex" aria-label="Primary">
                    {navLinks.map((item) => (
                        <Link
                            key={item.routeName}
                            href={route(item.routeName)}
                            className={cn(
                                'cc-public-nav-link px-3 py-2',
                                isActiveRoute(currentUrl, item.href) && 'cc-public-nav-link--active',
                            )}
                            aria-current={isActiveRoute(currentUrl, item.href) ? 'page' : undefined}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                <div className="flex items-center gap-1 sm:gap-2">
                    <PublicThemeToggle className="cc-public-theme-toggle shrink-0" />

                    <div className="hidden items-center gap-2 lg:flex">
                        {isAuthed ? (
                            <Button asChild size="sm" className="cc-dash-btn-primary">
                                <Link href={route('dashboard')}>Open Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground">
                                    <Link href={route('login')}>Log In</Link>
                                </Button>
                                <Button asChild size="sm" className="cc-dash-btn-primary">
                                    <Link href={route('register')}>Get Started</Link>
                                </Button>
                            </>
                        )}
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="min-h-11 min-w-11 lg:hidden"
                        aria-expanded={mobileOpen}
                        aria-controls={menuId}
                        aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
                        onClick={() => setMobileOpen((open) => !open)}
                    >
                        {mobileOpen ? <X className="size-5" aria-hidden /> : <Menu className="size-5" aria-hidden />}
                    </Button>
                </div>
            </PublicContainer>

            {mobileOpen ? (
                <div className="border-border bg-background fixed inset-0 z-50 flex flex-col border-t lg:hidden" id={menuId} role="dialog" aria-modal="true" aria-label="Navigation menu">
                    <div className="border-border flex items-center justify-between border-b px-4 py-3">
                        <CampusCoinBrand />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="min-h-11 min-w-11"
                            aria-label="Close menu"
                            onClick={() => setMobileOpen(false)}
                        >
                            <X className="size-5" aria-hidden />
                        </Button>
                    </div>
                    <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-4" aria-label="Mobile">
                        {navLinks.map((item) => (
                            <Link
                                key={item.routeName}
                                href={route(item.routeName)}
                                className={cn(
                                    'cc-public-nav-link min-h-11 rounded-[var(--radius)] px-3 py-2.5',
                                    isActiveRoute(currentUrl, item.href) && 'cc-public-nav-link--active bg-[var(--cc-orange-soft)]',
                                )}
                                aria-current={isActiveRoute(currentUrl, item.href) ? 'page' : undefined}
                                onClick={() => setMobileOpen(false)}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="border-border mt-auto flex flex-col gap-2 border-t px-6 py-5">
                        {isAuthed ? (
                            <Button asChild className="cc-dash-btn-primary min-h-11 w-full">
                                <Link href={route('dashboard')} onClick={() => setMobileOpen(false)}>
                                    Open Dashboard
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="outline" className="cc-dash-btn-secondary min-h-11 w-full">
                                    <Link href={route('login')} onClick={() => setMobileOpen(false)}>
                                        Log In
                                    </Link>
                                </Button>
                                <Button asChild className="cc-dash-btn-primary min-h-11 w-full">
                                    <Link href={route('register')} onClick={() => setMobileOpen(false)}>
                                        Get Started
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            ) : null}
        </header>
    );
}

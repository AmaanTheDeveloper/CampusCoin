import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            <div
                className="pointer-events-none absolute inset-0 opacity-90"
                aria-hidden
                style={{
                    background:
                        'radial-gradient(ellipse 80% 50% at 50% -10%, rgba(209,69,8,0.18), transparent 55%), radial-gradient(ellipse 60% 40% at 100% 100%, rgba(209,69,8,0.08), transparent 50%)',
                }}
            />
            <main className="relative z-10 w-full max-w-sm">
                <div className="cc-card flex flex-col gap-8 p-6 shadow-[0_14px_32px_rgba(0,0,0,0.12)] md:p-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <div className="mb-1 flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--cc-orange-soft)]">
                                <AppLogoIcon className="text-primary size-8" />
                            </div>
                            <span className="text-sm font-semibold tracking-tight">Campus Coin</span>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-foreground text-xl font-semibold tracking-tight">{title}</h1>
                            <p className="cc-text-secondary text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}

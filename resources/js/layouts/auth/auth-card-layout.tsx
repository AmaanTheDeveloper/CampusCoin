import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}) {
    return (
        <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
            <div
                className="pointer-events-none absolute inset-0"
                aria-hidden
                style={{
                    background:
                        'radial-gradient(ellipse 70% 45% at 50% -5%, rgba(233,79,9,0.16), transparent 55%)',
                }}
            />
            <div className="relative z-10 flex w-full max-w-md flex-col gap-6">
                <Link href={route('home')} className="flex items-center gap-2 self-center font-medium">
                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[var(--cc-orange-soft)]">
                        <AppLogoIcon className="text-primary size-8" />
                    </div>
                    <span className="text-sm font-semibold tracking-tight">Campus Coin</span>
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="cc-card border-border/60 rounded-[var(--radius)] bg-[var(--cc-surface-elevated)] shadow-[0_14px_32px_rgba(0,0,0,0.12)]">
                        <CardHeader className="px-8 pt-8 pb-0 text-center md:px-10">
                            <CardTitle className="text-xl font-semibold tracking-tight">{title}</CardTitle>
                            <CardDescription className="cc-text-secondary">{description}</CardDescription>
                        </CardHeader>
                        <CardContent className="px-8 py-8 md:px-10">{children}</CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}

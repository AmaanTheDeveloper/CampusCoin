import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name } = usePage<SharedData>().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div className="relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r">
                <div
                    className="absolute inset-0"
                    style={{
                        background:
                            'linear-gradient(165deg, #0a0a0a 0%, #121212 45%, #1a0f0a 100%), radial-gradient(ellipse at 30% 20%, rgba(233,79,9,0.35), transparent 55%)',
                    }}
                />
                <Link href={route('home')} className="relative z-20 flex items-center text-lg font-semibold tracking-tight">
                    <AppLogoIcon className="mr-2 size-8 text-[#e94f09]" />
                    {name || 'Campus Coin'}
                </Link>
                <div className="relative z-20 mt-auto">
                    <p className="cc-label mb-3 text-[#e94f09]">Student finance</p>
                    <p className="text-lg font-semibold tracking-tight">Smart Spending Student Style</p>
                    <p className="mt-2 max-w-sm text-sm text-white/70">Student-first budgeting for campus life.</p>
                </div>
            </div>
            <div className="bg-background w-full lg:p-8">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link href={route('home')} className="relative z-20 flex items-center justify-center lg:hidden">
                        <AppLogoIcon className="text-primary h-10 sm:h-12" />
                    </Link>
                    <div className="flex flex-col items-start gap-2 text-left sm:items-center sm:text-center">
                        <h1 className="text-foreground text-xl font-semibold tracking-tight">{title}</h1>
                        <p className="cc-text-secondary text-sm text-balance">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}

import { FintechPage, FintechPageHeader } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Password',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Appearance',
        url: '/settings/appearance',
        icon: null,
    },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <FintechPage>
            <FintechPageHeader eyebrow="Account" title="Settings" description="Manage your profile and account settings" />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-10">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="cc-elev-2 flex flex-col gap-0.5 p-1.5" aria-label="Settings">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-start',
                                    currentPath === item.url && 'bg-[var(--cc-orange-soft)] text-foreground hover:bg-[var(--cc-orange-soft)]',
                                )}
                            >
                                <Link href={item.url} prefetch>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-2 md:hidden" />

                <div className="min-w-0 flex-1 md:max-w-2xl">
                    <section className="cc-form-shell max-w-xl space-y-8 p-5 md:p-6">{children}</section>
                </div>
            </div>
        </FintechPage>
    );
}

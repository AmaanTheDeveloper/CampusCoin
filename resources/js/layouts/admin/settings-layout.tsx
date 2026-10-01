import { FintechPageHeader } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Appearance',
        url: '/admin/settings/appearance',
        icon: null,
    },
    {
        title: 'Profile',
        url: '/admin/settings/profile',
        icon: null,
    },
    {
        title: 'Password',
        url: '/admin/settings/password',
        icon: null,
    },
];

export default function AdminSettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <FintechPageHeader eyebrow="System" title="Settings" description="Manage administrator appearance and account settings." />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-10">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="cc-elev-2 flex flex-col gap-0.5 p-1.5" aria-label="Admin settings">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'min-h-11 w-full justify-start',
                                    currentPath === item.url && 'bg-[#E94F09]/15 text-white hover:bg-[#E94F09]/20',
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
        </div>
    );
}

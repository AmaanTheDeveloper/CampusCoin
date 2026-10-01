import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';

const modes = [
    { value: 'light' as const, label: 'Light', Icon: Sun },
    { value: 'dark' as const, label: 'Dark', Icon: Moon },
    { value: 'system' as const, label: 'System', Icon: Monitor },
];

export default function PublicThemeToggle({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();
    const current = modes.find((mode) => mode.value === appearance) ?? modes[2];
    const CurrentIcon = current.Icon;

    const cycle = () => {
        const index = modes.findIndex((mode) => mode.value === appearance);
        const next = modes[(index + 1) % modes.length] ?? modes[0];
        updateAppearance(next.value);
    };

    return (
        <div className={cn('inline-flex', className)} {...props}>
            <button
                type="button"
                onClick={cycle}
                className="text-muted-foreground hover:text-foreground inline-flex size-10 items-center justify-center rounded-md border border-transparent transition-colors hover:bg-black/5 sm:size-11 dark:hover:bg-white/5"
                aria-label={`Appearance: ${current.label}. Switch theme`}
            >
                <CurrentIcon className="size-5" aria-hidden />
            </button>
        </div>
    );
}

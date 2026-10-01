import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/**
 * Laravel pagination labels may contain HTML entities (&laquo;, &raquo;).
 * Render plain text only — never inject HTML from link labels.
 */
export function paginationLabel(label: string): string {
    const normalized = label
        .replace(/&laquo;/gi, '')
        .replace(/&raquo;/gi, '')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&nbsp;/gi, ' ')
        .replace(/<[^>]*>/g, '')
        .trim();

    if (/previous/i.test(normalized) || normalized === '«') {
        return 'Previous';
    }

    if (/next/i.test(normalized) || normalized === '»') {
        return 'Next';
    }

    return normalized || label;
}

export function PaginationNav({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
            {links.map((link, index) => {
                const label = paginationLabel(link.label);

                if (!link.url) {
                    return (
                        <span key={`${label}-${index}`} className="text-muted-foreground px-3 py-1 text-sm">
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={`${label}-${index}`}
                        href={link.url}
                        preserveState
                        className={cn(
                            'rounded-md border px-3 py-1 text-sm transition-colors',
                            link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                        )}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}

import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Display-only money formatter. Database DECIMAL values remain authoritative.
 * Currency symbol is presentation-only and not stored with amounts.
 */
export function formatMoney(amount: string | number, options?: { signed?: boolean; type?: 'income' | 'expense' }): string {
    const numeric = typeof amount === 'number' ? amount : Number(amount);

    if (!Number.isFinite(numeric)) {
        return '—';
    }

    const absolute = Math.abs(numeric).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const formatted = `Rs. ${absolute}`;

    if (!options?.signed) {
        return formatted;
    }

    if (options.type === 'expense' || numeric < 0) {
        return `− ${formatted}`;
    }

    return `+ ${formatted}`;
}

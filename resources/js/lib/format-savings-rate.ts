/**
 * Display-only savings-rate presentation.
 * Backend `savings_rate` (balance ÷ income × 100) remains authoritative.
 * Extreme magnitudes are withheld from the KPI rather than shown as fake precision.
 */

export const SAVINGS_RATE_DISPLAY_ABS_MAX = 500;

export type SavingsRateDisplay = {
    value: string;
    hint: string;
    meaningful: boolean;
};

export function isMeaningfulSavingsRate(rate: number | null): rate is number {
    return rate !== null && Number.isFinite(rate) && Math.abs(rate) <= SAVINGS_RATE_DISPLAY_ABS_MAX;
}

export function formatSavingsRateDisplay(rate: number | null, animatedValue?: number): SavingsRateDisplay {
    if (rate === null || !Number.isFinite(rate)) {
        return {
            value: 'N/A',
            hint: 'Unavailable when income is zero',
            meaningful: false,
        };
    }

    if (!isMeaningfulSavingsRate(rate)) {
        return {
            value: 'Not meaningful',
            hint: 'Rate not meaningful when income is very small relative to balance',
            meaningful: false,
        };
    }

    const shown = animatedValue !== undefined && Number.isFinite(animatedValue) ? animatedValue : rate;

    return {
        value: `${shown.toFixed(1)}%`,
        hint: 'Balance ÷ income × 100',
        meaningful: true,
    };
}

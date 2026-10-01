/**
 * Asserts display rules for resources/js/lib/format-savings-rate.ts
 * Keep thresholds and labels in sync with that module.
 * Run: node scripts/assert-format-savings-rate.mjs
 */
const SAVINGS_RATE_DISPLAY_ABS_MAX = 500;

function isMeaningfulSavingsRate(rate) {
    return rate !== null && Number.isFinite(rate) && Math.abs(rate) <= SAVINGS_RATE_DISPLAY_ABS_MAX;
}

function formatSavingsRateDisplay(rate, animatedValue) {
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

const cases = [
    { name: 'income = 0 → N/A', rate: null, expect: 'N/A' },
    { name: '80% normal', rate: 80, expect: '80.0%' },
    { name: '0% break-even', rate: 0, expect: '0.0%' },
    { name: 'honest negative', rate: -20, expect: '-20.0%' },
    { name: 'extreme negative withheld', rate: -5905.1, expect: 'Not meaningful' },
    { name: 'extreme positive withheld', rate: 1200, expect: 'Not meaningful' },
    { name: 'boundary 500 shown', rate: 500, expect: '500.0%' },
    { name: 'just over boundary withheld', rate: 500.1, expect: 'Not meaningful' },
];

let failed = 0;
for (const c of cases) {
    const result = formatSavingsRateDisplay(c.rate);
    const ok = result.value === c.expect;
    console.log(`[${ok ? 'PASS' : 'FAIL'}] ${c.name} → ${result.value}`);
    if (!ok) {
        console.error(`  expected ${c.expect}`);
        failed += 1;
    }
}

if (failed > 0) {
    process.exit(1);
}

console.log(`All ${cases.length} savings-rate display assertions passed.`);

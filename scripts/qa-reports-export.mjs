/**
 * One-off browser QA for /reports exports (not part of the app build).
 * Run: npx playwright install chromium && node scripts/qa-reports-export.mjs
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, '..', 'storage', 'app', 'qa-exports');
fs.mkdirSync(outDir, { recursive: true });

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.QA_EMAIL || 'test@example.com';
const PASSWORD = process.env.QA_PASSWORD || 'password';

const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"], input[name="email"]', EMAIL);
    await page.fill('input[type="password"], input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }),
        page.click('button[type="submit"]'),
    ]);
}

async function ensureReportHasData(page) {
    await page.goto(`${BASE}/reports`, { waitUntil: 'networkidle' });
    const empty = await page.getByText('No transactions found for this period.').isVisible().catch(() => false);
    if (!empty) {
        return true;
    }

    // Seed via create form if empty — minimal income + expense for current month.
    await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
    const typeSelect = page.locator('#type, select[name="type"]').first();
    if (await typeSelect.count()) {
        await typeSelect.selectOption('income');
    }
    await page.locator('input[name="amount"], #amount').first().fill('5000');
    const dateInput = page.locator('input[name="transaction_date"], #transaction_date, input[type="date"]').first();
    if (await dateInput.count()) {
        const today = new Date();
        const iso = today.toISOString().slice(0, 10);
        await dateInput.fill(iso);
    }
    const desc = page.locator('input[name="description"], #description, textarea[name="description"]').first();
    if (await desc.count()) {
        await desc.fill('QA income seed');
    }
    await page.click('button[type="submit"]');
    await page.waitForTimeout(800);

    await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
    if (await typeSelect.count()) {
        await page.locator('#type, select[name="type"]').first().selectOption('expense');
    }
    await page.locator('input[name="amount"], #amount').first().fill('250');
    if (await dateInput.count()) {
        const today = new Date();
        await page.locator('input[name="transaction_date"], #transaction_date, input[type="date"]').first().fill(today.toISOString().slice(0, 10));
    }
    if (await desc.count()) {
        await page.locator('input[name="description"], #description, textarea[name="description"]').first().fill('QA expense seed');
    }
    await page.click('button[type="submit"]');
    await page.waitForTimeout(800);

    await page.goto(`${BASE}/reports`, { waitUntil: 'networkidle' });
    return !(await page.getByText('No transactions found for this period.').isVisible().catch(() => false));
}

async function captureDownload(page, buttonLabel, expectedExt) {
    const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 60000 }),
        page.getByRole('button', { name: buttonLabel }).click(),
    ]);
    const suggested = download.suggestedFilename();
    const target = path.join(outDir, suggested || `export.${expectedExt}`);
    await download.saveAs(target);
    const stat = fs.statSync(target);
    return { target, suggested, size: stat.size };
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ acceptDownloads: true, viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const consoleErrors = [];
    page.on('pageerror', (err) => consoleErrors.push(String(err)));
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    try {
        await login(page);
        const hasData = await ensureReportHasData(page);
        record('Scenario 1 — Basic Report', hasData ? 'PASS' : 'FAIL', hasData ? 'page loaded with data' : 'no report data');

        // Export Image
        try {
            const img = await captureDownload(page, 'Export Image', 'png');
            const buf = fs.readFileSync(img.target);
            const isPng = buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47;
            record(
                'Scenario 2 — Export Image',
                isPng && img.size > 5000 ? 'PASS' : 'FAIL',
                `${img.suggested} (${img.size} bytes, png=${isPng})`,
            );
        } catch (e) {
            record('Scenario 2 — Export Image', 'FAIL', String(e));
        }

        // Export PDF
        try {
            const pdf = await captureDownload(page, 'Export PDF', 'pdf');
            const buf = fs.readFileSync(pdf.target);
            const isPdf = buf.slice(0, 4).toString() === '%PDF';
            record(
                'Scenario 3 — Export PDF',
                isPdf && pdf.size > 2000 ? 'PASS' : 'FAIL',
                `${pdf.suggested} (${pdf.size} bytes, pdf=${isPdf})`,
            );
        } catch (e) {
            record('Scenario 3 — Export PDF', 'FAIL', String(e));
        }

        // Filtered exports
        const categorySelect = page.locator('#report-category');
        if (await categorySelect.count()) {
            const options = await categorySelect.locator('option').all();
            if (options.length > 1) {
                const value = await options[1].getAttribute('value');
                await categorySelect.selectOption(value || '');
                await page.getByRole('button', { name: 'Apply Filters' }).first().click();
                await page.waitForLoadState('networkidle');
            }
        }

        try {
            const img = await captureDownload(page, 'Export Image', 'png');
            record('Scenario 4 — Filtered Image', img.size > 5000 ? 'PASS' : 'FAIL', `${img.size} bytes`);
        } catch (e) {
            record('Scenario 4 — Filtered Image', 'FAIL', String(e));
        }

        try {
            const pdf = await captureDownload(page, 'Export PDF', 'pdf');
            record('Scenario 5 — Filtered PDF', pdf.size > 2000 ? 'PASS' : 'FAIL', `${pdf.size} bytes`);
        } catch (e) {
            record('Scenario 5 — Filtered PDF', 'FAIL', String(e));
        }

        // Long report — widen date range if possible / reset and check multi-page via file size heuristic
        await page.getByRole('button', { name: 'Reset' }).first().click().catch(() => {});
        await page.waitForLoadState('networkidle');
        try {
            const pdf = await captureDownload(page, 'Export PDF', 'pdf');
            const content = fs.readFileSync(pdf.target);
            const pageCount = (content.toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length;
            record(
                'Scenario 6 — Long Report PDF',
                pdf.size > 2000 && pageCount >= 1 ? 'PASS' : 'FAIL',
                `pages≈${pageCount}, size=${pdf.size}`,
            );
        } catch (e) {
            record('Scenario 6 — Long Report PDF', 'FAIL', String(e));
        }

        // Empty filter — navigate with a past range that has no data
        await page.goto(`${BASE}/reports?from=2020-01-01&to=2020-01-31`, { waitUntil: 'networkidle' });
        const emptyVisible = await page.locator('.border-border.bg-card').getByText('No transactions found for this period.').isVisible().catch(() => false);
        const exportDisabled = await page.getByRole('button', { name: 'Export PDF' }).isDisabled();
        record(
            'Scenario 7 — Empty Filter',
            emptyVisible && exportDisabled ? 'PASS' : 'FAIL',
            `empty=${emptyVisible}, exportDisabled=${exportDisabled}`,
        );

        // Reset and multiple exports
        await page.getByRole('button', { name: 'Clear Filters' }).click().catch(async () => {
            await page.getByRole('button', { name: 'Reset' }).first().click();
        });
        await page.waitForLoadState('networkidle');

        try {
            await captureDownload(page, 'Export PDF', 'pdf');
            await captureDownload(page, 'Export PDF', 'pdf');
            await captureDownload(page, 'Export Image', 'png');
            await captureDownload(page, 'Export Image', 'png');
            record('Scenario 8 — Multiple Exports', 'PASS', '4 consecutive downloads succeeded');
        } catch (e) {
            record('Scenario 8 — Multiple Exports', 'FAIL', String(e));
        }

        // Dark mode — look for appearance toggle
        try {
            const appearance = page.getByRole('button', { name: /appearance|theme|dark/i }).first();
            if (await appearance.count()) {
                await appearance.click();
            }
            // Try common dark mode switch in settings menu
            const darkToggle = page.locator('[data-theme="dark"], button:has-text("Dark")').first();
            if (await darkToggle.count()) {
                await darkToggle.click();
            }
            await page.goto(`${BASE}/reports`, { waitUntil: 'networkidle' });
            const pdf = await captureDownload(page, 'Export PDF', 'pdf');
            record('Scenario 9 — Dark Mode', pdf.size > 2000 ? 'PASS' : 'FAIL', `export size=${pdf.size}`);
        } catch (e) {
            record('Scenario 9 — Dark Mode', 'FAIL', String(e));
        }

        // Mobile
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/reports`, { waitUntil: 'networkidle' });
        const filtersBtn = page.getByRole('button', { name: 'Filters' });
        const exportBtn = page.getByRole('button', { name: 'Export PDF' });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 2);
        record(
            'Scenario 10 — Mobile',
            (await exportBtn.isVisible()) && ((await filtersBtn.count()) === 0 || (await filtersBtn.isVisible())) ? 'PASS' : 'FAIL',
            `overflow=${overflow}`,
        );

        record(
            'Scenario 11 — Console',
            consoleErrors.length === 0 ? 'PASS' : 'FAIL',
            consoleErrors.slice(0, 5).join(' | ') || 'no runtime errors',
        );

        // Existing features visible
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${BASE}/reports`, { waitUntil: 'networkidle' });
        const features = [
            await page.getByText('Spending by Category').first().isVisible().catch(() => false),
            await page.getByText('Daily Spending').first().isVisible().catch(() => false),
            await page.getByText('Weekly Spending').first().isVisible().catch(() => false),
            await page.getByText('Income vs Expenses').first().isVisible().catch(() => false),
            await page.getByText('Report Details').first().isVisible().catch(() => false),
        ];
        record('Scenario 12 — Existing Report Features', features.every(Boolean) ? 'PASS' : 'FAIL', features.join(','));
    } catch (e) {
        record('Fatal', 'FAIL', String(e));
    } finally {
        await browser.close();
        const summaryPath = path.join(outDir, 'qa-summary.json');
        fs.writeFileSync(summaryPath, JSON.stringify(results, null, 2));
        console.log(`\nWrote ${summaryPath}`);
        const failed = results.filter((r) => r.status === 'FAIL').length;
        process.exit(failed > 0 ? 1 : 0);
    }
}

main();

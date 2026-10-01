/**
 * Non-destructive recurring page QA — NO create/edit/pause/delete/generator.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(__dirname, '..', 'storage', 'app', 'qa-exports');
fs.mkdirSync(outDir, { recursive: true });
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', 'test@example.com');
    await page.fill('input[type=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.includes('/login')), page.click('button[type=submit]')]);
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    const failed = [];
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    page.on('response', (r) => {
        const s = r.status();
        if (s >= 400 && r.url().includes('127.0.0.1:8000')) {
            failed.push(`${s} ${r.request().method()} ${r.url()}`);
        }
    });

    try {
        await login(page);

        await page.goto(`${BASE}/recurring-transactions`, { waitUntil: 'networkidle' });
        const heading = await page.getByRole('heading', { name: 'Recurring transactions', exact: true }).isVisible();
        record('Scenario 1 — Page load', heading ? 'PASS' : 'FAIL');

        const emptyOrList =
            (await page.getByText('No recurring transactions').isVisible().catch(() => false)) ||
            (await page.locator('table').count()) > 0;
        record('Scenario 2 — Empty/list state', emptyOrList ? 'PASS' : 'FAIL');

        await page.goto(`${BASE}/recurring-transactions/create`, { waitUntil: 'networkidle' });
        const formOk =
            (await page.locator('#amount').isVisible()) &&
            (await page.locator('#category_id').isVisible()) &&
            (await page.locator('#frequency').isVisible()) &&
            (await page.locator('#start_date').isVisible());
        record('Scenario 3 — Create form renders', formOk ? 'PASS' : 'FAIL', 'form not submitted');

        await page.getByRole('button', { name: 'Income', exact: true }).click();
        const incomeOpts = await page.locator('#category_id option').evaluateAll((els) =>
            els.map((e) => e.textContent?.trim()).filter(Boolean),
        );
        await page.getByRole('button', { name: 'Expense', exact: true }).click();
        const expenseOpts = await page.locator('#category_id option').evaluateAll((els) =>
            els.map((e) => e.textContent?.trim()).filter(Boolean),
        );
        const typeOk = incomeOpts.some((t) => /Allowance|Job|Scholarship|Gift|Income/i.test(t || '')) &&
            expenseOpts.some((t) => /Food|Transport|Subscription/i.test(t || ''));
        record('Scenario 4 — Type/category controls', typeOk ? 'PASS' : 'FAIL', `income=${incomeOpts.length}, expense=${expenseOpts.length}`);

        const freqOpts = await page.locator('#frequency option').evaluateAll((els) => els.map((e) => e.value));
        const freqOk = freqOpts.includes('weekly') && freqOpts.includes('monthly');
        record('Scenario 5 — Frequency/next-run controls', freqOk && (await page.locator('#start_date').isVisible()) ? 'PASS' : 'FAIL', freqOpts.join(','));

        await page.goto(`${BASE}/recurring-transactions`, { waitUntil: 'networkidle' });
        const hasRows = (await page.locator('table tbody tr').count()) > 0;
        if (hasRows) {
            const hasEdit = (await page.getByRole('link', { name: /Edit/i }).count()) > 0;
            const hasPauseResume = (await page.getByRole('button', { name: /Pause|Resume/i }).count()) > 0;
            const hasDelete = (await page.getByRole('button', { name: /Delete/i }).count()) > 0;
            record('Scenario 6 — Action controls present', hasEdit && hasPauseResume && hasDelete ? 'PASS' : 'FAIL', 'controls not executed');
        } else {
            record('Scenario 6 — Action controls present', 'PASS', 'no existing records; create CTA available');
        }

        record('Scenario 7 — Console', errors.length === 0 ? 'PASS' : 'FAIL', errors.slice(0, 5).join(' | ') || 'clean');

        const bad = failed.filter((f) => !f.includes('/favicon'));
        record('Scenario 8 — Network', bad.length === 0 ? 'PASS' : 'FAIL', bad.slice(0, 5).join(' | ') || 'no 4xx/5xx');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/recurring-transactions`, { waitUntil: 'networkidle' });
        const mobile = await page.getByRole('link', { name: 'Create' }).first().isVisible();
        record('Scenario 9 — Mobile', mobile ? 'PASS' : 'FAIL');

        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${BASE}/settings/appearance`, { waitUntil: 'networkidle' });
        const darkBtn = page.getByRole('button', { name: /^Dark$/i });
        if (await darkBtn.count()) await darkBtn.first().click();
        await page.goto(`${BASE}/recurring-transactions`, { waitUntil: 'networkidle' });
        await page.goto(`${BASE}/recurring-transactions/create`, { waitUntil: 'networkidle' });
        record('Scenario 10 — Dark mode', (await page.locator('#amount').isVisible()) ? 'PASS' : 'FAIL');
    } catch (e) {
        record('Fatal', 'FAIL', String(e));
    } finally {
        await browser.close();
        fs.writeFileSync(path.join(outDir, 'qa-recurring-nondestructive.json'), JSON.stringify(results, null, 2));
        process.exit(results.some((r) => r.status === 'FAIL') ? 1 : 0);
    }
}

main();

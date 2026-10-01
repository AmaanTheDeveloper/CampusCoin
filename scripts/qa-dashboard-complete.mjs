/**
 * Dashboard complete QA — Balance KPI = AvailableBalance, empty states, overspend, themes
 * Run: node scripts/qa-dashboard-complete.mjs
 * Uses a disposable user; cleans up afterward. Does not leave permanent financial data.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(ROOT, 'storage', 'app', 'qa-exports');
fs.mkdirSync(OUT, { recursive: true });

const QA_EMAIL = `dash-audit-qa-${Date.now()}@example.com`;
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

function php(code) {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: ROOT,
        encoding: 'utf8',
        timeout: 60000,
    });
}

function setupUser() {
    php(`
        \\App\\Models\\User::where('email', '${QA_EMAIL}')->forceDelete();
        $u = \\App\\Models\\User::factory()->create([
            'name' => 'Dash Audit QA',
            'email' => '${QA_EMAIL}',
            'email_verified_at' => now(),
        ]);
        echo $u->id;
    `);
}

function cleanupUser() {
    try {
        php(`
            $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->first();
            if ($u) {
                \\App\\Models\\Transaction::withTrashed()->where('user_id', $u->id)->forceDelete();
                $u->notifications()->delete();
                $u->forceDelete();
            }
            echo 'cleaned';
        `);
    } catch (e) {
        console.warn('cleanup', e.message);
    }
}

function wallet() {
    return php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        echo App\\Support\\AvailableBalance::forUser($u);
    `).trim().split(/\r?\n/).pop();
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('input[type=email], #email');
    await page.fill('input[type=email], #email', QA_EMAIL);
    await page.fill('input[type=password], #password', 'password');
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 }),
        page.click('button[type=submit]'),
    ]);
}

async function setAppearance(page, mode) {
    await page.evaluate((m) => {
        localStorage.setItem('appearance', m);
        document.documentElement.classList.toggle('dark', m === 'dark');
    }, mode);
}

async function kpiText(page, label) {
    return page
        .locator('.cc-summary-metric')
        .filter({ has: page.locator('.cc-label', { hasText: new RegExp(`^${label}$`, 'i') }) })
        .locator('.cc-summary-metric__value')
        .textContent();
}

async function createTxn(page, type, amount, description) {
    await page.goto(`${BASE}/transactions/create?type=${type}`, { waitUntil: 'domcontentloaded' });
    const typeBtn = page.getByRole('button', { name: type === 'income' ? 'Income' : 'Expense', exact: true });
    if (await typeBtn.count()) await typeBtn.click();
    const cat = page.locator('#category_id, select[name=category_id]').first();
    const opts = cat.locator('option');
    const n = await opts.count();
    for (let i = 0; i < n; i++) {
        const v = await opts.nth(i).getAttribute('value');
        if (v) {
            await cat.selectOption(v);
            break;
        }
    }
    await page.locator('#amount, input[name=amount]').first().fill(amount);
    const desc = page.locator('#description, input[name=description], textarea[name=description]').first();
    if (await desc.count()) await desc.fill(description);
    await Promise.all([
        page.waitForURL((u) => u.pathname.includes('/transactions'), { timeout: 20000 }),
        page.getByRole('button', { name: /Save|Create|Add/i }).first().click(),
    ]);
}

async function main() {
    const consoleErrors = [];
    let browser;
    try {
        setupUser();
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
        page.on('pageerror', (e) => consoleErrors.push(String(e)));
        page.on('console', (m) => {
            if (m.type() === 'error') consoleErrors.push(m.text());
        });

        await login(page);

        // A. Zero-data dashboard
        await setAppearance(page, 'light');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await setAppearance(page, 'light');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);
        const emptyHero = await page.getByText(/financial command center|Dashboard ready/i).first().isVisible().catch(() => false);
        const bal0 = await kpiText(page, 'Balance').catch(() => null);
        record(
            'A Zero-data dashboard',
            emptyHero || (bal0 && /Rs\.\s*0\.00/.test(bal0) && !/−/.test(bal0)) ? 'PASS' : 'FAIL',
            `hero=${emptyHero} balance=${bal0}`,
        );

        // B/C Valid income + expenses
        await createTxn(page, 'income', '20000.00', 'QA Allowance');
        await createTxn(page, 'income', '5000.00', 'QA Job');
        await createTxn(page, 'expense', '2500.00', 'QA Food');
        await createTxn(page, 'expense', '1500.00', 'QA Transport');
        await createTxn(page, 'expense', '2000.00', 'QA Academics');

        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-summary-metric', { timeout: 15000 });
        await page.waitForTimeout(1200);

        const balanceText = await kpiText(page, 'Balance');
        const incomeText = await kpiText(page, 'Income');
        const expenseText = await kpiText(page, 'Expenses');
        const walletValue = wallet();
        record(
            'B/C Fresh dataset KPIs',
            /19,000\.00|19000\.00/.test(balanceText || '') &&
                /25,000\.00|25000\.00/.test(incomeText || '') &&
                /6,000\.00|6000\.00/.test(expenseText || '') &&
                walletValue === '19000.00'
                ? 'PASS'
                : 'FAIL',
            `balance=${balanceText} income=${incomeText} expense=${expenseText} wallet=${walletValue}`,
        );

        // D Overspend create rejected
        await page.goto(`${BASE}/transactions/create?type=expense`, { waitUntil: 'domcontentloaded' });
        const cat = page.locator('#category_id, select[name=category_id]').first();
        const opts = cat.locator('option');
        for (let i = 0; i < (await opts.count()); i++) {
            const v = await opts.nth(i).getAttribute('value');
            if (v) {
                await cat.selectOption(v);
                break;
            }
        }
        await page.locator('#amount, input[name=amount]').first().fill('19000.01');
        const desc = page.locator('#description, input[name=description], textarea[name=description]').first();
        if (await desc.count()) await desc.fill('QA Overspend');
        await page.getByRole('button', { name: /Save|Create|Add/i }).first().click();
        await page.waitForTimeout(1200);
        const overspendError =
            (await page.getByText(/Insufficient Balance/i).first().isVisible().catch(() => false)) ||
            page.url().includes('/transactions/create');
        const walletAfter = wallet();
        record(
            'D Overspend create rejected',
            overspendError && walletAfter === '19000.00' ? 'PASS' : 'FAIL',
            `wallet=${walletAfter} url=${page.url()}`,
        );

        // F Dashboard after reject
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        const balAfterReject = await kpiText(page, 'Balance');
        record(
            'F Dashboard after rejected overspend',
            /19,000\.00|19000\.00/.test(balAfterReject || '') && !/−/.test(balAfterReject || '') ? 'PASS' : 'FAIL',
            `balance=${balAfterReject}`,
        );

        // G Light mode contrast spot-check
        await setAppearance(page, 'light');
        await page.reload({ waitUntil: 'domcontentloaded' });
        const lightOk = await page.evaluate(() => {
            const v = document.querySelector('.cc-summary-metric__value');
            const t = document.querySelector('.cc-app-sidebar-trigger');
            return {
                dark: document.documentElement.classList.contains('dark'),
                value: v ? getComputedStyle(v).color : null,
                trigger: t ? getComputedStyle(t).color : null,
            };
        });
        record('G Light mode readable', !lightOk.dark && lightOk.value && lightOk.trigger ? 'PASS' : 'FAIL', JSON.stringify(lightOk));

        // H Dark mode
        await setAppearance(page, 'dark');
        await page.reload({ waitUntil: 'domcontentloaded' });
        const darkOk = await page.evaluate(() => document.documentElement.classList.contains('dark'));
        record('H Dark mode', darkOk ? 'PASS' : 'FAIL');

        // I/J Mobile
        for (const [w, h, name] of [
            [390, 844, 'I Mobile 390'],
            [360, 800, 'J Mobile 360'],
        ]) {
            await setAppearance(page, 'light');
            await page.setViewportSize({ width: w, height: h });
            await page.reload({ waitUntil: 'domcontentloaded' });
            const m = await page.evaluate(() => ({
                overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
                kpi: Boolean(document.querySelector('.cc-summary-metric__value')),
            }));
            record(name, !m.overflow && m.kpi ? 'PASS' : 'FAIL', JSON.stringify(m));
        }

        // K Desktop
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        record('K Desktop 1440', (await page.locator('.cc-summary-metric').count()) >= 4 ? 'PASS' : 'FAIL');

        // L Charts / M recent / N budgets empty-or-present
        const sections = await page.evaluate(() => {
            const text = document.body.innerText;
            return {
                charts: /Spending overview|Category breakdown|Six-month/i.test(text),
                recent: /Recent transactions|No transactions yet/i.test(text),
                budgets: /Budget progress|haven.?t set a monthly budget|Set your first budget/i.test(text),
            };
        });
        record('L Charts present', sections.charts ? 'PASS' : 'FAIL');
        record('M Recent transactions', sections.recent ? 'PASS' : 'FAIL');
        record('N Budget section', sections.budgets ? 'PASS' : 'FAIL');

        // O Tips/intelligence sections (may be empty-state)
        const intel = await page.evaluate(() => /Money Pulse|Month Forecast|Spending Alert|Saving|insight|not enough|insufficient|No unusual/i.test(document.body.innerText));
        record('O Intelligence/tips sections render', intel ? 'PASS' : 'FAIL');

        // P Console
        const serious = consoleErrors.filter((e) => !/favicon|_boost|React DevTools|Download the React/i.test(e));
        record('P Console errors', serious.length === 0 ? 'PASS' : 'FAIL', serious.slice(0, 3).join(' | '));

        // Q A11y Balance label
        const a11y = await page.evaluate(() => {
            const metric = [...document.querySelectorAll('.cc-summary-metric')].find((m) => /Balance/i.test(m.textContent || ''));
            return {
                hasLabel: Boolean(metric?.querySelector('.cc-label')),
                section: Boolean(document.querySelector('#summary-heading')),
            };
        });
        record('Q Balance accessible label', a11y.hasLabel && a11y.section ? 'PASS' : 'FAIL', JSON.stringify(a11y));

        // R Refresh
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);
        const afterRefresh = await kpiText(page, 'Balance');
        record('R Refresh preserves Balance', /19,000\.00|19000\.00/.test(afterRefresh || '') ? 'PASS' : 'FAIL', afterRefresh);

        // S Ownership isolation — other user dashboard empty
        const otherEmail = `dash-audit-other-${Date.now()}@example.com`;
        php(`
            $u = \\App\\Models\\User::factory()->create([
                'name' => 'Other QA',
                'email' => '${otherEmail}',
                'email_verified_at' => now(),
            ]);
            echo $u->id;
        `);
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[type=email], #email', { timeout: 15000 });
        await page.fill('input[type=email], #email', otherEmail);
        await page.fill('input[type=password], #password', 'password');
        await Promise.all([
            page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 }),
            page.click('button[type=submit]'),
        ]);
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);
        const otherEmpty = await page.getByText(/financial command center|Dashboard ready|No transactions yet/i).first().isVisible().catch(() => false);
        const otherBal = await kpiText(page, 'Balance').catch(() => '0');
        record(
            'S Ownership isolation',
            otherEmpty || /Rs\.\s*0\.00/.test(otherBal || '') ? 'PASS' : 'FAIL',
            `empty=${otherEmpty} bal=${otherBal}`,
        );
        php(`
            $u = \\App\\Models\\User::where('email', '${otherEmail}')->first();
            if ($u) { $u->forceDelete(); }
            echo 'ok';
        `);

        fs.writeFileSync(path.join(OUT, 'dashboard-complete-qa.json'), JSON.stringify({ QA_EMAIL, results, consoleErrors }, null, 2));
    } finally {
        if (browser) await browser.close();
        cleanupUser();
    }

    const failed = results.filter((r) => r.status === 'FAIL').length;
    console.log(`\nSummary: ${results.length - failed}/${results.length} passed`);
    if (failed) process.exit(1);
}

main().catch((e) => {
    console.error(e);
    cleanupUser();
    process.exit(1);
});

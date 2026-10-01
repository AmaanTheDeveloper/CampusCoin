/**
 * Browser QA for budget overrun confirmation + non-negative remaining display.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

function php(args) {
    return execSync(`php scripts/qa-budget-overrun-setup.php ${args}`, { encoding: 'utf8' }).trim();
}

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', 'test@example.com');
    await page.fill('input[type=password]', 'password');
    await Promise.all([page.waitForURL((u) => !u.pathname.includes('/login')), page.click('button[type=submit]')]);
}

async function createExpense(page, { categoryId, amount, description }) {
    await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Expense', exact: true }).click();
    await page.selectOption('#category_id', String(categoryId));
    await page.fill('#amount', amount);
    await page.fill('#description', description);
    await page.click('button[type=submit]');
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

    try {
        const setupOut = php('setup');
        console.log(setupOut);
        const academics = setupOut.match(/academics=(\d+)/)?.[1];
        const misc = setupOut.match(/misc=(\d+)/)?.[1];
        const income = setupOut.match(/income=(\d+)/)?.[1];

        await login(page);

        // 1 under budget
        await createExpense(page, { categoryId: academics, amount: '1000.00', description: '[qa-budget-overrun] under 1000' });
        await page.waitForURL(/\/transactions$/);
        const d1 = await page.getByRole('heading', { name: /Going Over Budget|Already Exceeded/i }).isVisible().catch(() => false);
        record('Scenario 1 — Under budget no confirmation', !d1 ? 'PASS' : 'FAIL');

        php('bump9000'); // spent 9000

        // 2 confirmation
        await createExpense(page, { categoryId: academics, amount: '2000.00', description: '[qa-budget-overrun] try 2000' });
        await page.getByRole('heading', { name: /Going Over Budget|Already Exceeded/i }).waitFor({ timeout: 8000 });
        const msg = await page.locator('#budget-overrun-description').innerText();
        const msgOk = /10[,.]?000/.test(msg) && /11[,.]?000/.test(msg) && /1[,.]?000/.test(msg);
        record('Scenario 2 — Confirmation appears', msgOk ? 'PASS' : 'FAIL', msg.slice(0, 140));

        // 3 review
        await page.getByRole('button', { name: 'Review Your Budget' }).click();
        await page.waitForURL(/\/budgets/);
        const notSaved = php('count-desc "[qa-budget-overrun] try 2000"');
        record('Scenario 3 — Review navigates without save', page.url().includes('/budgets') && notSaved === '0' ? 'PASS' : 'FAIL', `saved=${notSaved}`);

        // 4 continue
        await createExpense(page, { categoryId: academics, amount: '2000.00', description: '[qa-budget-overrun] continue 2000' });
        await page.getByRole('button', { name: 'Continue Anyway' }).waitFor({ timeout: 8000 });
        await page.getByRole('button', { name: 'Continue Anyway' }).click();
        await page.waitForURL(/\/transactions$/, { timeout: 10000 });
        const saved = php('count-desc "[qa-budget-overrun] continue 2000"');
        record('Scenario 4 — Continue Anyway saves', saved === '1' ? 'PASS' : 'FAIL', `saved=${saved}`);

        // 5 budgets display
        await page.goto(`${BASE}/budgets`, { waitUntil: 'networkidle' });
        const body = await page.locator('body').innerText();
        const hasOver = /Over Budget/i.test(body);
        const hasNeg = /Remaining[\s\S]{0,30}−\s*Rs|Remaining[\s\S]{0,30}-\s*Rs/i.test(body);
        record('Scenario 5 — Non-negative overrun display', hasOver && !hasNeg ? 'PASS' : 'FAIL');

        // 6 insufficient balance
        await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Expense', exact: true }).click();
        await page.selectOption('#category_id', String(academics));
        await page.fill('#amount', '999999.00');
        await page.fill('#description', '[qa-budget-overrun] too big');
        await page.click('button[type=submit]');
        await page.getByRole('heading', { name: 'Insufficient Balance' }).waitFor({ timeout: 5000 });
        record('Scenario 6 — Insufficient balance hard block', 'PASS');

        // 7 already exceeded
        php('refill');
        await createExpense(page, { categoryId: academics, amount: '500.00', description: '[qa-budget-overrun] already over 500' });
        await page.getByRole('heading', { name: /Already Exceeded|Going Over Budget/i }).waitFor({ timeout: 8000 });
        const alreadyMsg = await page.locator('#budget-overrun-description').innerText();
        record('Scenario 7 — Already exceeded confirmation', /already/i.test(alreadyMsg) ? 'PASS' : 'FAIL', alreadyMsg.slice(0, 120));
        await page.getByRole('button', { name: 'Review Your Budget' }).click();
        await page.waitForURL(/\/budgets/);

        // 8 edit
        const transportOut = php('transport-edit');
        const editTxId = transportOut.match(/edit_tx=(\d+)/)?.[1];
        await page.goto(`${BASE}/transactions/${editTxId}/edit`, { waitUntil: 'networkidle' });
        await page.fill('#amount', '2000.00');
        await page.click('button[type=submit]');
        await page.getByRole('heading', { name: /Going Over Budget|Already Exceeded/i }).waitFor({ timeout: 8000 });
        record('Scenario 8 — Edit crosses budget', 'PASS');
        await page.getByRole('button', { name: 'Review Your Budget' }).click();
        await page.waitForURL(/\/budgets/);

        // 9 no budget
        await createExpense(page, { categoryId: misc, amount: '50.00', description: '[qa-budget-overrun] no budget misc' });
        await page.waitForURL(/\/transactions$/);
        record('Scenario 9 — No budget no popup', 'PASS');

        // 10 income
        await page.goto(`${BASE}/transactions/create`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Income', exact: true }).click();
        await page.selectOption('#category_id', String(income));
        await page.fill('#amount', '10.00');
        await page.fill('#description', '[qa-budget-overrun] income');
        await page.click('button[type=submit]');
        await page.waitForURL(/\/transactions$/);
        record('Scenario 10 — Income no confirmation', 'PASS');

        // 11 mobile
        await page.setViewportSize({ width: 390, height: 844 });
        await createExpense(page, { categoryId: academics, amount: '100.00', description: '[qa-budget-overrun] mobile' });
        await page.getByRole('button', { name: 'Continue Anyway' }).waitFor({ timeout: 8000 });
        const mobileReview = await page.getByRole('button', { name: 'Review Your Budget' }).isVisible();
        record('Scenario 11 — Mobile dialog', mobileReview ? 'PASS' : 'FAIL');
        await page.getByRole('button', { name: 'Review Your Budget' }).click();
        await page.waitForURL(/\/budgets/);

        // 12 dark
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${BASE}/settings/appearance`, { waitUntil: 'networkidle' });
        const darkBtn = page.getByRole('button', { name: /^Dark$/i });
        if (await darkBtn.count()) await darkBtn.first().click();
        await createExpense(page, { categoryId: academics, amount: '100.00', description: '[qa-budget-overrun] dark' });
        await page.getByRole('heading', { name: /Going Over Budget|Already Exceeded/i }).waitFor({ timeout: 8000 });
        record('Scenario 12 — Dark mode dialog', 'PASS');

        // 13 double click
        const before = Number(php('count-expense'));
        await page.getByRole('button', { name: 'Continue Anyway' }).dblclick();
        await page.waitForTimeout(2000);
        const after = Number(php('count-expense'));
        record('Scenario 13 — Double-click Continue', after - before <= 1 ? 'PASS' : 'FAIL', `delta=${after - before}`);
    } catch (e) {
        record('Fatal', 'FAIL', String(e));
    } finally {
        await browser.close();
        try {
            console.log(php('teardown'));
        } catch (e) {
            console.error(e);
        }
        const outDir = path.join(__dirname, '..', 'storage', 'app', 'qa-exports');
        fs.mkdirSync(outDir, { recursive: true });
        fs.writeFileSync(path.join(outDir, 'qa-budget-overrun.json'), JSON.stringify(results, null, 2));
        process.exit(results.some((r) => r.status === 'FAIL') ? 1 : 0);
    }
}

main();

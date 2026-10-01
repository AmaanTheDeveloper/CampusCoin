/**
 * Focused QA: Insufficient Balance dialog → "Review Amount" focuses #amount.
 * Run: node scripts/qa-insufficient-balance-review-amount.mjs
 * Disposable user; cleans up afterward.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const QA_EMAIL = `ibalance-review-${Date.now()}@example.com`;
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
            'name' => 'IBalance Review QA',
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
                $u->forceDelete();
            }
            echo 'cleaned';
        `);
    } catch (e) {
        console.warn('cleanup', e.message);
    }
}

function txnCount() {
    return Number(
        php(`
            $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
            echo \\App\\Models\\Transaction::withTrashed()->where('user_id', $u->id)->count();
        `)
            .trim()
            .split(/\r?\n/)
            .pop(),
    );
}

function fundUser(amount) {
    php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        $cat = \\App\\Models\\Category::query()
            ->where('is_default', true)
            ->where('type', 'income')
            ->orderBy('id')
            ->firstOrFail();
        \\App\\Models\\Transaction::factory()->for($u)->income()->create([
            'category_id' => $cat->id,
            'amount' => '${amount}',
            'transaction_date' => now()->toDateString(),
        ]);
        echo 'funded';
    `);
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

async function openExpenseForm(page) {
    await page.goto(`${BASE}/transactions/create?type=expense`, { waitUntil: 'domcontentloaded' });
    const typeBtn = page.getByRole('button', { name: 'Expense', exact: true });
    if (await typeBtn.count()) {
        await typeBtn.click();
    }
    const cat = page.locator('#category_id').first();
    const opts = cat.locator('option');
    const n = await opts.count();
    for (let i = 0; i < n; i++) {
        const v = await opts.nth(i).getAttribute('value');
        if (v) {
            await cat.selectOption(v);
            break;
        }
    }
}

async function main() {
    const consoleErrors = [];
    let browser;
    try {
        setupUser();
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
        page.on('pageerror', (err) => consoleErrors.push(String(err)));
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        await login(page);
        await openExpenseForm(page);

        // TEST 1: balance 0, expense 119.99 → Review Amount focuses amount, value kept
        await page.locator('#amount').fill('119.99');
        await page.getByRole('button', { name: /Save|Create|Add/i }).first().click();

        const dialog = page.getByRole('alertdialog');
        await dialog.waitFor({ state: 'visible', timeout: 10000 });
        const titleOk = await dialog.getByText('Insufficient Balance').isVisible();
        record('TEST 1 dialog appears', titleOk ? 'PASS' : 'FAIL');

        await dialog.getByRole('button', { name: /Review Amount/i }).click();
        await dialog.waitFor({ state: 'hidden', timeout: 10000 });

        const amount = page.locator('#amount');
        await page.waitForTimeout(100);
        const focused = await amount.evaluate((el) => document.activeElement === el);
        const value = await amount.inputValue();
        const stillOnCreate = page.url().includes('/transactions/create');
        const notBudgets = !page.url().includes('/budgets');
        record(
            'TEST 1 Review Amount focuses amount',
            focused && value === '119.99' && stillOnCreate && notBudgets ? 'PASS' : 'FAIL',
            `focused=${focused} value=${value} url=${page.url()}`,
        );

        // TEST 3: no transaction created by review
        const countAfterReview = txnCount();
        record('TEST 3 no transaction after Review Amount', countAfterReview === 0 ? 'PASS' : 'FAIL', `count=${countAfterReview}`);

        // TEST 2: fund wallet, overspend, review, reduce, submit succeeds
        fundUser('100.00');
        await openExpenseForm(page);
        await page.locator('#amount').fill('150.00');
        await page.locator('#description').fill('overspend then fix');
        await page.getByRole('button', { name: /Save|Create|Add/i }).first().click();
        await page.getByRole('alertdialog').waitFor({ state: 'visible', timeout: 10000 });
        await page.getByRole('button', { name: /Review Amount/i }).click();
        await page.getByRole('alertdialog').waitFor({ state: 'hidden', timeout: 10000 });
        await page.locator('#amount').fill('40.00');
        await Promise.all([
            page.waitForURL((u) => u.pathname.includes('/transactions') && !u.pathname.includes('/create'), { timeout: 20000 }),
            page.getByRole('button', { name: /Save|Create|Add/i }).first().click(),
        ]);
        const countAfterSave = txnCount();
        // income 100 + expense 40 = 2
        record('TEST 2 valid submit after Review Amount', countAfterSave === 2 ? 'PASS' : 'FAIL', `count=${countAfterSave}`);

        // TEST 5: dialog still blocks overspend (client-side) — open form again with remaining 60, try 70
        await openExpenseForm(page);
        await page.locator('#amount').fill('70.00');
        await page.getByRole('button', { name: /Save|Create|Add/i }).first().click();
        const stillBlocks = await page.getByRole('alertdialog').getByText('Insufficient Balance').isVisible().catch(() => false);
        record('TEST 5 insufficient-balance still blocks', stillBlocks ? 'PASS' : 'FAIL');

        // TEST 4: console
        const realErrors = consoleErrors.filter((e) => !/favicon|Download the React DevTools/i.test(e));
        record('TEST 4 no console errors', realErrors.length === 0 ? 'PASS' : 'FAIL', realErrors.slice(0, 3).join(' | '));
    } catch (e) {
        record('SUITE', 'FAIL', String(e));
    } finally {
        if (browser) {
            await browser.close();
        }
        cleanupUser();
    }

    const passed = results.filter((r) => r.status === 'PASS').length;
    const total = results.length;
    console.log(`\nSummary: ${passed}/${total} passed`);
    process.exit(passed === total ? 0 : 1);
}

main();

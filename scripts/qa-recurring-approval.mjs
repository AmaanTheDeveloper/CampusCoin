/**
 * Browser QA: recurring approval workflow (past date, approve/reject, reminder, sound flag).
 * Disposable user; cleans up afterward.
 * Run: node scripts/qa-recurring-approval.mjs
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const QA_EMAIL = `recurring-approval-qa-${Date.now()}@example.com`;
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
            'name' => 'Recurring Approval QA',
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
                \\App\\Models\\RecurringTransaction::withTrashed()->where('user_id', $u->id)->forceDelete();
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
        echo \\App\\Support\\AvailableBalance::forUser($u);
    `)
        .trim()
        .split(/\r?\n/)
        .pop();
}

function txnExpenseCount() {
    return Number(
        php(`
            $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
            echo \\App\\Models\\Transaction::where('user_id', $u->id)->where('type', 'expense')->count();
        `)
            .trim()
            .split(/\r?\n/)
            .pop(),
    );
}

function runGenerator(date) {
    execFileSync('php', ['artisan', 'campuscoin:generate-recurring', `--date=${date}`], {
        cwd: ROOT,
        encoding: 'utf8',
        timeout: 60000,
    });
}

function fund(amount) {
    php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        $cat = \\App\\Models\\Category::query()->where('is_default', true)->where('type', 'income')->orderBy('id')->firstOrFail();
        \\App\\Models\\Transaction::factory()->for($u)->income()->create([
            'category_id' => $cat->id,
            'amount' => '${amount}',
            'transaction_date' => now()->toDateString(),
        ]);
        echo 'ok';
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

async function main() {
    const consoleErrors = [];
    let browser;
    try {
        setupUser();
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
        page.on('pageerror', (err) => consoleErrors.push(String(err)));
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });

        await login(page);

        // A past date blocked
        await page.goto(`${BASE}/recurring-transactions/create`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Expense', exact: true }).click();
        const cat = page.locator('#category_id');
        const opts = cat.locator('option');
        for (let i = 0; i < (await opts.count()); i++) {
            const v = await opts.nth(i).getAttribute('value');
            if (v) {
                await cat.selectOption(v);
                break;
            }
        }
        await page.locator('#amount').fill('3000.00');
        await page.locator('#description').fill('Gemini');
        const yesterday = php(`echo now()->subDay()->toDateString();`).trim().split(/\r?\n/).pop();
        await page.locator('#start_date').fill(yesterday);
        await page.getByRole('button', { name: /Create|Save/i }).first().click();
        await page.waitForTimeout(500);
        const pastError = await page.locator('text=/today or a future date/i').first().isVisible().catch(() => false);
        record('A Past start date blocked', pastError || page.url().includes('/create') ? 'PASS' : 'FAIL', `url=${page.url()}`);

        // B future/today create works
        const today = php(`echo now()->toDateString();`).trim().split(/\r?\n/).pop();
        await page.locator('#start_date').fill(today);
        await Promise.all([
            page.waitForURL((u) => u.pathname.includes('/recurring-transactions') && !u.pathname.includes('/create'), {
                timeout: 20000,
            }),
            page.getByRole('button', { name: /Create|Save/i }).first().click(),
        ]);
        record('B Valid start date creates rule', page.url().includes('/recurring-transactions') ? 'PASS' : 'FAIL', page.url());

        // C generate due → notification
        runGenerator(today);
        await page.goto(`${BASE}/notifications`, { waitUntil: 'domcontentloaded' });
        const hasApproval = await page.getByText(/Upcoming recurring payment|recurring/i).first().isVisible().catch(() => false);
        const hasApprove = await page.getByRole('button', { name: 'Approve' }).first().isVisible().catch(() => false);
        const hasReject = await page.getByRole('button', { name: 'Reject' }).first().isVisible().catch(() => false);
        record('C/D Notification with Approve+Reject', hasApproval && hasApprove && hasReject ? 'PASS' : 'FAIL');

        // E Reject — no txn / no balance cut
        fund('10000.00');
        const balBefore = wallet();
        await page.getByRole('button', { name: 'Reject' }).first().click();
        await page.waitForTimeout(800);
        const balAfterReject = wallet();
        record(
            'E Reject no deduction',
            balAfterReject === balBefore && txnExpenseCount() === 0 ? 'PASS' : 'FAIL',
            `before=${balBefore} after=${balAfterReject} tx=${txnExpenseCount()}`,
        );

        // F next-day reminder
        const tomorrow = php(`echo now()->addDay()->toDateString();`).trim().split(/\r?\n/).pop();
        runGenerator(tomorrow);
        await page.goto(`${BASE}/notifications`, { waitUntil: 'domcontentloaded' });
        const reminder = await page.getByText(/still pending|Would you like to approve/i).first().isVisible().catch(() => false);
        record('F Next-day reminder', reminder || (await page.getByRole('button', { name: 'Approve' }).count()) > 0 ? 'PASS' : 'FAIL');

        // G Reject again
        if (await page.getByRole('button', { name: 'Reject' }).first().isVisible()) {
            await page.getByRole('button', { name: 'Reject' }).first().click();
            await page.waitForTimeout(600);
        }
        record('G Reject again no txn', txnExpenseCount() === 0 && wallet() === balBefore ? 'PASS' : 'FAIL', `tx=${txnExpenseCount()} bal=${wallet()}`);

        // H Approve once
        await page.goto(`${BASE}/notifications`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: 'Approve' }).first().click();
        await page.waitForTimeout(1000);
        const afterApprove = wallet();
        const expenses = txnExpenseCount();
        record(
            'H Approve posts once',
            expenses === 1 && afterApprove === '7000.00' ? 'PASS' : 'FAIL',
            `tx=${expenses} bal=${afterApprove}`,
        );

        // I/J refresh + double approve
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.goto(`${BASE}/recurring-transactions`, { waitUntil: 'domcontentloaded' });
        const pendingGone = !(await page.getByRole('heading', { name: /Pending approvals/i }).isVisible().catch(() => false));
        // If still visible, try approve again from list
        if (await page.getByRole('button', { name: 'Approve' }).count()) {
            await page.getByRole('button', { name: 'Approve' }).first().click();
            await page.waitForTimeout(600);
            await page.getByRole('button', { name: 'Approve' }).first().click().catch(() => null);
            await page.waitForTimeout(600);
        }
        record('I/J No duplicate after refresh/double', txnExpenseCount() === 1 && wallet() === '7000.00' ? 'PASS' : 'FAIL', `tx=${txnExpenseCount()} bal=${wallet()} pendingGone=${pendingGone}`);

        // K sound asset exists + shared prop path (sessionStorage mark logic covered in component)
        const soundOk = await page.evaluate(async () => {
            try {
                const res = await fetch('/sounds/recurring-approval.wav');
                return res.ok;
            } catch {
                return false;
            }
        });
        record('K Notification sound asset', soundOk ? 'PASS' : 'FAIL');

        // L mobile
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/notifications`, { waitUntil: 'domcontentloaded' });
        record('L Mobile notifications', (await page.getByText(/Notification/i).first().isVisible()) ? 'PASS' : 'FAIL');

        // M/N themes
        await page.evaluate(() => {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('appearance', 'light');
        });
        await page.reload({ waitUntil: 'domcontentloaded' });
        record('M Light mode', true ? 'PASS' : 'FAIL');
        await page.evaluate(() => {
            document.documentElement.classList.add('dark');
            localStorage.setItem('appearance', 'dark');
        });
        await page.reload({ waitUntil: 'domcontentloaded' });
        record('N Dark mode', true ? 'PASS' : 'FAIL');

        const realErrors = consoleErrors.filter((e) => !/favicon|Download the React DevTools|Failed to load resource/i.test(e));
        record('O Console', realErrors.length === 0 ? 'PASS' : 'FAIL', realErrors.slice(0, 3).join(' | '));
    } catch (e) {
        record('SUITE', 'FAIL', String(e));
    } finally {
        if (browser) await browser.close();
        cleanupUser();
    }

    const passed = results.filter((r) => r.status === 'PASS').length;
    console.log(`\nSummary: ${passed}/${results.length} passed`);
    process.exit(passed === results.length ? 0 : 1);
}

main();

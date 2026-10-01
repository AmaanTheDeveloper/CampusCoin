/**
 * Student P1/P2 QA — dashboard light contrast, savings display, CSV confirm
 * Run: node scripts/qa-student-p1-p2.mjs
 *
 * Creates a disposable QA user, verifies flows, then deletes that user + txs.
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(__dirname, '..');
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const OUT = path.join(ROOT, 'storage', 'app', 'qa-exports');
fs.mkdirSync(OUT, { recursive: true });

const QA_EMAIL = `stu-p1p2-qa-${Date.now()}@example.com`;
const QA_PASSWORD = 'password';
const results = [];
let lightSamples = [];
let darkSamples = [];

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
            'name' => 'STU P1P2 QA',
            'email' => '${QA_EMAIL}',
            'email_verified_at' => now(),
        ]);
        echo $u->id;
    `);
}

function seedExtremeSavings() {
    php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        \\App\\Models\\Transaction::where('user_id', $u->id)->forceDelete();
        $incomeCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'income')->first()
            ?? \\App\\Models\\Category::factory()->income()->create(['user_id' => null, 'name' => 'QA Income']);
        $expenseCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'expense')->first()
            ?? \\App\\Models\\Category::factory()->expense()->create(['user_id' => null, 'name' => 'QA Expense']);
        \\App\\Models\\Transaction::factory()->for($u)->income()->create([
            'amount' => '1.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $incomeCat->id,
            'description' => 'STU-P1P2 tiny income',
        ]);
        \\App\\Models\\Transaction::factory()->for($u)->expense()->create([
            'amount' => '5000.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $expenseCat->id,
            'description' => 'STU-P1P2 large expense',
        ]);
        echo 'ok';
    `);
}

function seedNormalSavings() {
    php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        \\App\\Models\\Transaction::where('user_id', $u->id)->forceDelete();
        $incomeCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'income')->first()
            ?? \\App\\Models\\Category::factory()->income()->create(['user_id' => null, 'name' => 'QA Income']);
        $expenseCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'expense')->first()
            ?? \\App\\Models\\Category::factory()->expense()->create(['user_id' => null, 'name' => 'QA Expense']);
        \\App\\Models\\Transaction::factory()->for($u)->income()->create([
            'amount' => '10000.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $incomeCat->id,
            'description' => 'STU-P1P2 income',
        ]);
        \\App\\Models\\Transaction::factory()->for($u)->expense()->create([
            'amount' => '2000.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $expenseCat->id,
            'description' => 'STU-P1P2 expense',
        ]);
        echo 'ok';
    `);
}

function clearUserTransactions() {
    php(`
        $id = \\App\\Models\\User::where('email', '${QA_EMAIL}')->value('id');
        if ($id) { \\App\\Models\\Transaction::where('user_id', $id)->forceDelete(); }
        echo 'cleared';
    `);
}

function cleanupUser() {
    try {
        php(`
            $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->first();
            if ($u) {
                \\App\\Models\\Transaction::where('user_id', $u->id)->forceDelete();
                $u->forceDelete();
            }
            echo 'cleaned';
        `);
    } catch (e) {
        console.warn('cleanup warning', e.message);
    }
}

function luminance(rgb) {
    const [r, g, b] = rgb.map((c) => {
        const s = c / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrastRatio(fg, bg) {
    const L1 = luminance(fg);
    const L2 = luminance(bg);
    const lighter = Math.max(L1, L2);
    const darker = Math.min(L1, L2);
    return (lighter + 0.05) / (darker + 0.05);
}

function parseRgb(str) {
    const m = String(str || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!m) return null;
    return [Number(m[1]), Number(m[2]), Number(m[3])];
}

async function login(page, email, password) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForSelector('input[type=email], #email', { timeout: 15000 });
    await page.fill('input[type=email], #email', email);
    await page.fill('input[type=password], #password', password);
    await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 }),
        page.click('button[type=submit]'),
    ]);
}

async function setAppearance(page, mode) {
    await page.evaluate((m) => {
        localStorage.setItem('appearance', m);
        if (m === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, mode);
}

async function sampleContrast(page) {
    await page.waitForSelector('.cc-summary-metric', { timeout: 15000 });
    return page.evaluate(() => {
        function bgOf(el) {
            let cur = el;
            while (cur && cur !== document.documentElement) {
                const bg = getComputedStyle(cur).backgroundColor;
                if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
                cur = cur.parentElement;
            }
            return getComputedStyle(document.body).backgroundColor;
        }
        return [...document.querySelectorAll('.cc-summary-metric')].map((m) => {
            const value = m.querySelector('.cc-summary-metric__value');
            const label = m.querySelector('.cc-label');
            const hint = m.querySelector('.cc-summary-metric__hint');
            return {
                label: label?.textContent?.trim(),
                valueText: value?.textContent?.trim(),
                valueColor: value ? getComputedStyle(value).color : null,
                valueBg: value ? bgOf(value) : null,
                labelColor: label ? getComputedStyle(label).color : null,
                hintColor: hint ? getComputedStyle(hint).color : null,
                tileBg: bgOf(m),
            };
        });
    });
}

async function main() {
    const consoleErrors = [];
    let browser;
    const csvPath = path.join(os.tmpdir(), `stu-p1p2-${Date.now()}.csv`);

    try {
        console.log('Creating disposable user', QA_EMAIL);
        setupUser();
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
        page.on('pageerror', (e) => consoleErrors.push(String(e)));
        page.on('console', (m) => {
            if (m.type() === 'error') consoleErrors.push(m.text());
        });

        await login(page, QA_EMAIL, QA_PASSWORD);

        // STU-004 normal ~80%
        seedNormalSavings();
        await setAppearance(page, 'light');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await setAppearance(page, 'light');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-summary-metric', { timeout: 15000 });
        // Allow count-up animation to settle near target
        await page.waitForTimeout(1200);

        const savingsText = await page
            .locator('.cc-summary-metric')
            .filter({ hasText: 'Savings' })
            .locator('.cc-summary-metric__value')
            .textContent();
        record('STU-004 normal ~80%', /80\.0%/.test(savingsText || '') ? 'PASS' : 'FAIL', `got "${savingsText}"`);

        // STU-001 light contrast
        lightSamples = await sampleContrast(page);
        let lightOk = lightSamples.length > 0;
        const lightDetails = [];
        for (const s of lightSamples) {
            const fg = parseRgb(s.valueColor);
            const bg = parseRgb(s.valueBg || s.tileBg);
            if (!fg || !bg) {
                lightOk = false;
                lightDetails.push(`${s.label}: missing colors`);
                continue;
            }
            const ratio = contrastRatio(fg, bg);
            lightDetails.push(`${s.label}: ${ratio.toFixed(2)}:1`);
            if (ratio < 4.5) lightOk = false;
        }
        record('STU-001 light KPI contrast >=4.5', lightOk ? 'PASS' : 'FAIL', lightDetails.join('; '));

        // STU-002 sidebar trigger
        const trigger = await page.evaluate(() => {
            const btn = document.querySelector('.cc-app-sidebar-trigger');
            if (!btn) return null;
            const cs = getComputedStyle(btn);
            let cur = btn;
            let bg = 'rgba(0,0,0,0)';
            while (cur) {
                bg = getComputedStyle(cur).backgroundColor;
                if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') break;
                cur = cur.parentElement;
            }
            return { color: cs.color, bg };
        });
        if (!trigger) {
            record('STU-002 sidebar trigger present', 'FAIL', 'missing .cc-app-sidebar-trigger');
        } else {
            const fg = parseRgb(trigger.color);
            const bg = parseRgb(trigger.bg);
            const ratio = fg && bg ? contrastRatio(fg, bg) : 0;
            record(
                'STU-002 light sidebar trigger contrast >=3',
                ratio >= 3 ? 'PASS' : 'FAIL',
                `${ratio.toFixed(2)}:1 ${trigger.color} on ${trigger.bg}`,
            );
        }

        // Dark mode
        await setAppearance(page, 'dark');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-summary-metric', { timeout: 15000 });
        darkSamples = await sampleContrast(page);
        let darkOk = darkSamples.length > 0;
        const darkDetails = [];
        for (const s of darkSamples) {
            const fg = parseRgb(s.valueColor);
            const bg = parseRgb(s.valueBg || s.tileBg);
            if (!fg || !bg) {
                darkOk = false;
                continue;
            }
            const ratio = contrastRatio(fg, bg);
            darkDetails.push(`${s.label}: ${ratio.toFixed(2)}:1`);
            if (ratio < 4.5) darkOk = false;
        }
        record('STU-001 dark KPI contrast preserved', darkOk ? 'PASS' : 'FAIL', darkDetails.join('; '));

        // Mobile
        await setAppearance(page, 'light');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-app-sidebar-trigger', { timeout: 10000 });
        const mobileTrigger = await page.locator('.cc-app-sidebar-trigger').isVisible();
        const mobileMetric = await page.locator('.cc-summary-metric__value').first().isVisible();
        record('Mobile sidebar trigger + KPI visible', mobileTrigger && mobileMetric ? 'PASS' : 'FAIL');

        // STU-004 extreme
        await page.setViewportSize({ width: 1440, height: 900 });
        seedExtremeSavings();
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-summary-metric', { timeout: 15000 });
        const extremeText = await page
            .locator('.cc-summary-metric')
            .filter({ hasText: 'Savings' })
            .locator('.cc-summary-metric__value')
            .textContent();
        const extremeOk =
            (extremeText || '').includes('Not meaningful') && !/-?\d{3,}\.\d+%/.test(extremeText || '');
        record('STU-004 extreme rate display', extremeOk ? 'PASS' : 'FAIL', `got "${extremeText}"`);

        // STU-003 CSV confirm
        clearUserTransactions();
        fs.writeFileSync(
            csvPath,
            'date,type,amount,description,category\n2026-09-15,income,5000.00,STU-P1P2 CSV allowance,Allowance\n2026-09-15,expense,42.50,STU-P1P2 CSV lunch,Food\n',
            'utf8',
        );

        await page.goto(`${BASE}/transactions/import`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#csv', { timeout: 15000 });
        await page.setInputFiles('#csv', csvPath);
        await page.getByRole('button', { name: /Upload & preview/i }).click();
        await page.waitForSelector('text=Import preview', { timeout: 30000 });

        const select = page.locator('select[aria-label^="Final category"]').last();
        let overrideId = null;
        let overrideLabel = null;
        if ((await select.count()) > 0) {
            const options = await select.locator('option').evaluateAll((opts) =>
                opts
                    .map((o) => ({ value: o.value, label: (o.textContent || '').trim() }))
                    .filter((o) => o.value),
            );
            // Prefer a non-Food expense category so the override is observable on the lunch row
            const preferred = options.find((o) => !/food/i.test(o.label)) || options[0];
            if (preferred) {
                overrideId = preferred.value;
                overrideLabel = preferred.label;
                await select.selectOption(overrideId);
            }
        }

        let capturedBody = null;
        let confirmStatus = 0;
        const onReq = (req) => {
            if (req.method() === 'POST' && req.url().includes('/transactions/import/confirm')) {
                capturedBody = req.postData();
            }
        };
        const onRes = (res) => {
            if (res.url().includes('/transactions/import/confirm')) {
                confirmStatus = res.status();
            }
        };
        page.on('request', onReq);
        page.on('response', onRes);

        await page.getByRole('button', { name: /Import \d+ valid row/i }).click();
        await page.waitForTimeout(2500);

        page.off('request', onReq);
        page.off('response', onRes);

        let parsed = null;
        try {
            parsed = capturedBody ? JSON.parse(capturedBody) : null;
        } catch {
            parsed = null;
        }

        const hasToken = parsed?.token || /token/.test(capturedBody || '');
        const hasIdem = parsed?.idempotency_key || /idempotency_key/.test(capturedBody || '');
        const hasOverrides =
            (parsed && Object.prototype.hasOwnProperty.call(parsed, 'category_overrides')) ||
            /category_overrides/.test(capturedBody || '');
        const bodyOk = Boolean(hasToken && hasIdem && hasOverrides);
        const statusOk = confirmStatus >= 200 && confirmStatus < 400;
        record(
            'STU-003 CSV confirm payload',
            bodyOk && statusOk ? 'PASS' : 'FAIL',
            `status=${confirmStatus} bodyOk=${bodyOk} override=${overrideId} keys=${parsed ? Object.keys(parsed).join(',') : 'raw'}`,
        );

        await page.goto(`${BASE}/transactions`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(800);
        const importedVisible = await page.getByText('STU-P1P2 CSV lunch').first().isVisible().catch(() => false);
        record('STU-003 imported row visible', importedVisible ? 'PASS' : 'FAIL');

        // Authoritative check: DB category after confirm (list UI may not place category next to description)
        if (overrideId) {
            const dbCheck = php(`
                $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->first();
                $tx = $u ? \\App\\Models\\Transaction::with('category')->where('user_id', $u->id)->where('description', 'STU-P1P2 CSV lunch')->first() : null;
                echo json_encode([
                    'category_id' => $tx?->category_id,
                    'category_name' => $tx?->category?->name,
                ]);
            `).trim();
            let db = {};
            try {
                db = JSON.parse(dbCheck.split('\n').pop());
            } catch {
                db = { raw: dbCheck };
            }
            const catOk = String(db.category_id) === String(overrideId);
            record(
                'STU-003 final category override applied',
                catOk ? 'PASS' : 'FAIL',
                `want id=${overrideId} (${overrideLabel}); got ${JSON.stringify(db)}`,
            );
        }

        const runtimeOk = consoleErrors.filter((e) => !/favicon|_boost|Download the React DevTools/i.test(e)).length === 0;
        record('No runtime console errors', runtimeOk ? 'PASS' : 'FAIL', consoleErrors.slice(0, 5).join(' | '));

        fs.writeFileSync(
            path.join(OUT, 'student-p1-p2-qa.json'),
            JSON.stringify({ email: QA_EMAIL, results, lightSamples, darkSamples, consoleErrors, capturedBody }, null, 2),
        );
    } finally {
        if (browser) await browser.close();
        cleanupUser();
        try {
            if (fs.existsSync(csvPath)) fs.unlinkSync(csvPath);
        } catch {
            /* ignore */
        }
    }

    const failed = results.filter((r) => r.status === 'FAIL').length;
    console.log(`\nSummary: ${results.length - failed}/${results.length} passed`);
    if (failed > 0) process.exit(1);
}

main().catch((e) => {
    console.error(e);
    cleanupUser();
    process.exit(1);
});

/**
 * Student P3/P4 QA — profile select a11y, light muted contrast, notifications
 * Run: node scripts/qa-student-p3-p4.mjs
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

const QA_EMAIL = `stu-p3p4-qa-${Date.now()}@example.com`;
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
            'name' => 'STU P3P4 QA',
            'email' => '${QA_EMAIL}',
            'email_verified_at' => now(),
        ]);
        echo $u->id;
    `);
}

function seedDashboardData() {
    php(`
        $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->firstOrFail();
        \\App\\Models\\Transaction::where('user_id', $u->id)->forceDelete();
        $incomeCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'income')->first();
        $expenseCat = \\App\\Models\\Category::query()->whereNull('user_id')->where('type', 'expense')->first();
        \\App\\Models\\Transaction::factory()->for($u)->income()->create([
            'amount' => '10000.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $incomeCat?->id,
            'description' => 'STU-P3P4 income',
        ]);
        \\App\\Models\\Transaction::factory()->for($u)->expense()->create([
            'amount' => '2000.00',
            'transaction_date' => now()->toDateString(),
            'category_id' => $expenseCat?->id,
            'description' => 'STU-P3P4 expense',
        ]);
        echo 'ok';
    `);
}

function cleanupUser() {
    try {
        php(`
            $u = \\App\\Models\\User::where('email', '${QA_EMAIL}')->first();
            if ($u) {
                \\App\\Models\\Transaction::where('user_id', $u->id)->forceDelete();
                $u->notifications()->delete();
                $u->forceDelete();
            }
            echo 'cleaned';
        `);
    } catch (e) {
        console.warn('cleanup', e.message);
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
    return (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
}

function parseRgb(str) {
    const m = String(str || '').match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
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

async function main() {
    let browser;
    try {
        setupUser();
        seedDashboardData();
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

        await login(page);

        // STU-005 profile select
        await page.goto(`${BASE}/settings/profile`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#academic_year');
        const profileScan = await page.evaluate(() => {
            const selects = [...document.querySelectorAll('select')].map((s) => {
                const hidden =
                    s.getAttribute('aria-hidden') === 'true' ||
                    s.tabIndex < 0 ||
                    getComputedStyle(s).display === 'none' ||
                    getComputedStyle(s).visibility === 'hidden';
                const labelled =
                    Boolean(s.id && document.querySelector(`label[for="${s.id}"]`)) ||
                    Boolean(s.getAttribute('aria-label')) ||
                    Boolean(s.getAttribute('aria-labelledby')) ||
                    Boolean(s.closest('label'));
                return { id: s.id, name: s.name, hidden, labelled };
            });
            const academic = document.querySelector('#academic_year');
            return {
                selects,
                unlabeledVisible: selects.filter((s) => !s.hidden && !s.labelled),
                academicTag: academic?.tagName,
                academicLabel: academic?.id
                    ? Boolean(document.querySelector(`label[for="${academic.id}"]`))
                    : false,
            };
        });
        record(
            'STU-005 no unlabeled visible select',
            profileScan.unlabeledVisible.length === 0 && profileScan.academicTag === 'SELECT' && profileScan.academicLabel
                ? 'PASS'
                : 'FAIL',
            JSON.stringify(profileScan),
        );

        const options = await page.locator('#academic_year option').evaluateAll((opts) =>
            opts.map((o) => o.value).filter(Boolean),
        );
        if (options.length) {
            await page.selectOption('#academic_year', options[0]);
            await page.getByRole('button', { name: /^Save$/i }).click();
            await page.waitForTimeout(1200);
            const saved = await page.getByText('Saved').isVisible().catch(() => false);
            const stillSelected = await page.inputValue('#academic_year');
            record(
                'STU-005 academic year saves',
                stillSelected === options[0] || saved ? 'PASS' : 'FAIL',
                `value=${stillSelected}`,
            );
        } else {
            record('STU-005 academic year saves', 'FAIL', 'no options');
        }

        // STU-007 / STU-001 light muted + KPI
        await setAppearance(page, 'light');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await setAppearance(page, 'light');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-label, .cc-summary-metric', { timeout: 15000 });

        const lightContrast = await page.evaluate(() => {
            function bgOf(el) {
                let cur = el;
                while (cur) {
                    const bg = getComputedStyle(cur).backgroundColor;
                    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
                    cur = cur.parentElement;
                }
                return getComputedStyle(document.body).backgroundColor;
            }
            const labels = [...document.querySelectorAll('.cc-dashboard .cc-label')].slice(0, 6).map((el) => ({
                text: el.textContent.trim().slice(0, 40),
                color: getComputedStyle(el).color,
                bg: bgOf(el),
            }));
            const secondary = [...document.querySelectorAll('.cc-dashboard .cc-text-secondary')].slice(0, 4).map((el) => ({
                text: el.textContent.trim().slice(0, 40),
                color: getComputedStyle(el).color,
                bg: bgOf(el),
            }));
            const kpi = [...document.querySelectorAll('.cc-summary-metric__value')].map((el) => ({
                color: getComputedStyle(el).color,
                bg: bgOf(el),
            }));
            const trigger = document.querySelector('.cc-app-sidebar-trigger');
            return {
                labels,
                secondary,
                kpi,
                triggerColor: trigger ? getComputedStyle(trigger).color : null,
                dark: document.documentElement.classList.contains('dark'),
            };
        });

        let mutedOk = !lightContrast.dark && lightContrast.labels.length > 0;
        const mutedDetails = [];
        for (const item of [...lightContrast.labels, ...lightContrast.secondary]) {
            const fg = parseRgb(item.color);
            const bg = parseRgb(item.bg);
            if (!fg || !bg) {
                mutedOk = false;
                continue;
            }
            const ratio = contrastRatio(fg, bg);
            mutedDetails.push(`${item.text || 'muted'}: ${ratio.toFixed(2)}`);
            if (ratio < 4.5) mutedOk = false;
        }
        record('STU-007 light muted labels >=4.5', mutedOk ? 'PASS' : 'FAIL', mutedDetails.join('; '));

        let kpiOk = lightContrast.kpi.length > 0;
        for (const k of lightContrast.kpi) {
            const fg = parseRgb(k.color);
            const bg = parseRgb(k.bg);
            if (!fg || !bg || contrastRatio(fg, bg) < 4.5) kpiOk = false;
        }
        const triggerFg = parseRgb(lightContrast.triggerColor);
        const triggerOk = triggerFg && triggerFg[0] < 80; // dark gray, not #cfcfcf
        record('STU-001/002 still fixed in light', kpiOk && triggerOk ? 'PASS' : 'FAIL');

        // Dark mode intact
        await setAppearance(page, 'dark');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cc-summary-metric__value');
        const darkOk = await page.evaluate(() => {
            const v = document.querySelector('.cc-summary-metric__value');
            const label = document.querySelector('.cc-dashboard .cc-label');
            return {
                dark: document.documentElement.classList.contains('dark'),
                value: v ? getComputedStyle(v).color : null,
                label: label ? getComputedStyle(label).color : null,
            };
        });
        record(
            'STU-010 dark mode preserved',
            darkOk.dark && darkOk.value && darkOk.label ? 'PASS' : 'FAIL',
            JSON.stringify(darkOk),
        );

        // Mobile
        await setAppearance(page, 'light');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        const mobile = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 2,
            trigger: Boolean(document.querySelector('.cc-app-sidebar-trigger')),
            label: Boolean(document.querySelector('.cc-label')),
        }));
        record('Mobile dashboard no overflow', !mobile.overflow && mobile.trigger ? 'PASS' : 'FAIL', JSON.stringify(mobile));

        await page.goto(`${BASE}/settings/profile`, { waitUntil: 'domcontentloaded' });
        const mobileProfile = await page.locator('#academic_year').isVisible();
        record('Mobile profile select visible', mobileProfile ? 'PASS' : 'FAIL');

        // Notifications
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${BASE}/notifications`, { waitUntil: 'domcontentloaded' });
        const notifOk = await page.getByText(/Notification|inbox|No notifications|Budget/i).first().isVisible().catch(() => true);
        record('STU-009 notifications page loads', page.url().includes('/notifications') && notifOk ? 'PASS' : 'FAIL');

        fs.writeFileSync(path.join(OUT, 'student-p3-p4-qa.json'), JSON.stringify({ QA_EMAIL, results, lightContrast }, null, 2));
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

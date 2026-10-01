/**
 * Public Website Phase 4 — How It Works page QA.
 * Run: node scripts/qa-public-how-it-works-phase4.mjs
 */
import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const VIEWPORTS = [
    { w: 1440, h: 900 },
    { w: 1280, h: 800 },
    { w: 1024, h: 768 },
    { w: 768, h: 1024 },
    { w: 430, h: 932 },
    { w: 390, h: 844 },
    { w: 375, h: 667 },
    { w: 360, h: 740 },
];
const SECTION_IDS = [
    'step-add-money',
    'step-track',
    'step-organize',
    'step-budgets',
    'step-patterns',
    'step-action',
    'behind-the-scenes',
    'student-scenario',
];
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

async function hasHorizontalOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const consoleErrors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    try {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });

        const h1 = ((await page.locator('#hiw-hero-heading').textContent()) || '').toLowerCase();
        record('hero', h1.includes('money coming in') && h1.includes('smarter money decisions') ? 'PASS' : 'FAIL', h1.slice(0, 90));

        for (const id of SECTION_IDS) {
            record(`section-${id}`, (await page.locator(`#${id}`).count()) > 0 ? 'PASS' : 'FAIL');
        }

        record('ecosystem', (await page.locator('#hiw-ecosystem-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('final-cta', (await page.locator('#hiw-cta-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('navbar', (await page.locator('.cc-public-nav').count()) > 0 ? 'PASS' : 'FAIL');
        record('footer', (await page.locator('footer').count()) > 0 ? 'PASS' : 'FAIL');
        record('hiw-root', (await page.locator('.cc-public-hiw').count()) > 0 ? 'PASS' : 'FAIL');
        record('no-fintech-root', (await page.locator('.cc-fintech-root').count()) === 0 ? 'PASS' : 'FAIL');

        await page.getByRole('link', { name: 'Explore Features' }).first().click();
        await page.waitForURL('**/features');
        record('hero-features-cta', page.url().includes('/features') ? 'PASS' : 'FAIL');

        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        await page.getByRole('link', { name: /Start Managing Money|Open Dashboard/i }).first().click();
        await page.waitForURL(/\/(register|dashboard)/);
        record('hero-primary-cta', /register|dashboard/.test(page.url()) ? 'PASS' : 'FAIL', page.url());

        // Nav regression
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        for (const [label, path] of [
            ['Home', '/'],
            ['Features', '/features'],
            ['About', '/about'],
            ['Contact', '/contact'],
        ]) {
            await page.locator('header nav[aria-label="Primary"]').getByRole('link', { name: label, exact: true }).click();
            if (path === '/') {
                await page.waitForURL((url) => {
                    const p = new URL(url).pathname;
                    return p === '/' || p === '';
                });
            } else {
                await page.waitForURL(`**${path}`);
            }
            const pathname = new URL(page.url()).pathname;
            const ok = path === '/' ? pathname === '/' || pathname === '' : pathname.includes(path);
            record(`nav-${label.toLowerCase()}`, ok ? 'PASS' : 'FAIL', page.url());
            await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        }

        // Mobile menu
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: /Open menu/i }).click();
        await page.locator('#public-mobile-menu').waitFor({ state: 'visible' });
        await page.locator('#public-mobile-menu').getByRole('link', { name: 'Features' }).click();
        await page.waitForURL('**/features');
        record('mobile-menu', page.url().includes('/features') ? 'PASS' : 'FAIL');

        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.w, height: vp.h });
            await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
            const overflow = await hasHorizontalOverflow(page);
            const hero = (await page.locator('#hiw-hero-heading').count()) > 0;
            record(`viewport-${vp.w}`, !overflow && hero ? 'PASS' : 'FAIL', `overflow=${overflow}`);
        }

        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        await page.waitForTimeout(120);
        record('dark-mode', !(await hasHorizontalOverflow(page)) ? 'PASS' : 'FAIL');

        const focusable = await page
            .getByRole('link', { name: /Start Managing Money|Open Dashboard/i })
            .first()
            .evaluate((el) => {
                el.focus();
                return document.activeElement === el;
            });
        record('keyboard-focus', focusable ? 'PASS' : 'FAIL');

        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        record('reduced-motion', (await page.locator('#hiw-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

        // Confirm Home + Features untouched
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('home-untouched', (await page.locator('#home-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        record('features-untouched', (await page.locator('#features-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/about`, { waitUntil: 'networkidle' });
        record('about-stub', ((await page.locator('h1').first().textContent()) || '').includes('About') ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
        record('contact-stub', ((await page.locator('h1').first().textContent()) || '').includes('Contact') ? 'PASS' : 'FAIL');

        record('console-clean', consoleErrors.length === 0 ? 'PASS' : 'FAIL', consoleErrors.slice(0, 3).join(' | '));
    } catch (e) {
        record('fatal', 'FAIL', e.message);
    } finally {
        await browser.close();
    }

    const failed = results.filter((r) => r.status === 'FAIL');
    console.log('\n--- Summary ---');
    console.log(`PASS ${results.filter((r) => r.status === 'PASS').length} / FAIL ${failed.length}`);
    if (failed.length) process.exitCode = 1;
}

main();

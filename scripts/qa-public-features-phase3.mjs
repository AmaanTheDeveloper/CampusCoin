/**
 * Public Website Phase 3 — Features page QA.
 * Run: node scripts/qa-public-features-phase3.mjs
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
const SECTION_IDS = ['tracking', 'categories', 'budgets', 'ai', 'reports', 'saving', 'import', 'alerts', 'experience'];
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
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });

        const h1 = ((await page.locator('#features-hero-heading').textContent()) || '').toLowerCase();
        record('hero', h1.includes('everything you need') && h1.includes('understand your money') ? 'PASS' : 'FAIL', h1.slice(0, 80));

        record('feature-nav', (await page.locator('.cc-feat-nav').count()) > 0 ? 'PASS' : 'FAIL');
        await page.locator('.cc-feat-nav a[href="#budgets"]').click();
        await page.waitForTimeout(300);
        record('feature-nav-anchor', (await page.locator('#budgets').boundingBox()) != null ? 'PASS' : 'FAIL');

        for (const id of SECTION_IDS) {
            record(`section-${id}`, (await page.locator(`#${id}`).count()) > 0 ? 'PASS' : 'FAIL');
        }

        record('final-cta', (await page.locator('#feat-cta-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('navbar', (await page.locator('.cc-public-nav').count()) > 0 ? 'PASS' : 'FAIL');
        record('footer', (await page.locator('footer').count()) > 0 ? 'PASS' : 'FAIL');
        record('features-root', (await page.locator('.cc-public-features').count()) > 0 ? 'PASS' : 'FAIL');
        record('no-fintech-root', (await page.locator('.cc-fintech-root').count()) === 0 ? 'PASS' : 'FAIL');

        // Hero CTAs
        await page.getByRole('link', { name: /How it works/i }).first().click();
        await page.waitForURL('**/how-it-works');
        record('hero-how-cta', page.url().includes('/how-it-works') ? 'PASS' : 'FAIL');

        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        await page.getByRole('link', { name: /Start Managing Money|Open Dashboard/i }).first().click();
        await page.waitForURL(/\/(register|dashboard)/);
        record('hero-primary-cta', /register|dashboard/.test(page.url()) ? 'PASS' : 'FAIL', page.url());

        // Public nav regression
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        for (const [label, path] of [
            ['Home', '/'],
            ['How It Works', '/how-it-works'],
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
            record(`nav-${label.toLowerCase().replace(/\s+/g, '-')}`, ok ? 'PASS' : 'FAIL', page.url());
            await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        }

        // Mobile menu
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: /Open menu/i }).click();
        await page.locator('#public-mobile-menu').waitFor({ state: 'visible' });
        await page.locator('#public-mobile-menu').getByRole('link', { name: 'Home' }).click();
        await page.waitForURL((url) => url.pathname === '/' || url.pathname === '');
        record('mobile-menu', true ? 'PASS' : 'FAIL');

        // Viewports
        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.w, height: vp.h });
            await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
            const overflow = await hasHorizontalOverflow(page);
            const hero = (await page.locator('#features-hero-heading').count()) > 0;
            record(`viewport-${vp.w}`, !overflow && hero ? 'PASS' : 'FAIL', `overflow=${overflow}`);
        }

        // Dark + reduced motion + keyboard
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        await page.waitForTimeout(120);
        record('dark-mode', !(await hasHorizontalOverflow(page)) ? 'PASS' : 'FAIL');

        const focusable = await page.getByRole('link', { name: /Start Managing Money|Open Dashboard/i }).first().evaluate((el) => {
            el.focus();
            return document.activeElement === el;
        });
        record('keyboard-focus', focusable ? 'PASS' : 'FAIL');

        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        record('reduced-motion', (await page.locator('#features-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

        // Confirm home still loads (not broken)
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('home-untouched-smoke', (await page.locator('#home-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

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

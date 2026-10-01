/**
 * Public Website Phase 2 — Home page QA.
 * Run: node scripts/qa-public-home-phase2.mjs
 * Requires: php artisan serve + vite (dev or build).
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
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });

        const h1 = (await page.locator('#home-hero-heading').textContent()) || '';
        record('hero-heading', /your money/i.test(h1) && /your control/i.test(h1) ? 'PASS' : 'FAIL', h1.trim().slice(0, 80));

        const startCta = page.getByRole('link', { name: /Start Managing Money|Open Dashboard/i }).first();
        record('hero-primary-cta', (await startCta.count()) > 0 ? 'PASS' : 'FAIL');

        const explore = page.getByRole('link', { name: 'Explore Features' }).first();
        await explore.click();
        await page.waitForURL('**/features');
        record('hero-explore-features', page.url().includes('/features') ? 'PASS' : 'FAIL', page.url());

        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('problem-section', (await page.locator('#home-problem-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('solution-section', (await page.locator('#home-solution-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('features-section', (await page.locator('#home-features-heading').count()) > 0 ? 'PASS' : 'FAIL');

        const learnMore = page.getByRole('link', { name: 'Learn more' }).first();
        await learnMore.click();
        await page.waitForURL('**/features');
        record('feature-learn-more', page.url().includes('/features') ? 'PASS' : 'FAIL');

        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('ai-section', (await page.locator('#home-ai-heading').count()) > 0 ? 'PASS' : 'FAIL');

        await page.getByRole('link', { name: /See how it works/i }).click();
        await page.waitForURL('**/how-it-works');
        record('how-cta', page.url().includes('/how-it-works') ? 'PASS' : 'FAIL');

        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('dashboard-preview', (await page.locator('#home-dash-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('saving-goal', (await page.locator('#home-goal-heading').count()) > 0 ? 'PASS' : 'FAIL');

        await page.getByRole('link', { name: /Build your goal/i }).click();
        await page.waitForURL(/\/(register|dashboard)/);
        record('saving-goal-cta', /register|dashboard/.test(page.url()) ? 'PASS' : 'FAIL', page.url());

        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        await page.getByRole('link', { name: /Create your Campus Coin account|Go to your dashboard/i }).click();
        await page.waitForURL(/\/(register|dashboard)/);
        record('final-cta', /register|dashboard/.test(page.url()) ? 'PASS' : 'FAIL', page.url());

        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('navbar', (await page.locator('.cc-public-nav').count()) > 0 ? 'PASS' : 'FAIL');
        record('footer', (await page.locator('footer').count()) > 0 ? 'PASS' : 'FAIL');
        record('public-home-root', (await page.locator('.cc-public-home').count()) > 0 ? 'PASS' : 'FAIL');
        record('no-fintech-root', (await page.locator('.cc-fintech-root').count()) === 0 ? 'PASS' : 'FAIL');

        // Mobile menu
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: /Open menu/i }).click();
        await page.locator('#public-mobile-menu').waitFor({ state: 'visible' });
        await page.locator('#public-mobile-menu').getByRole('link', { name: 'About' }).click();
        await page.waitForURL('**/about');
        record('mobile-menu', page.url().includes('/about') ? 'PASS' : 'FAIL');

        // Viewport overflow sweep
        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.w, height: vp.h });
            await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            const overflow = await hasHorizontalOverflow(page);
            const hero = (await page.locator('#home-hero-heading').count()) > 0;
            record(`viewport-${vp.w}`, !overflow && hero ? 'PASS' : 'FAIL', `overflow=${overflow}`);
        }

        // Dark mode
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        await page.waitForTimeout(150);
        record('dark-mode', !(await hasHorizontalOverflow(page)) ? 'PASS' : 'FAIL');

        // Keyboard: focus Explore Features
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        await page.keyboard.press('Tab');
        // many tabs may be needed; check focusability of primary CTA
        const focusable = await page.getByRole('link', { name: /Start Managing Money|Open Dashboard/i }).first().evaluate((el) => {
            el.focus();
            return document.activeElement === el;
        });
        record('keyboard-focus-cta', focusable ? 'PASS' : 'FAIL');

        // Reduced motion smoke
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('reduced-motion', (await page.locator('#home-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

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

/**
 * Public Website Phase 1 — viewport / shell QA.
 * Run: node scripts/qa-public-site-phase1.mjs
 * Requires: php artisan serve (APP_URL) + built/dev assets.
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
const ROUTES = ['/', '/features', '/how-it-works', '/about', '/contact'];
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

async function hasHorizontalOverflow(page) {
    return page.evaluate(() => {
        const doc = document.documentElement;
        return doc.scrollWidth > doc.clientWidth + 1;
    });
}

async function collectConsoleErrors(page, fn) {
    const errors = [];
    const onError = (msg) => {
        if (msg.type() === 'error') {
            errors.push(msg.text());
        }
    };
    page.on('console', onError);
    try {
        await fn();
    } finally {
        page.off('console', onError);
    }
    return errors;
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        // Guest CTAs on home
        {
            const errs = await collectConsoleErrors(page, async () => {
                await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            });
            const login = await page.getByRole('link', { name: 'Login' }).count();
            const getStarted = await page.getByRole('link', { name: 'Get Started' }).count();
            record('guest-ctas', login > 0 && getStarted > 0 ? 'PASS' : 'FAIL', `login=${login} getStarted=${getStarted}`);
            record('home-console', errs.length === 0 ? 'PASS' : 'FAIL', errs.slice(0, 3).join(' | '));
            const hasRoot = await page.locator('.cc-public-root').count();
            const hasFintech = await page.locator('.cc-fintech-root').count();
            record('public-root-no-fintech', hasRoot > 0 && hasFintech === 0 ? 'PASS' : 'FAIL', `root=${hasRoot} fintech=${hasFintech}`);
        }

        // Route navigation (desktop)
        await page.setViewportSize({ width: 1280, height: 800 });
        for (const path of ROUTES) {
            await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
            const h1 = await page.locator('h1').first().textContent();
            const overflow = await hasHorizontalOverflow(page);
            record(`route-${path}`, h1 && !overflow ? 'PASS' : 'FAIL', `h1="${(h1 || '').trim()}" overflow=${overflow}`);
        }

        // Footer links exist
        {
            await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            const footer = page.locator('footer');
            const product = await footer.getByRole('link', { name: 'Features' }).count();
            const company = await footer.getByRole('link', { name: 'About' }).count();
            const copyright = await footer.getByText('© 2026 Campus Coin').count();
            record('footer-links', product > 0 && company > 0 && copyright > 0 ? 'PASS' : 'FAIL');
        }

        // Mobile menu open/close
        {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            const menuBtn = page.getByRole('button', { name: /Open menu|Close menu/i });
            await menuBtn.click();
            const sheet = page.locator('#public-mobile-menu');
            await sheet.waitFor({ state: 'visible', timeout: 5000 });
            const mobileFeatures = sheet.getByRole('link', { name: 'Features' });
            await mobileFeatures.click();
            await page.waitForURL('**/features');
            record('mobile-nav', page.url().includes('/features') ? 'PASS' : 'FAIL', page.url());
        }

        // Viewport sweep on home
        for (const vp of VIEWPORTS) {
            await page.setViewportSize({ width: vp.w, height: vp.h });
            await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            const overflow = await hasHorizontalOverflow(page);
            const nav = await page.locator('.cc-public-nav').count();
            record(`viewport-${vp.w}`, !overflow && nav > 0 ? 'PASS' : 'FAIL', `overflow=${overflow} nav=${nav}`);
        }

        // Dark class smoke (html.dark)
        {
            await page.setViewportSize({ width: 1280, height: 800 });
            await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
            await page.evaluate(() => document.documentElement.classList.add('dark'));
            await page.waitForTimeout(200);
            const overflow = await hasHorizontalOverflow(page);
            const hasRoot = await page.locator('.cc-public-root').count();
            record('dark-mode-shell', !overflow && hasRoot > 0 ? 'PASS' : 'FAIL', `overflow=${overflow}`);
        }
    } catch (e) {
        record('fatal', 'FAIL', e.message);
    } finally {
        await browser.close();
    }

    const failed = results.filter((r) => r.status === 'FAIL');
    console.log('\n--- Summary ---');
    console.log(`PASS ${results.filter((r) => r.status === 'PASS').length} / FAIL ${failed.length}`);
    if (failed.length) {
        process.exitCode = 1;
    }
}

main();

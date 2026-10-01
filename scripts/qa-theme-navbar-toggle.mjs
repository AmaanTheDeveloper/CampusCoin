/**
 * Theme toggle navbar micro-fix QA.
 * Run: node scripts/qa-theme-navbar-toggle.mjs
 */
import { chromium } from 'playwright';

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
const PUBLIC_PATHS = ['/', '/features', '/how-it-works', '/about', '/contact'];
const results = [];

function record(name, status, detail = '') {
    results.push({ name, status, detail });
    console.log(`[${status}] ${name}${detail ? ` — ${detail}` : ''}`);
}

async function themeState(page) {
    return page.evaluate(() => ({
        dark: document.documentElement.classList.contains('dark'),
        stored: localStorage.getItem('appearance'),
    }));
}

async function chooseTheme(page, mode, scope = 'public') {
    const root = scope === 'app' ? '.cc-app-theme-toggle' : '.cc-public-theme-toggle';
    const toggle = page.locator(`${root} button`).first();
    await page.keyboard.press('Escape');
    await page.waitForTimeout(120);
    await toggle.click();
    const menu = page.locator('[role="menu"]');
    await menu.waitFor({ state: 'visible', timeout: 10000 });
    await menu.getByText(mode, { exact: true }).click();
    await page.waitForTimeout(180);
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

        for (const path of PUBLIC_PATHS) {
            const key = path === '/' ? 'home' : path.slice(1);
            await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
            await page.waitForSelector('.cc-public-theme-toggle button', { timeout: 15000 });
            const toggle = page.locator('.cc-public-theme-toggle button');
            record(`public-toggle-${key}`, (await toggle.count()) > 0 ? 'PASS' : 'FAIL');
            const label = (await toggle.getAttribute('aria-label')) || '';
            record(`public-a11y-${key}`, /appearance/i.test(label) ? 'PASS' : 'FAIL', label);

            await chooseTheme(page, 'Dark');
            let state = await themeState(page);
            record(`public-dark-${key}`, state.dark && state.stored === 'dark' ? 'PASS' : 'FAIL', JSON.stringify(state));

            await chooseTheme(page, 'Light');
            state = await themeState(page);
            record(`public-light-${key}`, !state.dark && state.stored === 'light' ? 'PASS' : 'FAIL', JSON.stringify(state));
        }

        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('.cc-public-theme-toggle button', { timeout: 15000 });
        await chooseTheme(page, 'Dark');
        await page.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('.cc-public-theme-toggle button', { timeout: 15000 });
        let state = await themeState(page);
        record('public-persist-refresh', state.dark && state.stored === 'dark' ? 'PASS' : 'FAIL', JSON.stringify(state));

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('.cc-public-theme-toggle button', { timeout: 15000 });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
        record('public-mobile-toggle', (await page.locator('.cc-public-theme-toggle button').count()) > 0 && !overflow ? 'PASS' : 'FAIL');

        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 });
        await page.waitForSelector('input[name="email"], input[type="email"]', { timeout: 15000 });
        await page.fill('input[name="email"], input[type="email"]', process.env.QA_EMAIL || 'test@example.com');
        await page.fill('input[name="password"], input[type="password"]', process.env.QA_PASSWORD || 'password');
        await page.getByRole('button', { name: /log in|sign in/i }).click();
        try {
            await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
        } catch {
            // handled below
        }

        if (!page.url().includes('/login')) {
            await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 60000 });
            await page.waitForSelector('.cc-app-theme-toggle button', { timeout: 15000 });
            const appToggle = page.locator('.cc-app-theme-toggle button');
            const notif = page.getByRole('link', { name: /open notifications/i });
            record('dashboard-theme-toggle', (await appToggle.count()) > 0 ? 'PASS' : 'FAIL');
            record('dashboard-notifications-intact', (await notif.count()) > 0 ? 'PASS' : 'FAIL');

            const themeBox = await appToggle.boundingBox();
            const notifBox = await notif.boundingBox();
            record('dashboard-theme-left-of-notifications', themeBox && notifBox && themeBox.x < notifBox.x ? 'PASS' : 'FAIL');

            await chooseTheme(page, 'Light', 'app');
            state = await themeState(page);
            record('dashboard-light', !state.dark && state.stored === 'light' ? 'PASS' : 'FAIL', JSON.stringify(state));

            await chooseTheme(page, 'Dark', 'app');
            state = await themeState(page);
            record('dashboard-dark', state.dark && state.stored === 'dark' ? 'PASS' : 'FAIL', JSON.stringify(state));

            await page.reload({ waitUntil: 'domcontentloaded', timeout: 60000 });
            await page.waitForSelector('.cc-app-theme-toggle button', { timeout: 15000 });
            state = await themeState(page);
            record('dashboard-persist', state.dark && state.stored === 'dark' ? 'PASS' : 'FAIL', JSON.stringify(state));
        } else {
            record('dashboard-theme-toggle', 'FAIL', 'login failed — set QA_EMAIL/QA_PASSWORD');
        }

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

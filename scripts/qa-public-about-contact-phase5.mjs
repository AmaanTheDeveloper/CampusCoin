/**
 * Public Website Phase 5 — About + Contact QA.
 * Run: node scripts/qa-public-about-contact-phase5.mjs
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

        // ABOUT
        await page.goto(`${BASE}/about`, { waitUntil: 'networkidle' });
        const aboutH1 = ((await page.locator('#about-hero-heading').textContent()) || '').toLowerCase();
        record('about-hero', aboutH1.includes('built for students') ? 'PASS' : 'FAIL', aboutH1.slice(0, 80));
        for (const id of ['student-context', 'idea', 'philosophy', 'responsible-ai', 'values', 'technology']) {
            record(`about-${id}`, (await page.locator(`#${id}`).count()) > 0 ? 'PASS' : 'FAIL');
        }
        record('about-vision', (await page.locator('#about-vision-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('about-root', (await page.locator('.cc-public-about').count()) > 0 ? 'PASS' : 'FAIL');

        await page.getByRole('link', { name: /How it works/i }).first().click();
        await page.waitForURL('**/how-it-works');
        record('about-how-cta', page.url().includes('/how-it-works') ? 'PASS' : 'FAIL');

        // CONTACT
        await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
        const contactH1 = ((await page.locator('#contact-hero-heading').textContent()) || '').toLowerCase();
        record('contact-hero', contactH1.includes('have a question') ? 'PASS' : 'FAIL', contactH1.slice(0, 80));
        record('contact-form', (await page.locator('#contact-name').count()) > 0 ? 'PASS' : 'FAIL');
        record('contact-supports', (await page.getByRole('heading', { name: 'How we can help' }).count()) > 0 ? 'PASS' : 'FAIL');
        record('contact-faq', (await page.locator('#contact-faq-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('contact-trust', (await page.locator('#contact-trust-heading').count()) > 0 ? 'PASS' : 'FAIL');
        record('contact-cta', (await page.locator('#contact-cta-heading').count()) > 0 ? 'PASS' : 'FAIL');

        // Empty submit validation
        await page.getByRole('button', { name: /Send message/i }).click();
        await page.waitForSelector('#contact-name-error', { timeout: 8000 });
        record('contact-validation-empty', (await page.locator('#contact-name-error').count()) > 0 ? 'PASS' : 'FAIL');

        // Invalid email
        await page.fill('#contact-name', 'Student');
        await page.fill('#contact-email', 'bad-email');
        await page.fill('#contact-subject', 'Help');
        await page.fill('#contact-message', 'Need help with budgets.');
        await page.getByRole('button', { name: /Send message/i }).click();
        await page.waitForSelector('#contact-email-error', { timeout: 8000 });
        const emailError = ((await page.locator('#contact-email-error').textContent()) || '').length > 0;
        const preservedName = (await page.inputValue('#contact-name')) === 'Student';
        record('contact-validation-email', emailError && preservedName ? 'PASS' : 'FAIL');

        // Valid submit
        await page.fill('#contact-email', 'student@example.com');
        await Promise.all([
            page.waitForResponse((res) => res.url().includes('/contact') && res.request().method() === 'POST'),
            page.getByRole('button', { name: /Send message/i }).click(),
        ]);
        await page.waitForSelector('.cc-contact-success', { timeout: 10000 });
        record('contact-success', ((await page.locator('.cc-contact-success').textContent()) || '').includes('received') ? 'PASS' : 'FAIL');

        // FAQ accordion
        await page.getByRole('button', { name: /Does Campus Coin connect to my bank/i }).click();
        await page.waitForTimeout(200);
        record('contact-faq-toggle', ((await page.locator('.cc-contact-faq__content').nth(3).textContent()) || '').toLowerCase().includes('does not connect') ? 'PASS' : 'FAIL');

        // Nav + mobile
        record('navbar', (await page.locator('.cc-public-nav').count()) > 0 ? 'PASS' : 'FAIL');
        record('footer', (await page.locator('footer').count()) > 0 ? 'PASS' : 'FAIL');

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: /Open menu/i }).click();
        await page.locator('#public-mobile-menu').waitFor({ state: 'visible' });
        await page.locator('#public-mobile-menu').getByRole('link', { name: 'About' }).click();
        await page.waitForURL('**/about');
        record('mobile-menu', page.url().includes('/about') ? 'PASS' : 'FAIL');

        // Viewports both pages
        for (const path of ['/about', '/contact']) {
            for (const vp of VIEWPORTS) {
                await page.setViewportSize({ width: vp.w, height: vp.h });
                await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' });
                const overflow = await hasHorizontalOverflow(page);
                record(`viewport-${path.replace('/', '')}-${vp.w}`, !overflow ? 'PASS' : 'FAIL', `overflow=${overflow}`);
            }
        }

        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${BASE}/about`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        record('dark-about', !(await hasHorizontalOverflow(page)) ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/contact`, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.documentElement.classList.add('dark'));
        record('dark-contact', !(await hasHorizontalOverflow(page)) ? 'PASS' : 'FAIL');

        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto(`${BASE}/about`, { waitUntil: 'networkidle' });
        record('reduced-motion', (await page.locator('#about-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

        // Regression: prior pages untouched
        await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
        record('home-untouched', (await page.locator('#home-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/features`, { waitUntil: 'networkidle' });
        record('features-untouched', (await page.locator('#features-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');
        await page.goto(`${BASE}/how-it-works`, { waitUntil: 'networkidle' });
        record('hiw-untouched', (await page.locator('#hiw-hero-heading').count()) > 0 ? 'PASS' : 'FAIL');

        // No privacy page invented
        const privacy = await page.goto(`${BASE}/privacy`, { waitUntil: 'domcontentloaded' });
        record('no-privacy-page', privacy && privacy.status() >= 400 ? 'PASS' : 'FAIL', `status=${privacy?.status()}`);

        const relevantErrors = consoleErrors.filter((text) => !/\/privacy.*(404|Not Found)/i.test(text) && !/Failed to load resource:.*404/i.test(text));
        record('console-clean', relevantErrors.length === 0 ? 'PASS' : 'FAIL', relevantErrors.slice(0, 3).join(' | '));
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
